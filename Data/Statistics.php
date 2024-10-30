<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Data\Statistics
 */

namespace ISData\Data;

if (class_exists('Statistics')) {
    return;
}

require_once dirname(__DIR__) . '/Base.php';

/**
 * Call the statistics data feed and present
 */
class Statistics extends \ISData\Base
{
    const JOB_COUNT = 'jobs';
    const STORY_COUNT = 'stories';
    const PROFESSION_COUNT = 'professions';
    const LOCATION_COUNT = 'locations';

    /**
     * @const name of option passed to set_site_option()
     */
    const OPTION_NAME = 'isdata_statistics';

    /**
     * plugin is newly installed
     */
    public function deactivate()
    {
        delete_option(self::OPTION_NAME);
        parent::deactivate();
    }

    /**
     * pull data from the Feed and store it within wordpress
     */
    public function update()
    {
        $data = $this->feed->getData('statistics');
        if (empty($data)) {
            $this->setData(null);
            return;
        }
        $result = [];
        foreach ($data as $row) {
            $result[$row['title']] = $row['value'];
        }
        $this->setData($result);
    }

    /**
     * @param array $data to save for later
     */
    public function setData($data)
    {
        update_option(self::OPTION_NAME, $data);
    }

    /**
     * get data from wordpress for rendering
     * @return array
     */
    public function getData()
    {
        return get_option(self::OPTION_NAME);
    }

    /**
     * @param array $args
     * @return string html
     */
    public function renderShortcode($args = [])
    {
        $args = shortcode_atts(['name' => ''], $args);
        $name = strtolower($args['name']);
        switch ($name) {
            case self::JOB_COUNT:
                return $this->getJobCount();
            case self::LOCATION_COUNT:
                return $this->getLocationCount();
            case self::STORY_COUNT:
                return $this->getStoryCount();
            case self::PROFESSION_COUNT:
                return $this->getProfessionCount();
            case '':
                return $this->formatAll();
            default:
                return $this->getNamed($name);
        }
    }

    /**
     * @return string
     */
    private function getJobCount()
    {
        global $wpdb; // ick! wordpress global variable
        $subQuery = 'SELECT count(ID) jobcount FROM ' . $wpdb->posts . ' WHERE post_type = "isdata_job"';
        $result   = $wpdb->get_results($subQuery);
        return $result[0]->jobcount;
    }

    /**
     * @return string
     */
    private function getStoryCount()
    {
        global $wpdb; // ick! wordpress global variable
        $subQuery = 'SELECT count(ID) storycount FROM ' . $wpdb->posts . ' WHERE post_type = "isdata_story"';
        $result   = $wpdb->get_results($subQuery);
        return $result[0]->storycount;
    }

    /**
     * @return int
     */
    private function getProfessionCount()
    {
        return count(get_terms('isdata_profession', ['hide_empty' => false]));
    }

    /**
     * @return int
     */
    private function getLocationCount()
    {
        return count(get_terms('isdata_location', ['hide_empty' => false]));
    }

    /**
     * @return string html
     */
    private function formatAll()
    {
        $data = $this->getData();
        if (empty($data)) {
            return '';
        }
        $builtin = [
            self::JOB_COUNT        => $this->getJobCount(),
            self::STORY_COUNT      => $this->getStoryCount(),
            self::LOCATION_COUNT   => $this->getLocationCount(),
            self::PROFESSION_COUNT => $this->getProfessionCount(),
        ];
        foreach ($builtin as $title => $value) {
            $data[ucwords($title)] = $value;
        }

        $output = '<dl class="isdata_statistics">';
        foreach ($data as $title => $value) {
            if ($title == 'Updated') {
                continue;
            }
            $output .= '<dt>' . esc_html($title) . '</dt><dd>' . esc_html($value) . '</dd>';
        }
        $output .= '</dl>';
        return $output;
    }

    /**
     * @param string $name lowercased arg name from shortcode
     * @return string
     */
    private function getNamed($name)
    {
        $data = $this->getData();
        if (empty($data)) {
            return '';
        }
        foreach ($data as $title => $value) {
            if ($name == strtolower($title)) {
                return esc_html($value);
            }
        }
        return '';
    }
}
