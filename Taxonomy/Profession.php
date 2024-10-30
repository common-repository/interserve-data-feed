<?php
/**
 * @file
 * @author Steve Pavarno
 * @brief  implements \ISData\Taxonomy\Profession
 */

namespace ISData\Taxonomy;

if (class_exists('Profession')) {
    return;
}

require_once __DIR__ . '/CustomTaxonomy.php';

/**
 * Profession taxonomy
 */
class Profession extends CustomTaxonomy
{

    /**
     */
    public function __construct()
    {
        parent::__construct('profession');
    }
}
