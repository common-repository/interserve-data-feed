<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Context
 */

namespace ISData;

if (class_exists('Context')) {
    return;
}

/**
 * The context of a page request, which can be built up from the current page,
 * query arguments or other defaults.
 * A parameter object.
 */
class Context
{
    const DEFAULT_ITEMS_PER_PAGE = 10;
    const MAX_ITEMS_PER_PAGE = 50;

    /**
     * @var int currently visible post
     */
    private $postID = 0;

    /**
     * @var string user specified job/story/office id from the search.
     * which is different to the currently visible post.
     */
    private $searchID = '';

    /**
     * @var string user specified free text from search form
     */
    private $searchText = '';

    /**
     * @var int paginator current page number. 1 based index
     */
    private $pageNumber = 1;

    /**
     * @var int number of items to show on a page. the 'n' parameter in args
     */
    private $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE;

    /**
     * @var array list of taxonomy->termID
     */
    private $taxonomyTermID = [
        'location'   => [],
        'profession' => [],
        'duration'   => [],
        'theme'      => [],
    ];

    /**
     * @return int
     */
    public function getPostID()
    {
        return $this->postID;
    }

    /**
     * @return boolean
     */
    public function hasPostID()
    {
        return !empty($this->postID);
    }

    /**
     * @return string
     */
    public function getSearchID()
    {
        return $this->searchID;
    }

    /**
     * @return boolean
     */
    public function hasSearchID()
    {
        return !empty($this->searchID);
    }

    /**
     * @return string
     */
    public function getSearchText()
    {
        return $this->searchText;
    }

    /**
     * @return string
     */
    public function hasSearchText()
    {
        return !empty($this->searchText);
    }

    /**
     * @return int
     */
    public function getPageNumber()
    {
        return $this->pageNumber;
    }

    /**
     * @param int $page int
     */
    public function setPageNumber($page)
    {
        $this->pageNumber = $page;
    }

    /**
     * @return int
     */
    public function getItemsPerPage()
    {
        return $this->itemsPerPage;
    }

    /**
     * @return array
     */
    public function getTaxonomyTerms()
    {
        return $this->taxonomyTermID;
    }

    /**
     * @param        $term
     * @return array wordpress taxonomy termID
     */
    public function getTaxonomyTermIDs($term)
    {
        if (!isset($this->taxonomyTermID[$term])) {
            return [];
        }
        return $this->taxonomyTermID[$term];
    }

    /**
     * return one ("the selected") taxonomy term id
     * @param string $term
     * @return int wordpress taxonomy termID
     */
    public function getTaxonomyTermSelected($term)
    {
        $terms = $this->getTaxonomyTermIDs($term);
        if (empty($terms)) {
            return 0;
        }
        return current($terms);
    }

    /**
     * Fully populate the context
     * @param array $args from shortcode
     */
    public function calculate($args = [])
    {
        $this->calculateFromQuery();
        $this->calculateFromPost();
        $this->calculateFromArgs($args);
        $this->expandTaxonomyTerms();
    }

    /**
     * calculate parameters from the wordpress query / http request
     */
    public function calculateFromQuery()
    {
        // try calculating it from query params (if any)
        foreach ($this->taxonomyTermID as $param => &$values) {
            $value = get_query_var($param);
            if (empty($value)) {
                continue;
            }
            // intval re-sanitises user provided params that wordpress may have let slip through
            if (is_array($value)) {
                $values = array_merge($this->taxonomyTermID[$param], array_map('intval', $value));
            } else {
                $values[] = intval($value);
            }
        }
        $this->pageNumber = get_query_var('paged');
        if (empty($this->pageNumber)) {
            $this->pageNumber = 1; // page is a 1 based index
        }

        $this->searchID   = isset($_REQUEST['search_id']) ? intval($_REQUEST['search_id']) : 0;
        $this->searchText = isset($_REQUEST['search_text']) ?
            filter_var($_REQUEST['search_text'], FILTER_SANITIZE_STRING,
                       FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH) :
            '';
    }

    /**
     * calculate the request context from wordpress parameters and return an
     * array( location => array( term_ids ),
     *        profession => array( term_ids ),
     *        duration => array( term_ids ))
     *        theme => array( term_ids ))
     * where known. term_ids are wordpress taxonomy term ids
     */
    public function calculateFromPost()
    {
        // try calculating context from the current post (if any)
        $post = get_post();
        if (empty($post)) {
            return;
        }

        if (empty($this->postID)) {
            $this->postID = $post->ID;
        }

        foreach ($this->taxonomyTermID as $param => &$values) {
            $terms = wp_get_post_terms($post->ID, 'isdata_' . $param);
            if (empty($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $values[] = intval($term->term_id);
            }
            $values = array_unique($values);
        }
    }

    /**
     * @param array $args from shortcode, widget instance or hard coded
     */
    public function calculateFromArgs($args)
    {
        if (isset($args['n'])) {
            $this->itemsPerPage = isset($args['n']) ? intval($args['n']) : self::DEFAULT_ITEMS_PER_PAGE;
            $this->itemsPerPage = max(1, min($this->itemsPerPage, self::MAX_ITEMS_PER_PAGE));
        }

        // taxonomy terms overwrite any specified in prior context
        foreach ($this->taxonomyTermID as $taxonomy => &$values) {
            if (!isset($args[$taxonomy])) {
                continue;
            }
            $newValues = [];
            $items     = array_map('trim', explode(',', $args[$taxonomy]));
            foreach ($items as $item) {
                $term = get_term_by('slug', $item, 'isdata_' . $taxonomy);
                if (empty($term)) {
                    continue;
                }
                $newValues[] = $term->term_id;
            }
//            $values = array_intersect($values, $newValues);
            $values = $newValues; // overrides any other settings
        }
    }

    /**
     * tidy up anything after finishing calculate()
     */
    public function expandTaxonomyTerms()
    {
        // todo if a parent ID is selected, also select all children
    }
}
