<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\PostType\Job
 */

namespace ISData\PostType;

if (class_exists('Job')) {
    return;
}

require_once __DIR__ . '/CustomPostType.php';

/**
 * Job details
 */
class Job extends CustomPostType
{
    const SHOW_UI = false;
    const TIME_LIMIT_SECONDS = 120;

    protected $taxonomies = [
        'location'   => 'isdata_location',
        'profession' => 'isdata_profession',
        'duration'   => 'isdata_duration',
    ];

    /**
     */
    public function __construct()
    {
        parent::__construct('job');
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
                        'name'          => __('Job'),
                        'singular_name' => __('Job'),
                    ],
                    'description'  => __('Job Openings'),
                    'supports'     => ['title', 'editor'],
                    'public'       => true,
                    'has_archive'  => true,
                    'hierarchical' => false,
                    'rewrite'      => ['slug' => __('job')],
                    'show_ui'      => self::SHOW_UI,
                    'query_var'    => 'job',
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

        $wordpressJobs = array_flip($this->getExisting('job_id')); // name => postID
        $updatedJobs   = []; // contains post IDs of updated posts
        foreach ($fromFeed as $job) {
            $postID = isset($wordpressJobs[$job['id']]) ? $wordpressJobs[$job['id']] : 0;
            $this->updateJob($postID, $job);
            $updatedJobs[] = $postID;
        }

        // remove invalid jobs
        $toDelete = array_diff($wordpressJobs, array_filter($updatedJobs));
        foreach ($toDelete as $postID) {
            $this->deleteJob($postID);
        }
        return count($fromFeed);
    }

    /**
     * sync the job info from the feed with new incoming data
     * @param int   $postID 0 for new post, otherwise wordpress post id
     * @param array $job    info from the feed about the job
     * @throws \Exception if unable to add / update
     * @return int postID
     */
    public function updateJob($postID, array $job)
    {
        $time = $job['last_modified']; // not strictly true: it may have been created for some time
        $args = [
            self::WP_TITLE_FIELD     => $job['title'],
            self::WP_CONTENT_FIELD   => $job['description'],
            self::WP_POST_TYPE_FIELD => $this->getWordpressName(),
            'post_name'              => $job['id'] . '-' . sanitize_title($job['title']) . '-job', // seo
            'post_status'            => 'publish',
            'comment_status'         => 'closed', // disallow comments
            'ping_status'            => 'closed', // disallow pings
            'post_date'              => $time,
            'post_date_gmt'          => $time,
            'post_modified'          => $time,
            'post_modified_gmt'      => $time,
        ];

        if ($postID == 0) {
            $postID = wp_insert_post($args, true);
            if (is_wp_error($postID)) {
                throw new \Exception('Unable to add job: ' . join('', $postID->get_error_messages()));
            }
        } else {
            $args[self::WP_ID_FIELD] = $postID;
            $updatedPostID           = wp_update_post($args);
            if ($updatedPostID != $postID) {
                throw new \Exception('Unable to update job: ' . $job['id']);
            }
        }

        update_post_meta($postID, 'is_priority', $job['is_priority'] > 0 ? 1 : 0);
        update_post_meta($postID, 'is_salaried', $job['is_salaried'] > 0 ? 1 : 0);
        update_post_meta($postID, 'job_id', $job['id']);
        wp_set_post_terms($postID, $this->getProfessions($job['profession_id']), 'isdata_profession');
        wp_set_post_terms($postID, $this->getLocations($job['country_id']), 'isdata_location');
        wp_set_post_terms($postID, $this->getDurations(explode(',', $job['duration_ids'])), 'isdata_duration');

        return $postID;
    }

    /**
     * @param int $postID
     */
    public function deleteJob($postID)
    {
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
        $sql .= ', ' . $wpdb->postmeta . ' postmeta';
        $sql .= ' WHERE ';
        $sql .= 'post.post_status = "publish" ';
        $sql .= 'AND post.post_type = "' . $this->getWordpressName() . '" ';
        $sql .= 'AND post.ID = postmeta.post_id ';
        $sql .= 'AND postmeta.meta_key = "is_priority" ';
        if ($context->hasPostID()) {
            // exclude a post because it is already visible
            $sql .= 'AND post.ID <> ' . $context->getPostID() . ' ';
        }
        $sql .= 'ORDER BY score DESC, postmeta.meta_value DESC, post.post_date DESC ';
        $sql .= 'LIMIT ' . $context->getItemsPerPage();

        return $wpdb->get_results($sql, OBJECT);
    }

    /**
     * Get a list of posts exactly matching the context
     * @param \ISData\Context $context
     * @param int             $maxRecords
     * @return array of post objects
     */
    public function getExactPosts(\ISData\Context $context, $maxRecords = 0)
    {
        if ($context->hasSearchID()) {
            return $this->getExactPost('job', $context->getSearchID());
        }
        global $wpdb; // ick! wordpress global variable

        $sql = 'SELECT post.*';
        $sql .= 'FROM ' . $wpdb->posts . ' post';
        $sql .= ', ' . $wpdb->postmeta . ' postpriority';
        $sql .= ', ' . $wpdb->postmeta . ' postjob_id';
        $sql .= ' WHERE ';
        $sql .= 'post.post_status = "publish" ';
        $sql .= 'AND post.post_type = "' . $this->getWordpressName() . '" ';
        $sql .= 'AND post.ID = postpriority.post_id ';
        $sql .= 'AND postpriority.meta_key = "is_priority" ';
        $sql .= 'AND post.ID = postjob_id.post_id ';
        $sql .= 'AND postjob_id.meta_key = "job_id" ';
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
                $sql .= 'AND postjob_id.meta_value = "' . intval($pattern) . '" ';
            } else {
                $pattern = $wpdb->_escape($wpdb->esc_like($pattern));
                $sql .= 'AND (post.post_title like "%' . $pattern . '%" or post.post_content like "%' . $pattern . '%") ';
            }
        }
        $sql .= 'ORDER BY postpriority.meta_value DESC, post.post_date DESC, post.post_title ';
