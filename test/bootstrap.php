<?php
/**
 * Setup for phpUnit unit testing.
 *
 * Some setup below from https://gist.github.com/dave1010/966439
 */

$_SERVER["HTTP_HOST"] = 'isorg';
$_SERVER["REQUEST_METHOD"] = 'GET';
$_SERVER["SERVER_PROTOCOL"] = 'HTTP/1.0';
$_SERVER["SERVER_PORT"] = '80';
$_SERVER["SERVER_NAME"] = $_SERVER["HTTP_HOST"];
$_SERVER["REMOTE_ADDR"] = 'localhost';
$_SERVER["REMOTE_PORT"] = '80';

define( 'PATH_TO_WORDPRESS', __DIR__ . '/../../../wordpress/' );

// Random WP things that need to be done
global $PHP_SELF;
global $wp_embed;
global $wpdb;
global $wp_version;
define( 'DOING_AJAX', true );
$GLOBALS[ '_wp_deprecated_widgets_callbacks' ] = array();

require_once( PATH_TO_WORDPRESS . 'wp-load.php' );

function cli_die($message) {
    debug_print_backtrace();
    die('DIE: ' . $message);
}
add_filter('wp_die_handler', 'cli_die');

ini_set('memory_limit', -1);

while (ob_get_level()) {
    // Who knows what some WP plugins are up to?
    ob_end_flush();
}
