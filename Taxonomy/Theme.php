<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Taxonomy\Theme
 */

namespace ISData\Taxonomy;

if (class_exists('Theme')) {
    return;
}

require_once __DIR__ . '/CustomTaxonomy.php';

/**
 * Theme taxonomy
 */
class Theme extends CustomTaxonomy
{
    public function __construct()
    {
        parent::__construct('theme');
    }
}
