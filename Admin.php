<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Admin
 */

namespace ISData;

if (class_exists('Admin')) {
    return;
}

/**
 * wordpress admin interface. Called from Plugin
 */
class Admin
{
    /**
     * wordpress admin settings form
     */
    public function adminPage()
    {
        $lastUpdated = get_option('isdata_last_updated');
        print '<div class="wrap">';
        print '<h2>' . __(Manager::TITLE) . '</h2>';
        if (!defined('DISABLE_WP_CRON')) {
            print '<div id="message" class="error">Cron jobs not set up correctly. '
                . 'Edit your wp-config.php and add <strong>define("DISABLE_WP_CRON", true);</strong> '
                . 'then set up a real system cron job. '
                . '</div>';
        }
        print '<p>' . __('Last updated') . ': ';
        if (empty($lastUpdated)) {
            print __('*never*');
        } else {
            print date(get_option('date_format'), $lastUpdated) . ' ' . date(get_option('time_format'), $lastUpdated);
        }
        print '. <a href="?page=isdata&isdata_action=update" title="May take several minutes">Update Now</a>. ';
        print '<a href="?page=isdata&isdata_action=delete-images" title="They will be replaced next Update">Delete Story Images / Thumbnails</a>. ';
        print '</p>';
        print '<p>See the <a href="' . plugins_url('isdata/readme.txt') . '">readme.txt</a> for instructions.</p>';
        print '<form method="post" action="options.php">';
        settings_fields('isdata_settings');
        do_settings_sections('isdata');
        submit_button();
        print '</form></div>';
    }

    /**
     * wordpress init hook
     */
    public function adminInit()
    {
        // adds a Settings link to the Plugin activation page
        add_filter(
            'plugin_action_links_isdata/isdata.php',
            function ($links) {
                $links[] = '<a href="options-general.php?page=isdata">Settings</a>';
                return $links;
            }
        );

        // set up the admin settings form
        add_settings_section(
            'isdata_section_default',
            'Interserve Plugin Settings',
            function () {
                return '<p>Intro Text</p>';
            },
            'isdata'
        );

        register_setting(
            'isdata_settings',
            'google_maps_api_key',
            function ($input) { // validator
                return preg_replace('|[^\w-]+|', '', $input);
            }
        );

        add_settings_field(
            'google_maps_api_key',
            'Google Maps API Key',
            function () {
                $currentKey = get_option('google_maps_api_key');
                print '<input type="text" size="50" maxlength="40" name="google_maps_api_key" '
                    . 'placeholder="used for contact maps" value="'
                    . esc_attr($currentKey) . '" ><br>'
                    . 'Get a <a href="https://cloud.google.com/maps-platform/">Google Maps Platform</a> API key '
                    . 'if you want office locations to be geocoded on sync and displayable on a map using the map shortcode.';
            },
            'isdata',
            'isdata_section_default',
            ['label_for' => 'google_maps_api_key']
        );

        foreach (['job', 'story', 'contact'] as $feed) {
            $title = ucfirst($feed);

            foreach (['before', 'after'] as $location) {
                register_setting(
                    'isdata_settings',
                    'isdata_' . $feed . '_' . $location,
                    function ($input) { // validator
                        return $input;
                    }
                );
                add_settings_field(
                    'isdata_' . $feed . '_' . $location,
                    ucfirst($location) . ' ' . $title,
                    function () use ($feed, $title, $location) {
                        $value = get_option('isdata_' . $feed . '_' . $location);
                        print '<textarea name="isdata_' . $feed . '_' . $location . '" width="100%" cols="50" '
                            . 'placeholder="HTML included ' . $location . ' ' . $title . '">'
                            . esc_textarea($value) . '</textarea>';
                    },
                    'isdata',
                    'isdata_section_default',
                    ['label_for' => 'isdata_' . $feed . '_' . $location]
                );
            }
        }

        return '';
    }
}
