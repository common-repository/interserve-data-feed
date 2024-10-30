<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Manager
 * If you have problems with wordpress admin displaying only partial styles, add
 * define('SCRIPT_DEBUG', true);
 * to wp-config.php
 */

namespace ISData;

if (class_exists('Manager')) {
    return;
}

require_once __DIR__ . '/Context.php';

/**
 * Main plugin object.
 * Useful advice http://www.yaconiello.com/blog/how-to-write-wordpress-plugin/
 * http://wp.smashingmagazine.com/2011/03/08/ten-things-every-wordpress-plugin-developer-should-know/
 * Design objectives
 * - create a pluggable suite of feeds
 * - deep integration with wordpress: do it "the wordpress way"
 * - make it easier to unit test
 * Acts as a facade between the plugins and the wordpress action / hooks and
 * negotiates the Context and parameters to pass to them
 */
class Manager
{
    const TITLE = 'Interserve Data'; // human friendly name of plugin
    const DATA_STATISTICS = 'statistics';
    const POST_TYPE_CONTACT = 'contact';
    const POST_TYPE_JOB = 'job';
    const POST_TYPE_STORY = 'story';
    const TAXONOMY_PROFESSION = 'profession';
    const TAXONOMY_LOCATION = 'location';
    const TAXONOMY_DURATION = 'duration';
    const TAXONOMY_THEME = 'theme';

    const CRON_ACTION = 'isdata_cron';

    /**
     * @var Admin
     */
    private $admin; // admin interface

    /**
     * @var Feed|null
     */
    private $feed; // a feed manager

    /**
     * @var array list of taxonomy classes
     */
    private $taxonomy = [
        self::TAXONOMY_PROFESSION => null,
        self::TAXONOMY_LOCATION   => null,
        self::TAXONOMY_DURATION   => null,
        self::TAXONOMY_THEME      => null,
    ];

    /**
     * @var array of custom post types
     */
    private $postType = [
        self::POST_TYPE_CONTACT     => null,
        self::POST_TYPE_JOB         => null,
        self::POST_TYPE_STORY       => null,
    ];

    /**
     * @var array of other plugins
     */
    private $data = [
        self::DATA_STATISTICS => null,
    ];

    /**
     * constructor
     * @param Feed $feed dependency injection
     */
    public function __construct($feed = null)
    {
        if (empty($feed)) {
            require_once(__DIR__ . '/Feed.php');
            $feed = new Feed();
        }
        $this->feed = $feed;
    }

    /**
     * return a new query context
     * @param array $args
     * @return Context
     */
    public static function getContext($args = [])
    {
        $context = new Context();
        $context->calculate($args);
        return $context;
    }

    /**
     * @param string $pluginName name
     * @param string $folder
     * @return Base
     * @throws \Exception
     */
    public function loadPlugin($pluginName, $folder = '')
    {
        $pluginName = ucfirst(preg_replace('|[\W]|', '', $pluginName));
        $className  = __NAMESPACE__ . '\\' . $folder . '\\' . $pluginName;
        if (!class_exists($className)) {
            $fileName = __DIR__ . '/' . $folder . '/' . $pluginName . '.php';
            if (!file_exists($fileName)) {
                // this can be thrown if the $folder is not og+rx
                throw new \Exception('Class does not exist' . $pluginName . ' file:' . $fileName . ' classname:' . $className);
            }
            require_once($fileName);
        }

        /** @var Base $plugin */
        $plugin = new $className();
        $plugin->setFeed($this->feed);
        return $plugin;
    }

    /**
     * @param string $name of post type
     * @return PostType\CustomPostType
     * @throws \Exception
     */
    public function getData($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        return $this->data[$name] = $this->loadPlugin($name, 'Data');
    }

    /**
     * @param string $name of taxonomy
     * @return Taxonomy\CustomTaxonomy
     * @throws \Exception
     */
    public function getTaxonomy($name)
    {
        if (isset($this->taxonomy[$name])) {
            return $this->taxonomy[$name];
        }
        return $this->taxonomy[$name] = $this->loadPlugin($name, 'Taxonomy');
    }

    /**
     * @param string $name of post type
     * @return PostType\CustomPostType
     * @throws \Exception
     */
    public function getPostType($name)
    {
        if (isset($this->postType[$name])) {
            return $this->postType[$name];
        }
        return $this->postType[$name] = $this->loadPlugin($name, 'PostType');
    }

