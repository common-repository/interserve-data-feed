<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\PostType\Story
 */

namespace ISData\PostType;

if (class_exists('Story')) {
    return;
}

require_once __DIR__ . '/CustomPostType.php';

/**
 * Story details.
 */
class Story extends CustomPostType
{
    const SHOW_UI = false;
    const TIME_LIMIT_SECONDS = 360; // because the images take a long time

    protected $taxonomies = [
        'profession' => 'isdata_profession',
        'location'   => 'isdata_location',
        'theme'      => 'isdata_theme',
    ];

    private $locations; // map of string term_name => term_id for isdata_location
    private $themes; // map of string term_name => term_id for isdata_theme

    /**
     */
    public function __construct()
    {
        parent::__construct('story');
    }

    /**
     * plugin initialisation
     */
    public function init()
    {
        foreach ($this->taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                throw new \Exception('Taxonomy does not exist: ' . $taxonomy);
            }
        }

        if (!post_type_exists($this->getWordpressName())) {
            // see http://codex.wordpress.org/Function_Reference/register_post_type
            register_post_type(
                $this->getWordpressName(),
                [
                    'labels'       => [
                        'name'          => __('Story'),
                        'singular_name' => __('Story'),
                    ],
                    'description'  => __('Stories'),
                    'supports'     => ['title', 'editor'],
                    'public'       => true,
                    'has_archive'  => true,
                    'hierarchical' => false,
                    'rewrite'      => ['slug' => __('story')],
                    'show_ui'      => self::SHOW_UI,
                    'query_var'    => 'story',
                    'can_export'   => false, // because the data is imported from elsewhere
                    'taxonomies'   => $this->taxonomies,
                ]
            );
        }
        parent::init();
    }

    /**
     * pull data from the Feed and store it within wordpress
     */
    public function update()
    {
        $fromFeed = $this->feed->getData($this->getFeedName());
        if (empty($fromFeed)) {
            return 0;
        }

        set_time_limit(self::TIME_LIMIT_SECONDS);

        $wordpressStories = array_flip($this->getExisting('story_id')); // name => postID
        $updatedStories   = []; // contains post IDs of updated posts
        foreach ($fromFeed as $story) {
            $postID = isset($wordpressStories[$story['id']]) ? $wordpressStories[$story['id']] : 0;
            $this->updateStory($postID, $story);
            $updatedStories[] = $postID;
        }

        // remove invalid storys
        $toDelete = array_diff($wordpressStories, array_filter($updatedStories));
        foreach ($toDelete as $postID) {
            $this->deleteStory($postID);
        }
        return count($fromFeed);
    }

    /**
     * sync the story info from the feed with new incoming data
     * @param int   $postID 0 for new post, otherwise wordpress post id
     * @param array $story  info from the feed about the story
     * @throws \Exception if unable to add / update
     * @return int postID
     */
    public function updateStory($postID, array $story)
    {
        $args = [
            self::WP_TITLE_FIELD     => $story['title'],
            self::WP_EXCERPT_FIELD   => $story['description'],
            self::WP_CONTENT_FIELD   => $story['content'],
            self::WP_POST_TYPE_FIELD => $this->getWordpressName(),
            'post_name'              => $story['id'] . '-' . sanitize_title($story['title']), // seo
            'post_status'            => 'publish',
            'comment_status'         => 'closed', // disallow comments
            'ping_status'            => 'closed', // disallow pings
            'post_date'              => $story['date_published'],
            'post_date_gmt'          => $story['date_published'],
            'post_modified'          => $story['last_modified'], // needed for syncing pdf and images
            'post_modified_gmt'      => $story['last_modified'],
        ];

        if ($postID == 0) {
            $postID = wp_insert_post($args, true);
            if (is_wp_error($postID)) {
                throw new \Exception('Unable to add story: ' . join('', $postID->get_error_messages()));
            }
        } else {
            $args[self::WP_ID_FIELD] = $postID;
            $updatedPostID           = wp_update_post($args);
            if ($updatedPostID != $postID) {
                throw new \Exception('Unable to update story: ' . $story['id']);
            }
        }
        update_post_meta($postID, 'story_id', $story['id']);
        update_post_meta($postID, 'story_pdf', empty($story['pdf']) ? '' : $story['pdf']); // link to pdf version
        wp_set_post_terms($postID, $this->getProfessions($story['profession_id']), 'isdata_profession');
        wp_set_post_terms($postID, $this->getLocation($story['region_name']), 'isdata_location');
        wp_set_post_terms($postID, $this->getThemes($story['theme_ids']), 'isdata_theme');
        $this->updateMedia($postID, $story);

        return $postID;
    }

    /**
     * look up the wordpress location ID from the feed story.region_name
     * @param string $feedRegionName
     * @return array
     */
    public function getLocation($feedRegionName)
    {
        if (!isset($this->locations)) {
            $terms = get_terms('isdata_location', ['hide_empty' => false]);
            foreach ($terms as $term) {
                $this->locations[$term->name] = $term->term_id;
            }
        }
        return isset($this->locations[$feedRegionName]) ? [intval($this->locations[$feedRegionName])] : [];
    }

    /**
     * check if the jpg image is present and up to date.
     * http://codex.wordpress.org/Function_Reference/media_handle_sideload
     * @param int   $postID
     * @param array $story
     * @throws \Exception
     */
    public function updateMedia($postID, array $story)
    {
        if (empty($story['image_modified']) || empty($story['image'])) {
            // no image to upload
            $this->deleteMedia($postID);
            return;
        }
        $modified = get_post_meta($postID, 'story_image_modified', true);
        if (!empty($modified) && $modified >= $story['image_modified']) {
            // image is up to date: no need to download it again
            return;
        }

        // delete the old version
        $this->deleteMedia($postID);

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tempFile = download_url($story['image']);
        if (is_wp_error($tempFile)) {
            throw new \Exception(
                'Unable to download ' . $story['image']
                . ' because ' . join('', $tempFile->get_error_messages())
            );
        }
        $file         = ['name' => basename($story['image']), 'tmp_name' => $tempFile];
        $post         = ['test_form' => false];
        $attachmentID = media_handle_sideload($file, $postID, 'Photo from story', $post);
        if (is_wp_error($attachmentID)) {
            @unlink($tempFile);
            throw new \Exception(
                'Unable to attach ' . $story['image']
                . ' because ' . join('', $attachmentID->get_error_messages())
            );
        }
        update_post_meta($postID, '_thumbnail_id', $attachmentID); // set it as the default thumbnail image
        update_post_meta($postID, 'story_image_modified', $story['image_modified']);
    }

    /**
     * delete media attachments
     * @param int $postID
     */
    public function deleteMedia($postID)
    {
        update_post_meta($postID, 'story_image_modified', 0);
        $args        = ['post_parent' => $postID, 'post_type' => 'attachment', 'post_mime_type' => 'image'];
        $attachments = get_children($args);
        if (empty($attachments)) {
            return;
        }
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
    }

    /**
     * delete all the media on a story
     */
    public function deleteAllMedia()
    {
        foreach ($this->getExisting('story_id') as $postID => $storyID) {
            $this->deleteMedia($postID);
        }
    }

    /**
     * @param int $postID
     */
    public function deleteStory($postID)
    {
        $this->deleteMedia($postID);
        wp_delete_post($postID, true);
    }

    /**
     * Get a list of related posts based on the context. A fuzzy match
     * @param \ISData\Context $context
     * @return array of post objects
     */
    public function getRelatedPosts(\ISData\Context $context)
    {
        global $wpdb; // ick! wordpress global variable

        // calculate a weighted score based on the number of matching context terms
        $scores = [];
        foreach ($context->getTaxonomyTerms() as $taxonomy => $values) {
            if (empty($values)) {
                continue;
            }
            $subQuery = $this->taxonomyTermSubQuery($taxonomy, $values);
            $scores[] = 'IF( post.ID in (' . $subQuery . '), 1, 0)';
        }

        $sql = 'SELECT post.*, ' . $this->getScoresSQL($scores);
        $sql .= ' FROM ' . $wpdb->posts . ' post';
        $sql .= ' WHERE ';
        $sql .= 'post.post_status = "publish" ';
        $sql .= 'AND post.post_type = "' . $this->getWordpressName() . '" ';
        if ($context->hasPostID()) {
            // exclude a post because it is already visible
            $sql .= 'AND post.ID <> ' . $context->getPostID() . ' ';
        }
        $sql .= 'ORDER BY score DESC, post.post_date DESC ';
        $sql .= 'LIMIT ' . $context->getItemsPerPage();

        return $wpdb->get_results($sql, OBJECT);
    }

    /**
     * Get a list of posts exactly matching the context
     * @param \ISData\Context $context
     * @return array of post objects
     */
    public function getExactPosts(\ISData\Context $context)
    {
        if ($context->hasSearchID()) {
            return $this->getExactPost('story', $context->getSearchID());
        }

        global $wpdb; // ick! wordpress global variable

        $sql = 'SELECT post.*';
        $sql .= 'FROM ' . $wpdb->posts . ' post';
        $sql .= ', ' . $wpdb->postmeta . ' poststory_id';
        $sql .= ' WHERE ';
        $sql .= 'post.post_status = "publish" ';
        $sql .= 'AND post.post_type = "' . $this->getWordpressName() . '" ';
        $sql .= 'AND post.ID = poststory_id.post_id ';
        $sql .= 'AND poststory_id.meta_key = "story_id" ';
        foreach ($context->getTaxonomyTerms() as $taxonomy => $values) {
            if (empty($values)) {
                continue;
            }
            $subQuery = $this->taxonomyTermSubQuery($taxonomy, $values);
            $sql .= 'AND post.ID in (' . $subQuery . ') ';
        }
        if ($context->hasSearchText()) {
            $pattern = $context->getSearchText();
            if (is_numeric($pattern)) {
                $sql .= 'AND poststory_id.meta_value = "' . intval($pattern) . '" ';
            } else {
                $pattern = $wpdb->_escape($wpdb->esc_like($pattern));
                $sql .= 'AND (post.post_title like "%' . $pattern . '%" or post.post_content like "%' . $pattern . '%") ';
            }
        }
        $sql .= 'ORDER BY post.post_date DESC, post.post_title ';
//        if ($context->getItemsPerPage() > 0) {
//            $sql .= 'LIMIT ' . $context->getItemsPerPage();
//        }
        return $wpdb->get_results($sql, OBJECT);
    }

    /**
     * @param \ISData\Context $context
     * @return string html
     */
    public function renderSearchForm(\ISData\Context $context)
    {
        $output = '';
        $output .= '<form role="search" method="get">'; // deliberately no action, so the shortcode will work on any page
        $output .= '<table>';
        foreach ($this->taxonomies as $feed => $taxonomy) {
            $args = [
                'id'              => $feed,
                'name'            => $feed,
                'orderby'         => 'name',
                'echo'            => false,
                'taxonomy'        => $taxonomy,
                'hierarchical'    => true,
                'show_option_all' => __('Any'),
                'selected'        => $context->getTaxonomyTermSelected($feed),
            ];
            $output .= '<tr><td><label for="' . $feed . '">' . __(ucfirst($feed))
                . '</label></td><td>' . wp_dropdown_categories($args) . '</td></tr>';
        }
        $output .= '<tr><td><label for="search_text">' . __('Text') . '</label></td>';
        $output .= '<td><input type="text" value="' . esc_attr($context->getSearchText())
            . '" name="search_text" id="search_text" placeholder="' . __('Title / Content') . '"></td></tr>';

        $output .= '<tr><td></td><td><input type="submit" value="Search"></td></tr>';
        $output .= '</table>';
        $output .= '</form>';
        return $output;
    }

    /**
     * @param string $content
     * @return string for a search result or similar
     */
    public function getExcerpt($content = '')
    {
        $postID = get_the_ID();
        $items = [];
        foreach ($this->taxonomies as $feed => $taxonomy) {
            $terms = wp_get_post_terms($postID, $taxonomy);
            if (empty($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $items[] = '<a href="' . get_term_link($term) . '">' . esc_html($term->name) . '</a>';
            }
        }
        $output = '<p class="' . $this->getWordpressName() . '_meta">' . join(', ', $items) . '</p>';
        return $output . $content;
    }

    /**
     * displayed at the top of the post
     * @return string
     */
    public function getContentPrefix()
    {
        if (is_search()) {
            return '';
        }

        if (is_archive()) {
            return $this->getExcerpt('');
        }

        $postID = get_the_ID();
        $output = '';
        $output .= '<table class="' . $this->getWordpressName() . '_meta">';
        foreach ($this->taxonomies as $feed => $taxonomy) {
            $terms = wp_get_post_terms($postID, $taxonomy);
            if (empty($terms)) {
                continue;
            }
            $items = [];
            foreach ($terms as $term) {
                $items[] = '<a href="' . get_term_link($term) . '">' . esc_html($term->name) . '</a>';
            }
            $output .= '<tr><td>' . ucfirst($feed) . '</td><td>' . join(', ', $items) . '</td></tr>';
        }
        $output .= '<tr><td>' . __('Date') . '</td><td>' . get_the_date() . '</td></tr>';
        $pdf = get_post_meta($postID, 'story_pdf', true);
        if (!empty($pdf)) {
            $output .= '<tr><td>' . __('PDF Version') . '</td><td><a href="' . $pdf . '">'
                . __('Download') . '</a></td></tr>';
        }
        $output .= '</table>';
        return $output;
    }
}
