<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\Data\Statistics
 * Tests for \ISData\Data\Statistics
 */

namespace ISData\Data;

require_once dirname(__DIR__) . '/FeedMock.php';
require_once ISDATA_PATH . 'Data/Statistics.php';

/**
 * tests
 */
class StatisticsTest extends \PHPUnit_Framework_TestCase
{

    /**
     */
    public function testRenderShortcode()
    {
        $feed = new \ISData\FeedMock();
        $subject = new \ISData\Data\Statistics();
        $subject->setFeed($feed);
        $subject->setData(null);
        $this->assertRegExp('|no data|i', $subject->renderShortcode());

        $items = array( 'Title' => 'value', 'Second' => 222 );
        $subject->setData($items);
        $result = $subject->renderShortcode();
        $this->assertRegExp('|Title|', $result);
        $this->assertRegExp('|value|', $result);
        $this->assertRegExp('|Second|', $result);
        $this->assertRegExp('|222|', $result);
    }



    /**
     * @group slow
     */
    public function testUpdate()
    {
        $feed = new \ISData\FeedMock();
        $subject = new \ISData\Data\Statistics();
        $subject->setFeed($feed);
        $subject->setData(array());
        $subject->update();
        $data = $subject->getData();
        $this->assertTrue(is_array($data));
        $this->assertArrayHasKey('Updated', $data);
        $time = strtotime($data['Updated']);
        $this->assertTrue($time > time() - TWENTY_FOUR_HOURS * 7, $data['Updated']); // should have been updated recently
    }
}