//        if ($context->getItemsPerPage() > 0) {
//            $sql .= 'LIMIT ' . $context->getItemsPerPage();
//        }
        return $wpdb->get_results($sql, OBJECT);
        // tried to use WP_Query here but it is unable to sort by a meta_key first then post_date desc within that
    }

    /**
     * return a csv string for wp_dropdown_categories to restrict the terms displayed
     * @param string          $feed
     * @param \ISData\Context $context
     * @return array
     */
    public function getExcludedTaxonomyTerms($feed, \ISData\Context $context)
    {
        $included = $context->getTaxonomyTermIDs($feed);
        if (empty($included)) {
            return []; // nothing to exclude
        }
        $excluded   = [];
        $args       = [
            'type'         => 'post',
            'taxonomy'     => 'isdata_' . $feed,
            'hide_empty'   => 0,
            'orderby'      => 'id',
            'hierarchical' => true,
        ];
        $categories = get_categories($args);
        foreach ($categories as $category) {
            $id = $category->term_id;
            if (in_array($id, $included)) {
                continue;
            }
            $excluded[] = $id;
        }
        sort($excluded);
        return join(',', $excluded);
    }

    /**
     * @param \ISData\Context $context
     * @return string html
     */
    public function renderSearchForm(\ISData\Context $context)
    {
        // deliberately no action on the form, so the shortcode will work on any page
        $output = '';
        $output .= '<form role="search" method="get">';
        $output .= '<table>';
        foreach ($this->taxonomies as $feed => $taxonomy) {
            $args = [
                'id'              => $feed,
                'name'            => $feed,
                'orderby'         => 'name',
                'echo'            => false,
                'taxonomy'        => 'isdata_' . $feed,
                'hierarchical'    => true,
                'show_option_all' => __('Any'),
                'selected'        => $context->getTaxonomyTermSelected($feed),
                'exclude'         => $this->getExcludedTaxonomyTerms($feed, $context),
            ];
            $output .= '<tr><td><label for="' . $feed . '">' . __(ucfirst($feed))
                . '</label></td><td>' . wp_dropdown_categories($args) . '</td></tr>';
        }
        $output .= '<tr><td><label for="search_id">' . __('Job ID') . '</label></td>';
        $output .= '<td><input type="text" value="' . ($context->getSearchID())
            . '" name="search_id" id="search_id"></td></tr>';

        $output .= '<tr><td><label for="search_text">' . __('Text') . '</label></td>';
        $output .= '<td><input type="text" value="' . esc_attr($context->getSearchText())
            . '" name="search_text" id="search_text" placeholder="' . __('Job Title / Organisation') . '"></td></tr>';

        $output .= '<tr><td></td><td><input type="submit" value="Search"></td></tr>';
        $output .= '</table>';
        $output .= '</form>';
        return $output;
    }

    /**
     * @param string $content
     * @return string: for a search result or similar
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
        $output = '';
        $output .= '<p class="isdata_job_meta">';
        $output .= join(', ', $items) . ' / ';
        $output .= '<strong>' . __('Job ID') . '</strong>: '
            . esc_html(get_post_meta($postID, 'job_id', true));
        $output .= '</p>';
        return $output . $content;
    }

    /**
     * @return string
     */
    public function getContentPrefix()
    {
        $output = '';
        if (is_search()) {
        } elseif (is_archive()) {
            $output .= $this->getExcerpt('');
        } else {
            $postID = get_the_ID();
            $output .= '<table class="isdata_job_meta">';
            foreach ($this->taxonomies as $feed => $taxonomy) {
                $terms = wp_get_post_terms($postID, $taxonomy);
                if (empty($terms)) {
                    continue;
                }
                $items = [];
                foreach ($terms as $term) {
                    $items[] = '<a href="' . get_term_link($term) . '">' . esc_html($term->name) . '</a>';
                }
                $output .= '<tr><td>' . __(ucfirst($feed)) . '</td><td>' . join(', ', $items) . '</td></tr>';
            }
            $output .= '<tr><td>' . __('Salaried') . '</td><td>'
                . (get_post_meta($postID, 'is_salaried', true) > 0 ? __('Yes') : __('No')) . '</td></tr>';
            $output .= '<tr><td>' . __('Priority') . '</td><td>'
                . (get_post_meta($postID, 'is_priority', true) > 0 ? __('Yes') : __('No')) . '</td></tr>';
            $output .= '<tr><td>' . __('Job ID') . '</td><td>'
                . esc_html(get_post_meta($postID, 'job_id', true)) . '</td></tr>';
            $output .= '<tr><td>' . __('Date') . '</td><td>' . get_the_date() . '</td></tr>';
            $output .= '</table>';
        }
        return $output;
    }
}
