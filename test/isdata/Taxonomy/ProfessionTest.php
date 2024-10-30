<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\Taxonomy\Profession
 * Tests for \ISData\Taxonomy\Profession
 */

namespace ISData\Taxonomy;

require_once dirname(__DIR__) . '/FeedMock.php';
require_once ISDATA_PATH . 'Taxonomy/Profession.php';

/**
 * tests
 */
class ProfessionTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @group slow
     */
    public function testUpdate()
    {
        $feed = new \ISData\FeedMock();
        $subject = new \ISData\Taxonomy\Profession();
        $subject->setFeed($feed);
        $itemCount = $subject->update();
        $this->assertGreaterThan(0, $itemCount, 'should load');

        $existing = $subject->getExisting();
        $this->assertEquals(count($existing), $itemCount, 'should not duplicate');

        // should delete missing offices and add new ones
    }
}
