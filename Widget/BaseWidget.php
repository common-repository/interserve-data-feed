<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Widget\BaseWidget
 */

namespace ISData\Widget;

if (class_exists('BaseWidget')) {
    return;
}

/**
 * wordpress widget to display jobs / stories etc in sidebar
 */
abstract class BaseWidget extends \WP_Widget
{

    /**
     * @return \ISData\PostType\CustomPostType
     */
    abstract public function getPostType();

    /**
     * @param array $args
     * @return \ISData\Context
     */
    public function getContext($args = [])
    {
        return \ISData\Manager::getContext($args);
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
        $plugin  = $this->getPostType();
        $context = $this->getContext($instance);
        $posts   = $plugin->getRelatedPosts($context);
        print '<ul class="' . $plugin->getWordpressName() . '_widget">';
        foreach ($posts as $post) {
            print '<li><a href="' . get_permalink($post) . '">' . esc_html($post->post_title) . '</a>';
            if (!empty($instance['show_location'])) {
                $terms = wp_get_post_terms($post->ID, 'isdata_location');
                if (!empty($terms)) {
                    $items = [];
                    foreach ($terms as $term) {
                        $items[] = esc_html($term->name);
                    }
                    print ' in ' . join(', ', $items);
                }
            }
            print '</li>' . NEWLINE;
        }
        print '</ul>';
        print '<p><a href="/' . $plugin->getFeedName() . '">' . __('More...') . '</a></p>';

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
        $instance                  = [];
        $instance['title']         = strip_tags($new_instance['title']);
        $instance['n']             = intval($new_instance['n']);
        $instance['show_location'] = $new_instance['show_location'] > 0 ? 1 : 0;
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
        if (!isset($instance['n'])) {
            $instance['n'] = 5;
        }
        if (empty($instance['show_location'])) {
            $instance['show_location'] = 0;
        }

        print '<p>';
        print '<label for="' . $this->get_field_id('title') . '">' . __('Title:') . '</label>';
        print '<input class="widefat" id="' . $this->get_field_id('title')
            . '" name="' . $this->get_field_name('title')
            . '" type="text" '
            . 'value="' . esc_attr($instance['title']) . '">';
        print '</p>' . NEWLINE;
        print '<p>';
        print '<label for="' . $this->get_field_id('n') . '">' . __('Display:') . '</label> ';
        print '<select id="' . $this->get_field_id('n')
            . '" name="' . $this->get_field_name('n') . '">';
        for ($n = 1; $n < \ISData\Context::MAX_ITEMS_PER_PAGE; $n++) {
            print '<option ' . selected($instance['n'], $n, false) . '>' . $n . '</option>';
        }
        print '</select> items';
        print '</p>' . NEWLINE;
        print '<p>';
        print '<label for="' . $this->get_field_id('show_location') . '">' . __('Location:') . '</label> ';
        print '<input id="' . $this->get_field_id('show_location')
            . '_0" name="' . $this->get_field_name('show_location')
            . '" type="radio" ' . checked($instance['show_location'] == 0, true, false)
            . ' value="0">Hide ';
        print '<input id="' . $this->get_field_id('show_location')
            . '_1" name="' . $this->get_field_name('show_location')
            . '" type="radio" ' . checked($instance['show_location'] == 1, true, false)
            . ' value="1">Show ';
        print '</p>' . NEWLINE;
    }
}
