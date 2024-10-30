<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\PostType\Contact
 * Tests for \ISData\PostType\Contact
 */

namespace ISData\PostType;

require_once dirname(__DIR__) . '/FeedMock.php';
require_once ISDATA_PATH . 'PostType/Contact.php';

/**
 * tests
 */
class ContactTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @group slow
     */
    public function testUpdate()
    {
        $feed = new \ISData\FeedMock();
        $subject = new \ISData\PostType\Contact();
        $subject->setFeed($feed);
        $officeCount = $subject->update();
        $this->assertGreaterThan(0, $officeCount, 'should load a number of offices');

        $existing = $subject->getExisting('office_id');
        $this->assertEquals(count($existing), $officeCount, 'should not duplicate offices');

        // should delete missing offices and add new ones
    }
}
