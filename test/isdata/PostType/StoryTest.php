<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\PostType\Story
 * Tests for \ISData\PostType\Story
 */

namespace ISData\PostType;

require_once dirname(__DIR__) . '/FeedMock.php';
require_once ISDATA_PATH . 'PostType/Story.php';

/**
 * tests
 */
class StoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @group slow
     */
    public function testUpdate()
    {
        $feed = new \ISData\FeedMock();
        $subject = new \ISData\PostType\Story();
        $subject->setFeed($feed);
        $itemCount = $subject->update();
        $this->assertGreaterThan(0, $itemCount, 'should load');

        $existing = $subject->getExisting('story_id');
        $this->assertEquals(count($existing), $itemCount, 'should not duplicate');
    }
}
