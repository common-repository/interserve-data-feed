<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Taxonomy\Duration
 */

namespace ISData\Taxonomy;

if (class_exists('Duration')) {
    return;
}

require_once __DIR__ . '/CustomTaxonomy.php';

/**
 * Duration taxonomy
 */
class Duration extends CustomTaxonomy
{
    public function __construct()
    {
        parent::__construct('duration');
    }
}
