<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\PostType\CustomPostType
 */

namespace ISData\PostType;

if (class_exists('CustomPostType')) {
    return;
}

require_once dirname(__DIR__) . '/Base.php';

/**
 * Stuff shared by all the data feeds
 */
abstract class CustomPostType extends \ISData\Base
{
    const WP_ID_FIELD = 'ID';
    const WP_TITLE_FIELD = 'post_title';
    const WP_EXCERPT_FIELD = 'post_excerpt';
    const WP_CONTENT_FIELD = 'post_content';
    const WP_POST_TYPE_FIELD = 'post_type';

    const DEFAULT_N_ITEMS = 10;
    const MAX_N_ITEMS = 50;

    protected $taxonomies = [];

    private $professions;
    private $durations;
    private $locations;
    private $themes;

    /**
     * @param string $feedName
     */
    public function __construct($feedName)
    {
        $this->setFeedName($feedName);
    }

    /**
     * plugin is newly installed
     */
    public function activate()
    {
        $this->init();
        parent::activate();
    }

    /**
     * plugin is being removed
     */
    public function deactivate()
    {
        // seems to be no way to remove a post type?
        parent::deactivate();
    }

    /**
     * return the template used to render this plugin
     * http://wp.tutsplus.com/tutorials/plugins/a-guide-to-wordpress-custom-post-types-creation-display-and-meta-boxes
     * @param string $prefix
     * @return string
     */
    public function template($prefix = '')
    {
        $fileName = $prefix . $this->getWordpressName() . '.php';
        // if the file exists in the theme, use it: so we can override the plugin
        $themeFile = locate_template([$fileName]);
        return empty($themeFile) ? ISDATA_PATH . $fileName : $themeFile;
    }

    /**
     * Get all the items known to wordpress, order by $idField
     * @param string $idField
     * @return array of WP_Post
     */
    public function getPosts($idField)
    {
        $query = new \WP_Query(
            [
                self::WP_POST_TYPE_FIELD => $this->getWordpressName(),
                'posts_per_page'         => -1,
                'nopaging'               => true,
                'order'                  => 'ASC',
                'orderby'                => $idField,
            ]
        );
        return $query->get_posts();
    }

    /**
     * Get all the items known to wordpress, order by $idField
     * @param string $idField
     * @return array post->ID => $idField
     */
    public function getExisting($idField)
    {
        $result = [];
        foreach ($this->getPosts($idField) as $post) {
            $result[$post->ID] = $post->$idField;
        }
        return $result;
    }

    /**
     * get all the post.IDs that match the set of selected values
     * @param string $taxonomy name
     * @param array  $values   taxonomy ids
     * @return string sql
     */
    public function taxonomyTermSubQuery($taxonomy, $values)
    {
        global $wpdb;

        $subQuery = 'SELECT distinct termrel.object_id FROM ' . $wpdb->term_relationships . ' termrel, '
            . $wpdb->term_taxonomy . ' termtax '
            . 'WHERE termtax.taxonomy = "isdata_' . $taxonomy . '" '
            . 'AND termtax.term_id in (' . join(', ', $values) . ') '
            . 'AND termtax.term_taxonomy_id = termrel.term_taxonomy_id ';
        return $subQuery;
    }

    /**
     * used for getRelatedPosts() in conjunction with taxonomyTermSubQuery()
     * @param array $scores sql clauses
     * @return string sql
     */
    public function getScoresSQL($scores)
    {
        $sql = '';
        if (empty($scores)) {
            $sql .= '0 as score ';
        } else {
            $sql .= '(' . join(' + ', $scores) . ') as score ';
        }
        return $sql;
    }

    /**
     * @param int $feedProfessionID
     * @return array
     */
    public function getProfessions($feedProfessionID)
    {
        if (!isset($this->professions)) {
            $this->professions = $this->getTaxonomyTermMap('isdata_profession');
        }
        return isset($this->professions[$feedProfessionID]) ? [
            intval(
                $this->professions[$feedProfessionID]
            ),
        ] : [];
    }

    /**
     * @param int $feedLocationID
     * @return array
     */
    public function getLocations($feedLocationID)
    {
        if (!isset($this->locations)) {
            $this->locations = $this->getTaxonomyTermMap('isdata_location');
        }
        return isset($this->locations[$feedLocationID]) ? [intval($this->locations[$feedLocationID])] : [];
    }

    /**
     * @param array $feedDurationIDs
     * @return array
     */
    public function getDurations($feedDurationIDs)
    {
        if (!isset($this->durations)) {
            $this->durations = $this->getTaxonomyTermMap('isdata_duration');
        }
        $result = [];
        foreach ($feedDurationIDs as $id) {
            if (isset($this->durations[$id])) {
                $result[] = intval($this->durations[$id]);
            }
        }
        return $result;
    }

    /**
     * @param array $feedThemeIDs
     * @return array
     */
    public function getThemes($feedThemeIDs)
    {
        if (!isset($this->themes)) {
            $this->themes = $this->getTaxonomyTermMap('isdata_theme');
        }
        $result = [];
        foreach ($feedThemeIDs as $id) {
            if (isset($this->themes[$id])) {
                $result[] = intval($this->themes[$id]);
            }
        }
        return $result;
    }

