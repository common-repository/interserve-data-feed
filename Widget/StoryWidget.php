<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Widget\StoryWidget
 */

namespace ISData\Widget;

if (class_exists('StoryWidget')) {
    return;
}

require_once __DIR__ . '/BaseWidget.php';

/**
 * wordpress widget to display Story opening titles in the sidebar
 */
class StoryWidget extends BaseWidget
{
    /**
     * Register widget with WordPress.
     */
    public function __construct()
    {
        parent::__construct(
            'isdata_StoryWidget', // Base ID
            __('Stories'), // Name
            ['description' => __('List of related Interserve Stories'),] // Args
        );
    }

    public function getPostType()
    {
        global $isDataManager;
        return $isDataManager->getPostType(\ISData\Manager::POST_TYPE_STORY);
    }
}
