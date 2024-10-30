<?php
/*
Plugin Name: Interserve Data Feeds
Plugin URI: http://data.interserve.org
Description: Display job openings, office contact, and other information in your site
Version: 1.2
Author: Interserve
License: GPL2
*/

define('TWENTY_FOUR_HOURS', 60 * 60 * 24);
define('NEWLINE', "\r\n");
define('ISDATA_PATH', plugin_dir_path(__FILE__));

require_once ISDATA_PATH . 'Manager.php';
$isDataManager = new \ISData\Manager();

// Installation and uninstallation hooks
// cannot use __FILE__ here if the directory is symlinked.
// see http://wpadventures.wordpress.com/2012/08/07/register_activation_hook/
register_activation_hook(WP_PLUGIN_DIR . '/isdata/isdata.php', [$isDataManager, 'activate']);
register_deactivation_hook(WP_PLUGIN_DIR . '/isdata/isdata.php', [$isDataManager, 'deactivate']);

add_action('init', [$isDataManager, 'init']);

if (is_admin()) {

    // manage the admin settings page
    add_action('admin_init', [$isDataManager, 'adminInit']);
    add_action('admin_menu', [$isDataManager, 'adminMenu']);
} else {
    // is front end

    // for canonical links
    remove_action('wp_head', 'rel_canonical'); // see wp-includes/default-filters.php
    add_action('wp_head', [$isDataManager, 'head']);

    // shortcodes
    add_shortcode('isdata_statistics', [$isDataManager, 'statistics']);
    add_shortcode('isdata_contact_list', [$isDataManager, 'contactList']);
    add_shortcode('isdata_contact_map', [$isDataManager, 'contactMap']);
    add_shortcode('isdata_contact_nearest', [$isDataManager, 'contactNearest']);
    add_shortcode('isdata_job_related', [$isDataManager, 'jobRelated']); // fuzzy match context
    add_shortcode('isdata_job_list', [$isDataManager, 'jobList']); // exact match context
    add_shortcode('isdata_job_search', [$isDataManager, 'jobSearchForm']);
    add_shortcode('isdata_story_related', [$isDataManager, 'storyRelated']);
    add_shortcode('isdata_story_list', [$isDataManager, 'storyList']); // exact match context
    add_shortcode('isdata_story_search', [$isDataManager, 'storySearchForm']);
    add_shortcode('isdata_profession_list', [$isDataManager, 'professionList']);
    add_shortcode('isdata_location_list', [$isDataManager, 'locationList']);
    add_shortcode('isdata_duration_list', [$isDataManager, 'durationList']);
    add_shortcode('isdata_theme_list', [$isDataManager, 'themeList']);
    add_shortcode('isdata_child_pages', [$isDataManager, 'childPages']);

    // render custom post type content
    add_filter('the_content', [$isDataManager, 'renderContent']);
    add_filter('the_excerpt', [$isDataManager, 'renderExcerpt']);
}

// widgets
add_action(
    'widgets_init',
    function () {
        require_once ISDATA_PATH . 'Widget/JobWidget.php';
        require_once ISDATA_PATH . 'Widget/StoryWidget.php';
        require_once ISDATA_PATH . 'Widget/ChildPagesWidget.php';
        register_widget('\ISData\Widget\JobWidget');
        register_widget('\ISData\Widget\StoryWidget');
        register_widget('\ISData\Widget\ChildPagesWidget');
    }
);
