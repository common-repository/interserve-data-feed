<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\PostType\Contact
 */

namespace ISData\PostType;

if (class_exists('Contact')) {
    return;
}

require_once __DIR__ . '/CustomPostType.php';

/**
 * Office Contact details
 */
class Contact extends CustomPostType
{
    const INVALID_LOCATION      = 1;
    const PRIORITY_AFTER_JQUERY = 200;

    private $fields = [
        'physical_address',
        'postal_address',
        'phone',
        'fax',
        'web_site',
        'donate_link',
        'email',
        'twitter',
        'facebook',
        'linkedin',
    ];

    /**
     */
    public function __construct()
    {
        parent::__construct('contact');
    }

    /**
     * plugin initialisation
     */
    public function init()
    {
        if (!post_type_exists($this->getWordpressName())) {
            // see http://codex.wordpress.org/Function_Reference/register_post_type
            register_post_type(
                $this->getWordpressName(),
                [
                    'labels'       => [
                        'name'          => __('Contact'),
                        'singular_name' => __('Contact'),
                    ],
                    'description'  => __('Contact an office near you'),
                    'supports'     => ['title'],
                    'public'       => true,
                    'has_archive'  => false,
                    'hierarchical' => false,
                    'can_export'   => false, // because the data is imported from elsewhere
                    'query_var'    => 'contact',
                    'rewrite'      => ['slug' => __('contact')],
                    'show_ui'      => false,
                ]
            );
        }
        parent::init();
    }

    /**
     * pull data from the Feed and store it within wordpress
     */
    public function update()
    {
        $feedOffices = $this->feed->getData('office');
        if (empty($feedOffices)) {
            return 0;
        }

        $time             = time() - 60 * 60; // one hour ago
        $wordpressOffices = array_flip($this->getExisting('office_id')); // office_id => postID
        $updatedOffices   = []; // contains post IDs of updated posts
        foreach ($feedOffices as $office) {
            $postID = isset($wordpressOffices[$office['id']]) ? $wordpressOffices[$office['id']] : 0;
            $this->updateOffice($postID, $office, $time + count($updatedOffices));
            $updatedOffices[] = $postID;
        }

        // remove invalid offices
        $toDelete = array_diff($wordpressOffices, array_filter($updatedOffices));
        foreach ($toDelete as $postID) {
            $this->deleteOffice($postID);
        }
        return count($feedOffices);
    }

    /**
     * sync the office info from the feed with new incoming data
     * @param int   $postID   0 for new post, otherwise wordpress post id
     * @param array $office   info from the feed about the office
     * @param int   $postDate set the post_date of the post so that offices appear in alpha order
     * @throws \Exception if unable to add / update
     * @return void
     */
    public function updateOffice($postID, array $office, $postDate)
    {
        $args = [
            self::WP_TITLE_FIELD     => $office['name'],
            self::WP_POST_TYPE_FIELD => $this->getWordpressName(),
            'post_status'            => 'publish',
            'comment_status'         => 'closed', // disallow comments
            'ping_status'            => 'closed', // disallow pings
            'post_date'              => gmdate('Y-m-d H:i:s', $postDate),
            'post_date_gmt'          => gmdate('Y-m-d H:i:s', $postDate),
            'post_modified'          => $office['last_modified'],
            'post_modified_gmt'      => $office['last_modified'],
        ];

        if ($postID == 0) {
            $postID = wp_insert_post($args, true);
            if (is_wp_error($postID)) {
                throw new \Exception('Unable to add office: ' . join('', $postID->get_error_messages()));
            }
        } else {
            $args[self::WP_ID_FIELD] = $postID;
            $updatedPostID           = wp_update_post($args);
            if ($updatedPostID != $postID) {
                throw new \Exception('Unable to update office: ' . esc_html($office['name']));
            }
        }

        // check for changed physical-address
        $oldAddress = get_post_meta($postID, 'physical_address', true);
        $latitude   = get_post_meta($postID, 'latitude', true);
        if ($oldAddress != $office['physical_address'] || empty($latitude)) {
            $location = $this->geocode($office['physical_address']);
            update_post_meta($postID, 'latitude', $location['latitude']);
            update_post_meta($postID, 'longitude', $location['longitude']);
            update_post_meta($postID, 'country_code', $location['country_code']);
        }
        // update all the other fields
        foreach ($this->fields as $field) {
            update_post_meta($postID, $field, $office[$field]);
        }

        update_post_meta($postID, 'office_id', $office['id']);
    }

