<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Feed
 */

namespace ISData;

if (class_exists('Feed')) {
    return;
}

/**
 * data feed wrapper.
 * handles caching, calling the api etc.
 * allows us to mock the feed for unit tests
 */
class Feed
{
    private $baseUrl = 'https://data.interserve.org/v3/';
    private $timeout = 10; // seconds

    /**
     * gets data from the feed
     * @param string $endpoint the sub url
     * @return array
     * @throws \Exception
     */
    public function getData($endpoint)
    {
        $url      = $this->baseUrl . $endpoint . '?format=json';
        $options  = [
            'timeout'     => $this->timeout,
//            'sslverify'   => false,
            // work around bug where certificate was not recognised
//            'httpversion' => '1.1'
            // work around bug where curl error 28 was preventing longer responses from completing
        ];
        $response = wp_remote_get($url, $options);
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            throw new \Exception('Feed::getData(' . $endpoint . ') ' . $error_message);
        }
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        if (empty($result) && !is_array($result)) {
            throw new \Exception('Invalid json response returned: ' . substr($body, 0, 200));
        }
        return $result;
    }
}
