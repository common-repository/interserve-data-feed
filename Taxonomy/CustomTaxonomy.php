<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Taxonomy\CustomTaxonomy
 */

namespace ISData\Taxonomy;

if (class_exists('CustomTaxonomy')) {
    return;
}

require_once dirname(__DIR__) . '/Base.php';

/**
 * Stuff shared by all the data feeds.
 * //http://net.tutsplus.com/tutorials/wordpress/introducing-wordpress-3-custom-taxonomies/
 */
abstract class CustomTaxonomy extends \ISData\Base
{
    /**
     * to allow descriptions for taxonomy searches. Not visible in all themes
     */
    const SHOW_UI = true;

    /**
     * @param string $feedName
     */
    public function __construct($feedName)
    {
        $this->setFeedName($feedName);
    }

    /**
     * called for every page load
     */
    public function init()
    {
        $name = $this->getWordpressName();
        if (taxonomy_exists($name)) {
            return;
        }

        $title = $this->getFeedName();
        $slug  = str_replace(' ', '_', $title);
        register_taxonomy(
            $name,
            '',
            [
                'labels'            => ['name' => __(ucwords($title))],
                'public'            => true,
                'show_ui'           => self::SHOW_UI,
                'show_in_nav_menus' => true,
                'show_admin_column' => true,
                'hierarchical'      => false,
                'query_var'         => $slug,
                'rewrite'           => ['slug' => __($slug)],
            ]
        );
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
        // taxonomies are added dynamically at every page load: no need to de-install
        parent::deactivate();
    }

    /**
     * pull data from the feed and stick it on wordpress
     * @return int number of active terms
     * @throws \Exception
     */
    public function update()
    {
        $feedTerms = $this->feed->getData($this->getFeedName());
        if (empty($feedTerms)) {
            return 0;
        }

        $usedTerms      = [];
        $taxonomyName   = $this->getWordpressName();
        $wordpressTerms = $this->getExisting();
        foreach ($feedTerms as $feedTerm) {
            if (isset($wordpressTerms[$feedTerm['name']])) {
                // term exists: do nothing
                $usedTerms[$feedTerm['id']] = $wordpressTerms[$feedTerm['name']];
            } else {
                $usedTerms[$feedTerm['id']] = $this->addTerm($feedTerm['name']);
            }
        }

        // delete unused
        $toDelete = array_diff($wordpressTerms, $usedTerms);
        foreach ($toDelete as $termID) {
            wp_delete_term($termID, $taxonomyName);
        }

        $this->saveTaxonomyTermMap($usedTerms);
        return count($usedTerms);
    }

    /**
     * @return array name => id
     */
    public function getExisting()
    {
        $terms = get_terms($this->getWordpressName(), ['hide_empty' => 0]);
        if (empty($terms)) {
            return [];
        }

        $result = [];
        foreach ($terms as $term) {
            // assumes names from the feed are unique, which is true for simple feeds but not for hierarchical
            $result[$term->name] = $term->term_id;
        }
        return $result;
    }

    /**
     * @param string $name title of term
     * @param int    $parentID
     * @return int
     * @throws \Exception
     */
    public function addTerm($name, $parentID = 0)
    {
        $result = wp_insert_term($name, $this->getWordpressName(), ['parent' => $parentID]);
        if (is_wp_error($result)) {
            throw new \Exception(
                'Unable to add term to taxonomy ' . $this->getWordpressName() . ': '
                . $name . ' because '
                . join(' ', $result->get_error_messages())
            );
        }
        return $result['term_id'];
    }

    /**
     * return a bulleted list of taxonomy terms followed by counts
     * @param array $args
     * @return string html ul
     */
    public function showList($args = [])
    {
        $terms = get_terms($this->getWordpressName(), ['hide_empty' => 0]);
        if (empty($terms)) {
            return ''; // no terms!
        }

        $result = '';
        foreach ($terms as $term) {
            $result .= '<li><a href="' . get_term_link($term) . '">' . esc_html($term->name) . '</a> (' . $term->count . ')</li>';
        }
        if (empty($result)) {
            return '';
        }
        return '<ul class="' . $this->getWordpressName() . '_list">' . $result . '</ul>' . NEWLINE;
    }
}