    /**
     * @return PostType\CustomPostType | null
     */
    public function getWordpressPostType()
    {
        $postType = get_post_type();
        if (empty($postType)) {
            return null;
        }
        $postType = str_replace('isdata_', '', $postType);
        if (!array_key_exists($postType, $this->postType)) {
            return null;
        }
        return $this->getPostType($postType);
    }

    /**
     * call $method on each known plugin
     * @param string $method
     */
    public function forEachPlugin($method)
    {
        foreach ($this->taxonomy as $name => $ignored) {
            $this->getTaxonomy($name)->$method();
        }
        foreach ($this->data as $name => $ignored) {
            $this->getData($name)->$method();
        }
        foreach ($this->postType as $name => $ignored) {
            $this->getPostType($name)->$method();
        }
    }

    /**
     * initialise the plugin every page request
     */
    public function init()
    {
        $this->forEachPlugin('init');
        add_action(self::CRON_ACTION, [$this, 'cron']);

        // handle admin actions triggered by clicking on the settings page
        if (isset($_REQUEST['isdata_action'])) {
            switch ($_REQUEST['isdata_action']) {
                case 'update':
                    // allow extra time because downloading all images may take a while
                    set_time_limit(360);
                    $this->update();
                    break;
                case 'delete-images':
                    $this->getPostType(self::POST_TYPE_STORY)->deleteAllMedia();
                    break;
            }
        }
    }

    /**
     * activate the plugin
     */
    public function activate()
    {
        $this->forEachPlugin('activate');
        flush_rewrite_rules();

        // set up cron jobs
        if (!wp_next_scheduled(self::CRON_ACTION)) {
            wp_schedule_event(time(), 'twicedaily', self::CRON_ACTION);
        }
    }

    /**
     * deactivate the plugin
     */
    public function deactivate()
    {
        $this->forEachPlugin('deactivate');
        wp_unschedule_event(wp_next_scheduled(self::CRON_ACTION), self::CRON_ACTION);
        wp_clear_scheduled_hook(self::CRON_ACTION);
        flush_rewrite_rules();
    }

    /**
     * pull fresh data from the feeds
     */
    public function update()
    {
        $this->forEachPlugin('update');
        update_option('isdata_last_updated', time());
    }

    /**
     * runs in the background: see activate().
     * This can take a long time (15 seconds) so make sure you have set
     * define('DISABLE_WP_CRON', true);
     * and are running this as a real cron job in the system crontab
     * every 5 minutes wget YOUR_SITE_URL/wp-cron.php
     */
    public function cron()
    {
        $this->update();
    }

    /**
     * get the wordpress admin settings handler
     * @return Admin
     */
    public function getAdmin()
    {
        if (empty($this->admin)) {
            require_once __DIR__ . '/Admin.php';
            $this->admin = new Admin();
        }
        return $this->admin;
    }

    /**
     * add stuff to the wordpress admin menu
     */
    public function adminMenu()
    {
        add_options_page('Settings', self::TITLE, 'manage_options', 'isdata', [$this->getAdmin(), 'adminPage']);
    }

    /**
     * wordpress init hook
     */
    public function adminInit()
    {
        return $this->getAdmin()->adminInit();
    }

    /**
     * hooks wp_head to override link rel = canonical
     */
    public function head()
    {
        $post = $this->getWordpressPostType();
        if (empty($post)) {
            return;
        }
        print $post->addCanonicalLink();
    }

    /**
     * append / prepend our custom post type content to the page content
     * @param string $content
     * @return string
     */
    public function renderContent($content)
    {
        $post = $this->getWordpressPostType();
        if (empty($post)) {
            return $content;
        }
        $singularBefore = $singularAfter = '';
        if (is_singular()) {
            $singularBefore = get_option($post->getWordpressName() . '_before');
            $singularAfter  = get_option($post->getWordpressName() . '_after');
        }
        return $singularBefore .
            $post->getContentPrefix() .
            $content .
            $post->getContentSuffix() .
            $singularAfter;
    }

