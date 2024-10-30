<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\Statistics
 * Tests for \ISData\Statistics
 */

namespace ISData;
require_once ISDATA_PATH . 'Feed.php';

/**
 * tests
 */
class FeedTest extends \PHPUnit_Framework_TestCase
{

    /**
     * pulls one feed to check the http api is working
     * @group slow
     */
    public function testGetData()
    {
        $feed = new Feed();
        $data = $feed->getData('statistics');
        $this->assertTrue(is_array($data));
        $this->assertTrue(is_array($data[0]));
        $this->assertArrayHasKey('title', $data[0]);
        $this->assertArrayHasKey('value', $data[0]);
        $this->assertEquals($data[0]['title'], 'Years');
        $this->assertGreaterThanOrEqual($data[0]['value'], 161);
    }
}
