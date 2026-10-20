<?php
/**
 * Plugin Name: JAVTEEN Core
 * Description: Video catalog post type, categories, and secure metadata fields for the JAVTEEN theme.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: JAVTEEN
 * Text Domain: javteen-core
 * License: GPL-2.0-or-later
 */
if(!defined('ABSPATH')){exit;}
function javteen_core_register_content(){
 register_post_type('video',array('labels'=>array('name'=>__('Videos','javteen-core'),'singular_name'=>__('Video','javteen-core'),'add_new_item'=>__('Add New Video','javteen-core'),'edit_item'=>__('Edit Video','javteen-core'),'all_items'=>__('All Videos','javteen-core')),'public'=>true,'publicly_queryable'=>true,'show_in_rest'=>true,'menu_icon'=>'dashicons-video-alt3','has_archive'=>true,'rewrite'=>array('slug'=>'videos'),'supports'=>array('title','editor','thumbnail','excerpt','revisions')));
 register_taxonomy('video_category',array('video'),array('labels'=>array('name'=>__('Video Categories','javteen-core'),'singular_name'=>__('Video Category','javteen-core')),'public'=>true,'hierarchical'=>true,'show_in_rest'=>true,'rewrite'=>array('slug'=>'video-category')));
}
add_action('init','javteen_core_register_content');
function javteen_core_add_meta_box(){add_meta_box('javteen-video-details',__('Video Details','javteen-core'),'javteen_core_render_meta_box','video','normal','high');}
add_action('add_meta_boxes_video','javteen_core_add_meta_box');
function javteen_core_render_meta_box($post){
 wp_nonce_field('javteen_core_save_video','javteen_core_video_nonce');
 $mode=get_post_meta($post->ID,'_javteen_playback_mode',true); $url=get_post_meta($post->ID,'_javteen_video_url',true);
 if(!in_array($mode,array('direct','embed'),true)){$mode='direct';}
 ?>
 <p><label for="javteen-playback-mode"><strong><?php esc_html_e('Playback type','javteen-core'); ?></strong></label><br><select id="javteen-playback-mode" name="javteen_playback_mode"><option value="direct" <?php selected($mode,'direct'); ?>><?php esc_html_e('Direct media URL (MP4/WebM/OGG/HLS)','javteen-core'); ?></option><option value="embed" <?php selected($mode,'embed'); ?>><?php esc_html_e('Provider URL (WordPress oEmbed)','javteen-core'); ?></option></select></p>
 <p><label for="javteen-video-url"><strong><?php esc_html_e('Video URL','javteen-core'); ?></strong></label><br><input type="url" id="javteen-video-url" name="javteen_video_url" value="<?php echo esc_attr($url); ?>" class="widefat" placeholder="https://example.com/video.mp4"><span class="description"><?php esc_html_e('Use a direct media URL or a supported oEmbed provider URL. Do not paste arbitrary iframe/HTML code.','javteen-core'); ?></span></p>
 <?php
}
function javteen_core_save_video_meta($post_id){
 if(!isset($_POST['javteen_core_video_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['javteen_core_video_nonce'])),'javteen_core_save_video')){return;}
 if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){return;} if(wp_is_post_revision($post_id) || 'video'!==get_post_type($post_id)){return;} if(!current_user_can('edit_post',$post_id)){return;}
 $mode=isset($_POST['javteen_playback_mode'])?sanitize_key(wp_unslash($_POST['javteen_playback_mode'])):'direct'; if(!in_array($mode,array('direct','embed'),true)){$mode='direct';}
 $url=isset($_POST['javteen_video_url'])?esc_url_raw(trim(wp_unslash($_POST['javteen_video_url'])),array('http','https')):''; if($url && !wp_http_validate_url($url)){$url='';}
 update_post_meta($post_id,'_javteen_playback_mode',$mode); if($url){update_post_meta($post_id,'_javteen_video_url',$url);}else{delete_post_meta($post_id,'_javteen_video_url');}
}
add_action('save_post_video','javteen_core_save_video_meta');
function javteen_core_activation(){javteen_core_register_content();flush_rewrite_rules();}
register_activation_hook(__FILE__,'javteen_core_activation');
function javteen_core_deactivation(){flush_rewrite_rules();}
register_deactivation_hook(__FILE__,'javteen_core_deactivation');