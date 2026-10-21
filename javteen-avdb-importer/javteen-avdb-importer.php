<?php
/**
 * Plugin Name: JAVTEEN AVDB Importer
 * Description: Manually search AVDB metadata, select entries, and import them into JAVTEEN Videos. Playback URLs are never imported automatically.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: JAVTEEN
 * Text Domain: javteen-avdb-importer
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }

final class JAVTEEN_AVDB_Importer {
    const API = 'https://avdbapi.com/api.php/provide/vod';
    const NONCE = 'javteen_avdb_importer';
    const OPTION_AGE_GATE = 'javteen_avdb_age_gate';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_javteen_avdb_search', array(__CLASS__, 'search'));
        add_action('admin_post_javteen_avdb_import', array(__CLASS__, 'import_selected'));
        add_action('admin_post_javteen_avdb_age_settings', array(__CLASS__, 'save_age_settings'));
        add_action('template_redirect', array(__CLASS__, 'age_gate'), 0);
    }

    public static function activate() {
        if (get_option(self::OPTION_AGE_GATE, null) === null) {
            add_option(self::OPTION_AGE_GATE, '1');
        }
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=video', __('AVDB Importer', 'javteen-avdb-importer'), __('AVDB Importer', 'javteen-avdb-importer'), 'edit_posts', 'javteen-avdb-importer', array(__CLASS__, 'render_page'));
        add_options_page(__('JAVTEEN Age Access', 'javteen-avdb-importer'), __('JAVTEEN Age Access', 'javteen-avdb-importer'), 'manage_options', 'javteen-avdb-age-access', array(__CLASS__, 'render_age_settings'));
    }

    private static function admin_url_with($args = array()) {
        return add_query_arg(array_merge(array('post_type' => 'video', 'page' => 'javteen-avdb-importer'), $args), admin_url('edit.php'));
    }

    public static function render_page() {
        if (!current_user_can('edit_posts')) { wp_die(esc_html__('You are not allowed to import videos.', 'javteen-avdb-importer')); }
        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $results = get_transient(self::result_key());
        if (!is_array($results)) { $results = array(); }
        echo '<div class="wrap"><h1>JAVTEEN — AVDB Metadata Importer</h1>';
        echo '<p>Search AVDB, review the results, select only the entries you want, then import metadata. Playback URLs are intentionally excluded. Import only metadata and images you are authorized to use.</p>';
        if (isset($_GET['notice'])) {
            $notice = sanitize_key(wp_unslash($_GET['notice']));
            $messages = array('searched' => 'Search completed.', 'imported' => 'Selected entries were imported.', 'none' => 'Select at least one result.', 'error' => 'The request failed. Please retry.');
            if (isset($messages[$notice])) { echo '<div class="notice notice-info"><p>' . esc_html($messages[$notice]) . '</p></div>'; }
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="javteen_avdb_search">';
        wp_nonce_field(self::NONCE);
        echo '<label for="javteen-avdb-query"><strong>Search by title or JAV code</strong></label> ';
        echo '<input id="javteen-avdb-query" type="search" name="q" value="' . esc_attr($query) . '" class="regular-text" required maxlength="120" placeholder="e.g. MIAA-818">';
        submit_button('Search AVDB', 'primary', 'submit', false);
        echo '</form>';

        if ($results) {
            echo '<hr><h2>Review results</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="javteen_avdb_import">';
            wp_nonce_field(self::NONCE);
            echo '<p><label><input type="checkbox" id="javteen-avdb-select-all"> Select all results on this page</label></p>';
            echo '<table class="widefat striped"><thead><tr><th>Select</th><th>Poster</th><th>Title / Code</th><th>Year</th><th>Actors</th><th>Categories</th><th>Playback source (optional)</th></tr></thead><tbody>';
            foreach ($results as $item) {
                $id = isset($item['id']) ? absint($item['id']) : 0;
                if (!$id) { continue; }
                $title = self::text($item, 'name');
                $code = self::text($item, 'movie_code');
                $poster = self::safe_image_url(isset($item['poster_url']) ? $item['poster_url'] : '');
                echo '<tr><td><input class="javteen-avdb-select" type="checkbox" name="ids[]" value="' . esc_attr((string) $id) . '"></td><td>';
                if ($poster) { echo '<img src="' . esc_url($poster) . '" alt="" loading="lazy" style="width:70px;height:auto;max-height:100px;object-fit:cover">'; }
                echo '</td><td><strong>' . esc_html($title) . '</strong><br><code>' . esc_html($code) . '</code>';
                if (self::already_imported($id)) { echo '<br><span style="color:#996800">Already imported (will update)</span>'; }
                echo '</td><td>' . esc_html(self::text($item, 'year')) . '</td><td>' . esc_html(self::joined($item, 'actor')) . '</td><td>' . esc_html(self::joined($item, 'category')) . '</td><td>';
                $embeds = isset($item['embeds']) && is_array($item['embeds']) ? $item['embeds'] : array();
                if ($embeds) {
                    echo '<label class="screen-reader-text" for="javteen-avdb-embed-' . esc_attr((string) $id) . '">Playback embed for ' . esc_html($code) . '</label><select id="javteen-avdb-embed-' . esc_attr((string) $id) . '" name="embeds[' . esc_attr((string) $id) . ']" style="max-width:220px"><option value="">Metadata only</option>';
                    foreach ($embeds as $embed) { echo '<option value="' . esc_attr($embed['url']) . '">' . esc_html($embed['label']) . '</option>'; }
                    echo '</select>';
                } else { echo '<span class="description">No embed found</span>'; }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
            submit_button('Import selected metadata', 'primary');
            echo '<p class="description">Imported entries are saved as drafts for review. Choose an embed source only if you are authorized to use it. Leaving “Metadata only” selected will not change playback settings.</p></form>';
            echo '<script>(function(){var a=document.getElementById("javteen-avdb-select-all");if(a){a.addEventListener("change",function(){document.querySelectorAll(".javteen-avdb-select").forEach(function(c){c.checked=a.checked;});});}})();</script>';
        }
        echo '<p><a href="' . esc_url(admin_url('options-general.php?page=javteen-avdb-age-access')) . '">Age-access settings</a></p></div>';
    }

    private static function result_key() { return 'javteen_avdb_results_' . get_current_user_id(); }

    public static function search() {
        if (!current_user_can('edit_posts')) { wp_die(esc_html__('Not allowed.', 'javteen-avdb-importer')); }
        check_admin_referer(self::NONCE);
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        $q = trim(substr($q, 0, 120));
        if ($q === '') { wp_safe_redirect(self::admin_url_with(array('notice' => 'error'))); exit; }
        $url = add_query_arg(array('ac' => 'detail', 'wd' => $q, 'pagesize' => 20, 'pg' => 1), self::API);
        $response = wp_remote_get($url, array('timeout' => 15, 'redirection' => 2, 'headers' => array('Accept' => 'application/json', 'User-Agent' => 'JAVTEEN-AVDB-Importer/1.0; ' . home_url('/'))));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            delete_transient(self::result_key());
            wp_safe_redirect(self::admin_url_with(array('notice' => 'error', 'q' => rawurlencode($q)))); exit;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || !isset($data['list']) || !is_array($data['list'])) {
            delete_transient(self::result_key());
            wp_safe_redirect(self::admin_url_with(array('notice' => 'error'))); exit;
        }
        $safe = array();
        foreach (array_slice($data['list'], 0, 20) as $item) {
            if (!is_array($item) || empty($item['id']) || empty($item['name'])) { continue; }
            $safe[] = array(
                'id' => absint($item['id']),
                'name' => sanitize_text_field($item['name']),
                'movie_code' => isset($item['movie_code']) ? sanitize_text_field($item['movie_code']) : '',
                'year' => isset($item['year']) ? sanitize_text_field($item['year']) : '',
                'actor' => self::array_text($item, 'actor'),
                'category' => self::array_text($item, 'category'),
                'poster_url' => self::safe_image_url(isset($item['poster_url']) ? $item['poster_url'] : ''),
                'description' => isset($item['description']) ? wp_kses_post($item['description']) : '',
                'embeds' => self::extract_embeds(isset($item['episodes']) ? $item['episodes'] : array()),
            );
        }
        set_transient(self::result_key(), $safe, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(self::admin_url_with(array('notice' => 'searched', 'q' => rawurlencode($q)))); exit;
    }

    public static function import_selected() {
        if (!current_user_can('edit_posts')) { wp_die(esc_html__('Not allowed.', 'javteen-avdb-importer')); }
        check_admin_referer(self::NONCE);
        $selected = isset($_POST['ids']) ? array_values(array_unique(array_filter(array_map('absint', (array) wp_unslash($_POST['ids']))))) : array();
        $results = get_transient(self::result_key());
        if (!$selected || !is_array($results)) { wp_safe_redirect(self::admin_url_with(array('notice' => 'none'))); exit; }
        $by_id = array();
        foreach ($results as $item) { if (!empty($item['id'])) { $by_id[absint($item['id'])] = $item; } }
        $count = 0;
        foreach (array_slice($selected, 0, 20) as $id) {
            if (!isset($by_id[$id])) { continue; }
            $item = $by_id[$id];
            $existing = self::find_imported($id);
            $postarr = array(
                'post_type' => 'video',
                'post_status' => 'draft',
                'post_title' => sanitize_text_field($item['name']),
                'post_content' => isset($item['description']) ? wp_kses_post($item['description']) : '',
                'post_excerpt' => !empty($item['movie_code']) ? sanitize_text_field($item['movie_code']) : '',
            );
            if ($existing) { $postarr['ID'] = $existing; $postarr['post_status'] = get_post_status($existing) === 'publish' ? 'publish' : 'draft'; }
            $post_id = wp_insert_post(wp_slash($postarr), true);
            if (is_wp_error($post_id)) { continue; }
            update_post_meta($post_id, '_javteen_avdb_id', $id);
            update_post_meta($post_id, '_javteen_avdb_code', sanitize_text_field($item['movie_code']));
            update_post_meta($post_id, '_javteen_avdb_year', sanitize_text_field($item['year']));
            update_post_meta($post_id, '_javteen_avdb_actors', self::joined($item, 'actor'));
            update_post_meta($post_id, '_javteen_avdb_categories', self::joined($item, 'category'));
            update_post_meta($post_id, '_javteen_avdb_poster_url', self::safe_image_url($item['poster_url']));
            $requested_embeds = isset($_POST['embeds']) && is_array($_POST['embeds']) ? wp_unslash($_POST['embeds']) : array();
            $chosen_embed = isset($requested_embeds[$id]) ? esc_url_raw(trim((string) $requested_embeds[$id]), array('https')) : '';
            $allowed_embeds = isset($item['embeds']) && is_array($item['embeds']) ? array_column($item['embeds'], 'url') : array();
            if ($chosen_embed && in_array($chosen_embed, $allowed_embeds, true) && wp_http_validate_url($chosen_embed)) {
                update_post_meta($post_id, '_javteen_playback_mode', 'embed');
                update_post_meta($post_id, '_javteen_video_url', $chosen_embed);
            }
            // Only an embed explicitly selected by the admin is saved to JAVTEEN Core playback fields.
            if (!get_post_thumbnail_id($post_id) && !empty($item['poster_url'])) { self::sideload_poster($item['poster_url'], $post_id); }
            $count++;
        }
        wp_safe_redirect(self::admin_url_with(array('notice' => $count ? 'imported' : 'error'))); exit;
    }

    /** Extract HTTPS embed links from AVDB's variable episodes/server_data structure. */
    private static function extract_embeds($episodes) {
        $found = array();
        $walk = function($node, $label = '') use (&$walk, &$found) {
            if (!is_array($node)) { return; }
            if (isset($node['link_embed']) && is_string($node['link_embed'])) {
                $url = esc_url_raw($node['link_embed'], array('https'));
                if ($url && wp_http_validate_url($url) && !isset($found[$url])) {
                    $found[$url] = array('url' => $url, 'label' => $label !== '' ? $label : 'Embed source');
                }
            }
            $server = isset($node['server_name']) && is_scalar($node['server_name']) ? sanitize_text_field((string) $node['server_name']) : '';
            foreach ($node as $key => $value) {
                if ($key === 'link_embed') { continue; }
                $part = is_string($key) ? sanitize_text_field($key) : '';
                $next = trim(implode(' — ', array_filter(array($server, $part, $label)));
                if (is_array($value)) { $walk($value, $next); }
            }
        };
        $walk($episodes);
        $out = array();
        foreach ($found as $entry) { $out[] = array('url' => $entry['url'], 'label' => substr($entry['label'], 0, 100)); if (count($out) >= 20) { break; } }
        return $out;
    }
    private static function find_imported($api_id) {
        $posts = get_posts(array('post_type' => 'video', 'post_status' => array('publish','draft','pending','private'), 'meta_key' => '_javteen_avdb_id', 'meta_value' => absint($api_id), 'fields' => 'ids', 'numberposts' => 1, 'suppress_filters' => true));
        return $posts ? absint($posts[0]) : 0;
    }

    private static function already_imported($api_id) { return (bool) self::find_imported($api_id); }

    private static function sideload_poster($url, $post_id) {
        $url = self::safe_image_url($url);
        if (!$url) { return; }
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = download_url($url, 10);
        if (is_wp_error($tmp)) { return; }
        $name = 'avdb-' . absint($post_id) . '.jpg';
        $file = array('name' => $name, 'tmp_name' => $tmp);
        $attachment = media_handle_sideload($file, $post_id);
        if (is_wp_error($attachment)) { @unlink($tmp); return; }
        set_post_thumbnail($post_id, $attachment);
    }

    private static function safe_image_url($url) {
        $url = esc_url_raw((string) $url, array('https'));
        if (!$url || !wp_http_validate_url($url)) { return ''; }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if (!$host || in_array($host, array('localhost','127.0.0.1','::1'), true)) { return ''; }
        return $url;
    }

    private static function array_text($item, $key) {
        if (!isset($item[$key])) { return array(); }
        $values = is_array($item[$key]) ? $item[$key] : explode(',', (string) $item[$key]);
        return array_values(array_filter(array_map('sanitize_text_field', $values), 'strlen'));
    }
    private static function joined($item, $key) { return implode(', ', self::array_text($item, $key)); }
    private static function text($item, $key) { return isset($item[$key]) && is_scalar($item[$key]) ? sanitize_text_field((string) $item[$key]) : ''; }

    public static function render_age_settings() {
        if (!current_user_can('manage_options')) { return; }
        $enabled = get_option(self::OPTION_AGE_GATE, '1') === '1';
        echo '<div class="wrap"><h1>JAVTEEN Age Access</h1><p>This setting displays an age-confirmation gate to visitors before they access the public site. It is not identity verification and does not replace local legal compliance.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="javteen_avdb_age_settings">';
        wp_nonce_field(self::NONCE);
        echo '<label><input type="checkbox" name="enabled" value="1" ' . checked($enabled, true, false) . '> Enable age-confirmation gate (recommended)</label>';
        submit_button('Save settings');
        echo '</form></div>';
    }

    public static function save_age_settings() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not allowed.', 'javteen-avdb-importer')); }
        check_admin_referer(self::NONCE);
        update_option(self::OPTION_AGE_GATE, isset($_POST['enabled']) ? '1' : '0');
        wp_safe_redirect(admin_url('options-general.php?page=javteen-avdb-age-access&updated=1')); exit;
    }

    public static function age_gate() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON) || (isset($_GET['preview']) && is_user_logged_in()) || is_user_logged_in()) { return; }
        if (get_option(self::OPTION_AGE_GATE, '1') !== '1' || (isset($_COOKIE['javteen_age_confirmed']) && $_COOKIE['javteen_age_confirmed'] === '1')) { return; }
        if (isset($_POST['javteen_age_action'], $_POST['javteen_age_nonce']) && $_POST['javteen_age_action'] === 'confirm' && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['javteen_age_nonce'])), 'javteen_age_confirm')) {
            $confirmed = isset($_POST['adult_confirm']) && $_POST['adult_confirm'] === 'yes';
            if ($confirmed) {
                $secure = is_ssl();
                setcookie('javteen_age_confirmed', '1', array('expires' => time() + DAY_IN_SECONDS, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
                $_COOKIE['javteen_age_confirmed'] = '1';
                wp_safe_redirect(remove_query_arg(array('javteen_age_denied')));
                exit;
            }
            status_header(403);
        }
        status_header(200);
        nocache_headers();
        $action = esc_url(remove_query_arg(array('javteen_age_denied')));
        ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html(get_bloginfo('name')); ?> — Age confirmation</title><?php wp_head(); ?><style>body{margin:0;background:#080a13;color:#f4f5ff;font:16px/1.6 system-ui,sans-serif;display:grid;min-height:100vh;place-items:center}.gate{max-width:520px;margin:24px;padding:32px;border:1px solid #393253;border-radius:20px;background:#111525;text-align:center}.gate h1{font-size:28px}.gate button{background:linear-gradient(120deg,#7069ff,#a34df5);color:white;border:0;border-radius:10px;padding:13px 20px;font-weight:700;margin:8px;cursor:pointer}.gate a{color:#b5b4ff}</style></head><body><main class="gate"><h1>Age-restricted website</h1><p>This website may contain adult-oriented metadata and links. You must be at least the legal age required in your location to continue. By continuing, you confirm that you meet that age requirement.</p><form method="post" action="<?php echo $action; ?>"><?php wp_nonce_field('javteen_age_confirm', 'javteen_age_nonce'); ?><input type="hidden" name="javteen_age_action" value="confirm"><button type="submit" name="adult_confirm" value="yes">I meet the legal age requirement</button><button type="submit" name="adult_confirm" value="no" formnovalidate>Leave site</button></form><p><small>Age confirmation is not identity verification. Follow the laws that apply to you.</small></p></main><?php wp_footer(); ?></body></html><?php
        exit;
    }
}
JAVTEEN_AVDB_Importer::init();
register_activation_hook(__FILE__, array('JAVTEEN_AVDB_Importer', 'activate'));