    /**
     * @param int $postID
     */
    public function deleteOffice($postID)
    {
        wp_delete_post($postID, true);
    }

    /**
     * creates a ul with all the offices listed, linking to their pages
     * @return string html
     */
    public function renderShortcodeList($args = [])
    {
        $args     = shortcode_atts(['link' => ''], $args);
        $linkType = strtolower($args['link']);
        $offices  = [];
        foreach ($this->getPosts('office_id') as $post) {
            $offices[] = '<li><a href="' . $this->getLink($post, $linkType) . '">'
                . esc_html($post->post_title) . '</a></li>';
        }
        if (empty($offices)) {
            return '<p class="isdata_contact">No offices</p>';
        }
        return '<ul class="isdata_contact">' . join('', $offices) . '</ul>';
    }

    /**
     * creates a link to the closest office
     * @return string html
     */
    public function renderShortcodeNearest($args = [])
    {
        if (!function_exists('geoip_country_name_by_name')) {
            return 'PHP geoip extension is not installed';
        }
        $args     = shortcode_atts(['link' => ''], $args);
        $linkType = strtolower($args['link']);
        $default       = '';
        $myIP          = $this->getIPAddress();
        $country       = ['United States' => 'USA', 'United Kingdom' => 'England'];
        $myCountryCode = empty($myIP) ? 'xxxnomatch' : geoip_country_code_by_name($myIP);
        $myCountryName = empty($myIP) ? 'xxxnomatch' : geoip_country_name_by_name($myIP);
        $myCountryName = str_replace(array_keys($country), array_values($country), $myCountryName);
        foreach ($this->getPosts('office_id') as $post) {
            $countryCode = get_post_meta($post->ID, 'country_code', true);
            if (!empty($countryCode) && $myCountryCode == $countryCode) {
                // exact match on geoip country code
                return '<a href="' . $this->getLink($post, $linkType) . '">' . esc_html($post->post_title) . '</a>';
            }
            // otherwise try to match by text in the title or address
            $address = get_post_meta($post->ID, 'physical_address', true);
            if (empty($address)) {
                $address = get_post_meta($post->ID, 'postal_address', true);
            }
            if ($post->post_title == 'International Office') {
                $default = '<a href="' . $this->getLink($post, $linkType) . '">' . esc_html($post->post_title) . '</a>';
            }
            if (empty($address)) {
                // office does not want to be found
                continue;
            }
            if (strpos($address, $myCountryName) !== false || strpos($myCountryName, $post->post_title) !== false) {
                return '<a href="' . $this->getLink($post, $linkType) . '">' . esc_html($post->post_title) . '</a>';
            }
        }
        return $default;
    }

    /**
     * creates a google maps world map with all the offices marked on it
     * @return string html
     */
    public function renderShortcodeMap()
    {
        $apiKey = get_option('google_maps_api_key');
        if (empty($apiKey)) {
            return '<p>Configure a Google Maps API key in the Settings</p>';
        }

        wp_enqueue_script('jquery');
        $output      = '<div id="map_canvas" style="width: 100%; height: 400px"></div>' . NEWLINE;
        $output      .= '<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=' . $apiKey . '&sensor=false"></script>' . NEWLINE;
        $output      .= '<script type="text/javascript">' . NEWLINE;
        $output      .= 'function initializeGoogleMaps() {' . NEWLINE;
        $output      .= '    var mapOptions = { zoom: 1, center: new google.maps.LatLng(3.107, 101.647), mapTypeId: google.maps.MapTypeId.ROADMAP };' . NEWLINE;
        $output      .= '    var map = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);' . NEWLINE;
        $output      .= '    var bounds = new google.maps.LatLngBounds();' . NEWLINE;
        $markerCount = 0;
        foreach ($this->getPosts('office_id') as $post) {
            $latitude  = get_post_meta($post->ID, 'latitude', true);
            $longitude = get_post_meta($post->ID, 'longitude', true);
            if (!empty($latitude) && $latitude != self::INVALID_LOCATION) {
                $output .= '    var location' . $markerCount . ' = new google.maps.LatLng(' . $latitude . ',' . $longitude . ')' . NEWLINE;
                $output .= '    var marker' . $markerCount
                    . ' = new google.maps.Marker({position: location' . $markerCount
                    . ', map: map, title: "' . get_the_title($post->ID)
                    . '", url:"' . get_post_permalink($post->ID) . '" });'
                    . NEWLINE;
                $output .= '    bounds.extend( location' . $markerCount . ' );' . NEWLINE;
                $output .= '    google.maps.event.addListener( marker' . $markerCount
                    . ', "click", function() { window.location.href = this.url; } );' . NEWLINE;
                $markerCount++;
            }
        }
        $output .= '    map.fitBounds( bounds );' . NEWLINE;
        $output .= '}</script>' . NEWLINE;
        add_action(
            'wp_footer',
            function () {
                print '<script type="text/javascript">jQuery(document).ready(initializeGoogleMaps);</script>';
            },
            self::PRIORITY_AFTER_JQUERY
        );
        return $output;
    }

