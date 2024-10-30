<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\Story
 * Tests for \ISData\Story
 */

namespace ISData;

require_once ISDATA_PATH . 'Feed.php';

/**
 * make a local copy of the
 */
class FeedMock extends \ISData\Feed
{
    private $path;

    /**
     * @var bool set this true to grab a fresh copy from the live server.
     * May cause some tests to break that rely on specific values existing
     */
    private $forceUpdate = false;



    public function __construct()
    {
        $this->path = __DIR__ . '/var/';
    }



    public function getData($endpoint)
    {
        $fileName = $this->path . $endpoint . '.txt';
        if (!file_exists($fileName) || $this->forceUpdate) {
            $data = parent::getData($endpoint);
            file_put_contents($fileName, json_encode($data));
            return $data;
        }
        $data = file_get_contents($fileName);
        return json_decode($data, true);
    }
}
