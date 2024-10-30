<?php
/**
 * @file
 * @author Steve Pavarno
 * @test \ISData\Plugin
 * Tests for \ISData\Plugin
 */

namespace ISData;

require_once ISDATA_PATH . 'Manager.php';

class ManagerTest extends \PHPUnit_Framework_TestCase
{

    /**
     */
    public function testGetPlugin()
    {
        $subject = new Manager();

        $plugin = $subject->getPostType($subject::DATA_STATISTICS);
        $plugin2 = $subject->getPostType($subject::DATA_STATISTICS);
        $this->assertEquals($plugin, $plugin2, 'cached: so should be identical instances');
    }
}
