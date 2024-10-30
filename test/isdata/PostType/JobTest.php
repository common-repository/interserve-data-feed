<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\PostType\Job
 * Tests for \ISData\PostType\Job
 */

namespace ISData\PostType;

require_once dirname(__DIR__) . '/FeedMock.php';
require_once ISDATA_PATH . 'PostType/Job.php';

/**
 * tests
 */
class JobTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @group slow
     */
    public function testUpdate()
    {
        $feed = new \ISData\FeedMock();
        $subject = new \ISData\PostType\Job();
        $subject->setFeed($feed);
        $itemCount = $subject->update();
        $this->assertGreaterThan(0, $itemCount, 'should load');

        $existing = $subject->getExisting('job_id');
        $this->assertEquals(count($existing), $itemCount, 'should not duplicate');
    }
}
