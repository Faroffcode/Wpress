<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main-content"><?php esc_html_e('Skip to content','javteen'); ?></a>
<header class="site-header"><div class="container header-inner">
<a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
<?php if(has_custom_logo()){the_custom_logo();}else{?><span class="brand-mark">J</span><span class="brand-name">JAV<span>TEEN</span></span><?php } ?></a>
<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation"><?php esc_html_e('Menu','javteen'); ?></button>
<nav id="primary-navigation" class="primary-navigation" aria-label="<?php esc_attr_e('Primary navigation','javteen'); ?>"><?php wp_nav_menu(array('theme_location'=>'primary','container'=>false,'fallback_cb'=>false)); ?></nav>
<div class="header-search"><?php get_search_form(); ?></div></div></header>