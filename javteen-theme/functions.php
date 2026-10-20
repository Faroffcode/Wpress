<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function javteen_setup() {
 load_theme_textdomain( 'javteen', get_template_directory() . '/languages' );
 add_theme_support( 'title-tag' ); add_theme_support( 'post-thumbnails' );
 add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
 add_theme_support( 'custom-logo', array( 'height'=>80, 'width'=>240, 'flex-height'=>true, 'flex-width'=>true ) );
 register_nav_menus( array( 'primary'=>__( 'Primary Menu', 'javteen' ) ) );
}
add_action( 'after_setup_theme', 'javteen_setup' );
function javteen_assets() {
 wp_enqueue_style( 'javteen-style', get_stylesheet_uri(), array(), '1.0.0' );
 wp_enqueue_style( 'javteen-main', get_template_directory_uri().'/assets/css/main.css', array('javteen-style'), '1.0.0' );
 wp_enqueue_script( 'javteen-main', get_template_directory_uri().'/assets/js/main.js', array(), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'javteen_assets' );
add_filter( 'body_class', function($classes){ $classes[]='javteen-site'; return $classes; } );