    /**
     * append / prepend our custom post type content to the page content
     * @param string $content
     * @return string
     */
    public function renderExcerpt($content)
    {
        $post = $this->getWordpressPostType();
        if (empty($post)) {
            return $content;
        }
        return $post->getExcerpt($content);
    }

    /**
     * facade for shortcode
     * @return string html
     */
    public function statistics($args = [])
    {
        return $this->getData(self::DATA_STATISTICS)->renderShortcode($args);
    }

    /**
     * facade for shortcode
     * @param array $args from the shortcode
     * @return string html
     */
    public function contactList($args = [])
    {
        return $this->getPostType(self::POST_TYPE_CONTACT)->renderShortcodeList($args);
    }

    /**
     * facade for shortcode
     * @return string html
     */
    public function contactMap()
    {
        return $this->getPostType(self::POST_TYPE_CONTACT)->renderShortcodeMap();
    }

    /**
     * facade for shortcode
     * @param array $args from the shortcode
     * @return string html
     */
    public function contactNearest($args = [])
    {
        return $this->getPostType(self::POST_TYPE_CONTACT)->renderShortcodeNearest($args);
    }

    /**
     * print a list of related jobs in a table
     * @param array $args from the shortcode
     * @return string html
     */
    public function jobRelated($args = [])
    {
        $context = $this->getContext($args);
        return $this->getPostType(self::POST_TYPE_JOB)->showRelated($context);
    }

    /**
     * print a list of related jobs in a table
     * @param array $args from the shortcode
     * @return string html
     */
    public function storyRelated($args = [])
    {
        $context = $this->getContext($args);
        return $this->getPostType(self::POST_TYPE_STORY)->showRelated($context);
    }

    /**
     * print a list of related jobs in a table
     * @param array $args from the shortcode
     * @return string html
     */
    public function jobList($args = [])
    {
        $context = $this->getContext($args);
        return $this->getPostType(self::POST_TYPE_JOB)->showPostList($context);
    }

    /**
     * print a list of related stories in a table
     * @param array $args from the shortcode
     * @return string html
     */
    public function storyList($args = [])
    {
        $context = $this->getContext($args);
        return $this->getPostType(self::POST_TYPE_STORY)->showPostList($context);
    }

    /**
     * print a job search form
     * @param array $args from the shortcode
     * @return string html
     */
    public function jobSearchForm($args = [])
    {
        $context = $this->getContext($args);
        return $this->getPostType(self::POST_TYPE_JOB)->renderSearchForm($context);
    }

    /**
     * print a story search form
     * @param array $args from the shortcode
     * @return string html
     */
    public function storySearchForm($args = [])
    {
        $context = $this->getContext($args);
        return $this->getPostType(self::POST_TYPE_STORY)->renderSearchForm($context);
    }

    /**
     * @param array $args from the shortcode
     * @return string html
     */
    public function professionList($args = [])
    {
        return $this->getTaxonomy(self::TAXONOMY_PROFESSION)->showList($args);
    }

    /**
     * @param array $args from the shortcode
     * @return string html
     */
    public function durationList($args = [])
    {
        return $this->getTaxonomy(self::TAXONOMY_DURATION)->showList($args);
    }

    /**
     * @param array $args from the shortcode
     * @return string html
     */
    public function themeList($args = [])
    {
        return $this->getTaxonomy(self::TAXONOMY_THEME)->showList($args);
    }

    /**
     * @param array $args from the shortcode
     * @return string html
     */
    public function locationList($args = [])
    {
        return $this->getTaxonomy(self::TAXONOMY_LOCATION)->showList($args);
    }

    /**
     * from http://thomasgriffinmedia.com/blog/2011/01/how-to-list-child-pages-in-your-wordpress-sidebar-and-pages/
     * @param array $args from the shortcode
     * @return string html
     */
    public function childPages($args = [])
    {
        $post = get_post();
        if (is_page() && $post->post_parent) { // Make sure we are on a page and that the page is a parent
            $kiddies = wp_list_pages('sort_column=menu_order&title_li=&child_of=' . $post->post_parent . '&echo=0');
        } else {
            $kiddies = wp_list_pages('sort_column=menu_order&title_li=&child_of=' . $post->ID . '&echo=0');
        }

        if (empty($kiddies)) {
            return '';
        }
        return '<ul class="secondary">' . $kiddies . '</ul>';
    }
}
