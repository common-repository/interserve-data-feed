<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Widget\ChildPagesWidget
 */

namespace ISData\Widget;

if (class_exists('ChildPagesWidget')) {
    return;
}

/**
 * wordpress widget to display child pages
 */
class ChildPagesWidget extends \WP_Widget
{
    /**
     * Register widget with WordPress.
     */
    public function __construct()
    {
        parent::__construct(
            'isdata_ChildPagesWidget', // Base ID
            __('Child Pages'), // Name
            ['description' => __('List of child pages'),] // Args
        );
    }

    /**
     * Front-end display of widget.
     * @see WP_Widget::widget()
     * @param array $args     Widget arguments: before_title, after_title etc
     * @param array $instance Saved values from database.
     */
    public function widget($args, $instance)
    {
        print $args['before_widget'];

        $title = apply_filters('widget_title', $instance['title']);
        if (!empty($title)) {
            print $args['before_title'] . $title . $args['after_title'];
        }
//        print '<ul class="isdata_child_pages_widget">';
        global $isDataManager;
        print $isDataManager->childPages();
//        print '</ul>';
        print $args['after_widget'];
    }

    /**
     * Sanitize widget form values as they are saved.
     * @see WP_Widget::update()
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     * @return array Updated safe values to be saved.
     */
    public function update($new_instance, $old_instance)
    {
        $instance          = [];
        $instance['title'] = strip_tags($new_instance['title']);
        return $instance;
    }

    /**
     * Back-end widget form.
     * @see WP_Widget::form()
     * @param array $instance Previously saved values from database.
     * @return string|void
     */
    public function form($instance)
    {
        if (!isset($instance['title'])) {
            $instance['title'] = '';
        }
        print '<p>';
        print '<label for="' . $this->get_field_id('title') . '">' . __('Title:') . '</label>';
        print '<input class="widefat" id="' . $this->get_field_id('title')
            . '" name="' . $this->get_field_name('title')
            . '" type="text" '
            . 'value="' . esc_attr($instance['title']) . '">';
        print '</p>' . NEWLINE;
    }
}
