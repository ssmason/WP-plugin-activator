<?php

if ( ! isset( $table_prefix ) ) {
    $table_prefix = 'wp_';
}



if ( ! defined( 'DB_NAME' ) ) {
    define( 'DB_NAME', 'wordpress_test' );
}
if ( ! defined( 'DB_USER' ) ) {
    define( 'DB_USER', 'testuser' );
}
if ( ! defined( 'DB_PASSWORD' ) ) {
    define( 'DB_PASSWORD', 'testpassword' );
}
if ( ! defined( 'DB_HOST' ) ) {
    define( 'DB_HOST', '127.0.0.1:3307' );
}
if ( ! defined( 'DB_PORT' ) ) {
    define( 'DB_PORT', 3307 );
}
 
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', true );
}

if ( ! defined( 'WP_TESTS_DOMAIN' ) ) {
    define( 'WP_TESTS_DOMAIN', 'example.org' );
}
if ( ! defined( 'WP_TESTS_EMAIL' ) ) {
    define( 'WP_TESTS_EMAIL', 'admin@example.org' );
}
if ( ! defined( 'WP_TESTS_TITLE' ) ) {
    define( 'WP_TESTS_TITLE', 'Test Blog' );
}
if ( ! defined( 'WP_PHP_BINARY' ) ) {
    define( 'WP_PHP_BINARY', '/opt/homebrew/opt/php@8.2/bin/php' ); 
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/Users/stephenmason/projects/wp-coalition/html/' );
}