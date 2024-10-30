<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\LocationTaxonomy
 * Tests for \ISData\LocationTaxonomy
 */

namespace ISData\Taxonomy;

require_once dirname(__DIR__) . '/FeedMock.php';
require_once ISDATA_PATH . 'Taxonomy/Location.php';

/**
 * tests
 */
class LocationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @group slow
     */
    public function testUpdate()
    {
        $feed = new \ISData\FeedMock();
        $subject = new \ISData\Taxonomy\Location();
        $subject->setFeed($feed);
        $itemCount = $subject->update();
        $this->assertGreaterThan(0, $itemCount, 'should load');

        $parents = get_terms($subject->getWordpressName(), array( 'hide_empty' => 0, 'parent' => 0 ));
        $this->assertEquals(count($parents), 5, '5 regions');

        $all = get_terms($subject->getWordpressName(), array( 'hide_empty' => 0, 'get' => 'all' ));
        $this->assertEquals(count($all), $itemCount);
    }
}
