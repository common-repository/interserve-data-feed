<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Taxonomy\Location
 */

namespace ISData\Taxonomy;

if (class_exists('Location')) {
    return;
}

require_once __DIR__ . '/CustomTaxonomy.php';

/**
 * Location taxonomy
 */
class Location extends CustomTaxonomy
{
    public function __construct()
    {
        parent::__construct('country'); // to match name of feed
    }

    /**
     * @return string
     */
    public function getWordpressName()
    {
        return 'isdata_location';
    }

    /**
     */
    public function init()
    {
        if (!taxonomy_exists($this->getWordpressName())) {
            register_taxonomy(
                $this->getWordpressName(),
                '',
                [
                    'labels'            => ['name' => __('Location')],
                    'public'            => true,
                    'show_ui'           => self::SHOW_UI,
                    'show_in_nav_menus' => true,
                    'show_admin_column' => true,
                    'hierarchical'      => true,
                    'query_var'         => 'location',
                    'rewrite'           => ['slug' => __('location'), 'hierarchical' => true],
                ]
            );
        }
    }

    /**
     * pull data from the feed and stick it on wordpress
     * @return int number of active terms
     * @throws \Exception
     */
    public function update()
    {
        $feedCountries = $this->feed->getData($this->getFeedName());
        if (empty($feedCountries)) {
            return 0;
        }

        $usedTerms = $this->createRegions($feedCountries);

        $wordpressLocations = get_terms($this->getWordpressName(), ['hide_empty' => false]);
        foreach ($feedCountries as $feedCountry) {
            $parentID = $this->findTerm($feedCountry['region_name'], $wordpressLocations);
            if (empty($parentID)) {
                throw new \Exception('Parent term should have been built already: ' . $feedCountry['region_name']);
            }

            if ($feedCountry['name'] == $feedCountry['region_name']) {
                $usedTerms[$feedCountry['id']] = $parentID;
                continue; // don't create a country with the same name as its region
            }
            $termID = $this->findTerm($feedCountry['name'], $wordpressLocations, $parentID);
            if (!empty($termID)) {
                // term exists: do nothing
                $usedTerms[$feedCountry['id']] = $termID;
                continue;
            }

            $usedTerms[$feedCountry['id']] = $this->addTerm($feedCountry['name'], $parentID);
        }

        // delete unused
        foreach ($wordpressLocations as $term) {
            if (!in_array($term->term_id, $usedTerms)) {
                wp_delete_term($term->term_id, $this->getWordpressName());
            }
        }
        $this->clearCache();
        $this->saveTaxonomyTermMap($usedTerms);
        return count($usedTerms);
    }

    /**
     * @param string $name  title of term to find
     * @param array  $terms array of term objects from wordpress
     * @param int    $parentID
     * @return int term id
     */
    public function findTerm($name, $terms, $parentID = 0)
    {
        foreach ($terms as $term) {
            if ($term->name == $name && $term->parent == $parentID) {
                return $term->term_id;
            }
        }
        return 0;
    }

    /**
     * populate the top level of the taxonomy hierarchy
     * @param array $feedCountries
     * @return array feedCountry.id => wordpressLocation.term_id
     */
    public function createRegions($feedCountries)
    {
        $wordpressLocations = get_terms($this->getWordpressName(), ['hide_empty' => false]);
        $used               = [];
        $regionsAdded       = []; // so that regions are only added once
        foreach ($feedCountries as $feedCountry) {
            if (in_array($feedCountry['region_name'], $regionsAdded)) {
                continue;
            }
            $wordpressLocationID = $this->findTerm($feedCountry['region_name'], $wordpressLocations);
            if (empty($wordpressLocationID)) {
                $wordpressLocationID = $this->addTerm($feedCountry['region_name']);
            }
            $used[$feedCountry['id']] = $wordpressLocationID;
            $regionsAdded[]           = $feedCountry['region_name'];
        }

        // $this->clearCache();
        // no need to clear cache: done by caller
        return $used;
    }

    /**
     */
    public function clearCache()
    {
        // work around a wordpress caching bug
        // http://wordpress.stackexchange.com/questions/8357/inserting-terms-in-an-hierarchical-taxonomy/
        delete_option($this->getWordpressName() . '_children');
        wp_cache_flush();
    }
}
