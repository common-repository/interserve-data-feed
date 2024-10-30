<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Widget\JobWidget
 */

namespace ISData\Widget;

if (class_exists('JobWidget')) {
    return;
}

require_once __DIR__ . '/BaseWidget.php';

/**
 * wordpress widget to display job opening titles in the sidebar
 */
class JobWidget extends BaseWidget
{
    /**
     * Register widget with WordPress.
     */
    public function __construct()
    {
        parent::__construct(
            'isdata_JobWidget', // Base ID
            __('Job Openings'), // Name
            ['description' => __('List of related Interserve Job Openings'),] // Args
        );
    }

    public function getPostType()
    {
        global $isDataManager;
        return $isDataManager->getPostType(\ISData\Manager::POST_TYPE_JOB);
    }
}
