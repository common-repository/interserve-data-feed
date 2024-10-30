<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Base
 */

namespace ISData;

if (class_exists('Base')) {
    return;
}

/**
 * Stuff shared by all the data feeds
 */
abstract class Base
{
    /**
     * for link rel=canonical on custom post types
     */
    const CANONICAL_DOMAIN = 'www.interserve.org';

    /**
     * @var Feed data from data.interserve.org
     */
    protected $feed;

    /**
     * @var string name of feed to pull from isdata $feed
     */
    private $feedName;

    /**
     * @param Feed $feed
     * @return Base
     */
    public function setFeed(Feed $feed)
    {
        $this->feed = $feed;
        return $this;
    }

    /**
     * @param string $feedName
     * @return Base
     */
    public function setFeedName($feedName = null)
    {
        $this->feedName = $feedName;
        return $this;
    }

    /**
     * @return string
     */
    public function getFeedName()
    {
        return $this->feedName;
    }

    /**
     * return the wordpress name for the plugin
     * @return string
     */
    public function getWordpressName()
    {
        return 'isdata_' . $this->getFeedName();
    }

    /**
     * plugin initialisation
     */
    public function init()
    {
    }

    /**
     * plugin is newly installed
     */
    public function activate()
    {
    }

    /**
     * plugin is being removed
     */
    public function deactivate()
    {
    }

    /**
     * pull data from the Feed and store it within wordpress
     */
    abstract public function update();

    /**
     * terms are set in CustomTaxonomy during update()
     * @param string $name
     * @return array
     */
    public function getTaxonomyTermMap($name)
    {
        $result = get_option($name . '_term_map');
        if (empty($result)) {
            return [];
        }
        return $result;
    }

    /**
     * A taxonomy term map maps between the feed index and the wordpress term
     * id so that jobs, stories etc that use the taxonomy can find the right term
     * when being imported from the feed
     * @param array $map feed id => wordpress term id
     */
    public function saveTaxonomyTermMap($map)
    {
        $name = $this->getWordpressName() . '_term_map';
        if (empty($map)) {
            delete_option($name);
            return;
        }
        update_option($name, $map);
    }

    /**
     * hook for the_content()
     * @return string
     */
    public function getContentPrefix()
    {
        return '';
    }

    /**
     * hook for the_content()
     * @return string
     */
    public function getContentSuffix()
    {
        return '';
    }

    /**
     * hook for the_excerpt()
     * @return string
     */
    public function getExcerpt()
    {
        return '';
    }

    /**
     * for wp_head
     * @return string
     */
    public function addCanonicalLink()
    {
        return '';
    }
}