    /**
     * render the shortcode isdata_story_related | isdata_job_related.
     * http://codex.wordpress.org/Displaying_Posts_Using_a_Custom_Select_Query
     * @param array $posts array of WP_Post
     * @return string html
     */
    public function renderList($posts)
    {
        if (empty($posts)) {
            return '<p>' . __('No matching items') . '</p>';
        }

        $output = '';
        $output .= '<table class="' . $this->getWordpressName() . '">';
        $output .= '<thead><tr><th>' . __('Title') . '</th>';
        foreach ($this->taxonomies as $feed => $taxonomy) {
            $output .= '<th>' . __(ucfirst($feed)) . '</th>';
        }
//                . '<th>' . __('Date') . '</th>'
        $output .= '</tr></thead>';
        $output .= '<tbody>' . NEWLINE;
        foreach ($posts as $post) {
            $output .= '<tr>';
            $output .= '<td><a href="' . get_permalink($post) . '">' . esc_html($post->post_title) . '</a></td>';
            foreach ($this->taxonomies as $feed => $taxonomy) {
                $terms = wp_get_post_terms($post->ID, $taxonomy);
                if (empty($terms)) {
                    $output .= '<td>&nbsp;</td>';
                } else {
                    $items = [];
                    foreach ($terms as $term) {
                        $items[] = '<a href="' . get_term_link($term) . '">' . esc_html($term->name) . '</a>';
                    }
                    $output .= '<td>' . join(', ', $items) . '</td>';
                }
            }
//            $priority = get_post_meta($post->ID, 'is_priority', true) > 0 ? 'P' : '';
//            $output .= '<td>' . gmdate('Y-m-d', strtotime($post->post_date)) . $priority . '</td>';
            $output .= '</tr>' . NEWLINE;
        }
        $output .= '</tbody></table>';
        return $output;
    }

    /**
     * @param int $page     current page number
     * @param int $nRows    total number of records
     * @param int $pageSize number of records per page
     * @return string html formatted paginator with links
     */
    public function renderPaginator($page, $nRows, $pageSize)
    {
        if ($nRows <= $pageSize) {
            return '';
        }
        $nPages = ceil($nRows / $pageSize);
        $links  = [];
        for ($pageNo = 1; $pageNo <= $nPages; $pageNo++) {
            if ($page == $pageNo) {
                $links[] = '<strong>' . $pageNo . '</strong>';
                continue;
            }
            $links[] = '<a href="' . get_pagenum_link($pageNo) . '">' . $pageNo . '</a>';
        }
        $output = '<p>' . __('Page') . ' ' . join(' | ', $links) . '</p>';
        return $output;
    }

    /**
     * @param $context
     * @return string
     */
    public function renderSearchForm(\ISData\Context $context)
    {
        return '';
    }

    /**
     * Get a list of related posts based on the context. A fuzzy match
     * @param \ISData\Context $context
     * @return array of post objects
     */
    public function getRelatedPosts(\ISData\Context $context)
    {
        return [];
    }

    /**
     * Get a list of posts exactly matching the context
     * @param \ISData\Context $context
     * @return array of post objects
     */
    public function getExactPosts(\ISData\Context $context)
    {
        return [];
    }

    /**
     * Get a list of posts exactly matching the context
     * @param string $postType story | job
     * @param int    $postID   story_id | job_id etc
     * @return array of post objects
     */
    public function getExactPost($postType, $postID)
    {
        global $wpdb; // ick! wordpress global variable

        $sql = 'SELECT post.*';
        $sql .= 'FROM ' . $wpdb->posts . ' post';
        $sql .= ', ' . $wpdb->postmeta . ' postjobid';
        $sql .= ' WHERE ';
        $sql .= 'post.post_status = "publish" ';
        $sql .= 'AND post.post_type = "' . $this->getWordpressName() . '" ';
        $sql .= 'AND post.ID = postjobid.post_id ';
        $sql .= 'AND postjobid.meta_key = "' . $postType . '_id" ';
        $sql .= 'AND postjobid.meta_value = "' . intval($postID) . '" ';
        $sql .= 'ORDER BY post.post_date DESC, post.post_title ';

        return $wpdb->get_results($sql, OBJECT);
        // tried to use WP_Query here but it is unable to sort by a meta_key first then post_date desc within that
    }

    /**
     * SEO: add canonical link to head meta
     * http://support.google.com/webmasters/bin/answer.py?hl=en&answer=139394
     * @return string
     */
    public function addCanonicalLink()
    {
        if (!is_singular()) {
            return '';
        }
        if (is_search() || is_archive()) {
            return '';
        }
        $url = parse_url(get_permalink(), PHP_URL_PATH);
        return '<link rel="canonical" href="https://' . self::CANONICAL_DOMAIN . $url . '" />' . NEWLINE;
    }

    /**
     * @param \ISData\Context $context
     * @return string html
     */
    public function showRelated(\ISData\Context $context)
    {
        $posts = $this->getRelatedPosts($context);
        return $this->renderList($posts);
    }

    /**
     * @param \ISData\Context $context
     * @return string html
     */
    public function showPostList(\ISData\Context $context)
    {
        $posts    = $this->getExactPosts($context);
        $maxPosts = count($posts);
        $start    = ($context->getPageNumber() - 1) * $context->getItemsPerPage();
        if ($start >= $maxPosts) {
            $context->setPageNumber(1);
            $start = 0;
        }
        $posts = array_slice($posts, $start, $context->getItemsPerPage());
        return $this->renderPaginator($context->getPageNumber(), $maxPosts, $context->getItemsPerPage())
            . $this->renderList($posts);
    }
}
