<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\ThemeTaxonomy
 * Tests for \ISData\ThemeTaxonomy
 */

namespace ISData\Taxonomy;

require_once dirname(__DIR__) . '/FeedMock.php';
require_once ISDATA_PATH . 'Taxonomy/Theme.php';

/**
 * tests
 */
class ThemeTest extends \PHPUnit_Framework_TestCase
{

    /**
     */
    public function setUp()
    {
        $subject = new \ISData\Taxonomy\Theme();
        $subject->saveTaxonomyTermMap(array());
    }



    /**
     * @group slow
     */
    public function testUpdate()
    {
        $feed = new \ISData\FeedMock();
        $subject = new \ISData\Taxonomy\Theme();
        $subject->setFeed($feed);
        $itemCount = $subject->update();
        $this->assertGreaterThan(0, $itemCount, 'should load');

        $existing = $subject->getExisting();
        $this->assertEquals(count($existing), $itemCount, 'should not duplicate');

        $map = $subject->getTaxonomyTermMap($subject->getWordpressName());
        $this->assertEquals(count($map), count($existing), 'should not duplicate');
        // should delete missing offices and add new ones
    }
}