    /**
     * Call Google Maps Geocoder to convert an address to a lat/long
     * @param string $address to geocode
     * @throws \Exception
     * @return array latitude, longitude
     */
    public function geocode($address)
    {
        if (empty($address)) {
            // return dummy values to stop the geocoder from running again
            return ['latitude' => self::INVALID_LOCATION, 'longitude' => self::INVALID_LOCATION, 'country_code' => ''];
        }
        $apiKey = get_option('google_maps_api_key');
        if (empty($apiKey)) {
            // an api key is required for the geocoding to work
            return ['latitude' => self::INVALID_LOCATION, 'longitude' => self::INVALID_LOCATION, 'country_code' => ''];
        }

        $url      = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&sensor=false&key=' . $apiKey;
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            throw new \Exception('Google Geocode ' . $response->get_error_message());
        }
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($json) || $json['status'] == 'ZERO_RESULTS') {
            // return dummy values to stop the geocoder from running again
            return ['latitude' => self::INVALID_LOCATION, 'longitude' => self::INVALID_LOCATION, 'country_code' => ''];
        }

        if (!isset($json['results'][0]['geometry']['location'])) {
            //throw new \Exception('Google Geocode: malformed results');
            return ['latitude' => self::INVALID_LOCATION, 'longitude' => self::INVALID_LOCATION, 'country_code' => ''];
        }
        if (!empty($json['results'][0]['address_components'])) {
            $countryCode = $this->findCountryCode($json['results'][0]['address_components']);
        } else {
            $countryCode = '';
        }
        return [
            'country_code' => $countryCode,
            'latitude'     => $json['results'][0]['geometry']['location']['lat'],
            'longitude'    => $json['results'][0]['geometry']['location']['lng'],
        ];
    }

    /**
     * @param string $content
     * @return string: for a search result or similar
     */
    public function getExcerpt($content = '')
    {
        $output = [];
        $postID = get_the_ID();
        $value  = get_post_meta($postID, 'phone', true);
        if (!empty($value)) {
            $output[] = '<td>Phone:</td><td><a href="callto:' . esc_html($value) . '" class="tel">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'web_site', true);
        if (!empty($value)) {
            $output[] = '<td>Web:</td><td><a href="' . esc_html($value) . '" class="url">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'email', true);
        if (!empty($value)) {
            $output[] = '<td>Email:</td><td><a href="mailto:' . esc_html($value) . '" class="email">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'physical_address', true);
        if (!empty($value)) {
            $output[] = '<td>Address:</td><td class="adr">' . esc_html($value) . '</td>';
        }
        if (empty($output)) {
            return $content;
        }
        return '<table class="isdata_contact_meta"><tr>' . join('</tr><tr>', $output) . '</tr></table>' . $content;
    }

    /**
     * @return string
     */
    public function getContentPrefix()
    {
        if (is_search()) {
            return '';
        }
        if (is_archive()) {
            return $this->getExcerpt('');
        }

        $output = [];
        $postID = get_the_ID();
        $value  = get_post_meta($postID, 'phone', true);
        if (!empty($value)) {
            $output[] = '<td>Phone</td><td><a href="callto:' . esc_html($value) . '" class="tel">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'fax', true);
        if (!empty($value)) {
            $output[] = '<td>Fax</td><td class="tel">' . esc_html($value) . '</td>';
        }
        $value = get_post_meta($postID, 'web_site', true);
        if (!empty($value)) {
            $output[] = '<td>Web</td><td><a href="' . esc_html($value) . '" class="url">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'email', true);
        if (!empty($value)) {
            $output[] = '<td>Email</td><td><a href="mailto:' . esc_html($value) . '" class="email">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'twitter', true);
        if (!empty($value)) {
            $output[] = '<td>Twitter</td><td><a href="' . esc_html($value) . '" class="url">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'facebook', true);
        if (!empty($value)) {
            $output[] = '<td>Facebook</td><td><a href="' . esc_html($value) . '" class="url">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'linkedin', true);
        if (!empty($value)) {
            $output[] = '<td>LinkedIn</td><td><a href="' . esc_html($value) . '" class="url">' . esc_html($value) . '</a></td>';
        }
        $value = get_post_meta($postID, 'physical_address', true);
        if (!empty($value)) {
            $output[] = '<td>Physical Address</td><td class="adr">' . esc_html($value) . '</td>';
        }
        $value = get_post_meta($postID, 'postal_address', true);
        if (!empty($value)) {
            $output[] = '<td>Postal Address</td><td class="adr">' . esc_html($value) . '</td>';
        }
        if (empty($output)) {
            return '';
        }
        return '<table class="isdata_contact_meta"><tr>' . join('</tr><tr>', $output) . '</tr></table>';
    }

    /**
     * @return string
     */
    public function getContentSuffix()
    {
        if (is_archive()) {
            return '';
        }

        $postID    = get_the_ID();
        $latitude  = get_post_meta($postID, 'latitude', true);
        $longitude = get_post_meta($postID, 'longitude', true);
        $apiKey    = get_option('google_maps_api_key');
        if (empty($apiKey) || empty($latitude) || $latitude == self::INVALID_LOCATION) {
            return '';
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('googlemaps', 'https://maps.googleapis.com/maps/api/js?key=' . $apiKey . '&sensor=false');
        $postTitle = get_the_title($postID);
        $output    = <<< EOS
        <div id="map_canvas$postID" style="width: 100%; height: 300px"></div>
        <script type="text/javascript">
        function initializeGoogleMaps$postID()
        {
            var location = new google.maps.LatLng( $latitude, $longitude );
            var mapOptions = {
                zoom: 12,
                center: location,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            };
            var map = new google.maps.Map(document.getElementById("map_canvas$postID"), mapOptions);
            var marker = new google.maps.Marker({position: location, map: map, title: "$postTitle"});
        }
        </script>
EOS;
        add_action(
            'wp_footer',
            function () use ($postID) {
                print '<script type="text/javascript">jQuery(document).ready(initializeGoogleMaps' . $postID . ');</script>';
            },
            self::PRIORITY_AFTER_JQUERY
        );
        return $output;
    }

    /**
     * get the users current IP address
     * see http://stackoverflow.com/a/2031935/117647
     * @return string
     */
    private function getIPAddress()
    {
        foreach ([
                     'HTTP_CLIENT_IP',
                     'HTTP_X_FORWARDED_FOR',
                     'HTTP_X_FORWARDED',
                     'HTTP_X_CLUSTER_CLIENT_IP',
                     'HTTP_FORWARDED_FOR',
                     'HTTP_FORWARDED',
                     'REMOTE_ADDR',
                 ] as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                    ) {
                        return $ip;
                    }
                }
            }
        }
        return '';
    }

    /**
     * extract 2 letter country code from google geocoding
     * @param array $addressComponents
     * @return string
     */
    private function findCountryCode($addressComponents)
    {
        foreach ($addressComponents as $component) {
            if (isset($component['types']) &&
                in_array('country', $component['types']) && isset($component['short_name'])
            ) {
                return $component['short_name'];
            }
        }
        return '';
    }

    private function getLink($post, $linkType)
    {
        $link = '';
        if ($linkType == 'donate') {
            $link = get_post_meta($post->ID, 'donate_link', true);
        } else if ($linkType == 'direct') {
            $link = get_post_meta($post->ID, 'web_site', true);
        }
        if (empty($link)) {
            $link = get_post_permalink($post->ID);
        }
        return $link;
    }
}
