<?php
/**
 * WordPress Mock Functions for Unit Testing
 *
 * These mocks allow unit tests to run without a full WordPress environment.
 * Data can be set up using the global arrays before each test.
 */

// Global mock data storage
global $mock_post_meta, $mock_user_meta, $mock_term_meta, $mock_posts, $mock_users, $mock_terms, $mock_options;
$mock_post_meta = [];
$mock_user_meta = [];
$mock_term_meta = [];
$mock_posts = [];
$mock_users = [];
$mock_terms = [];
$mock_options = [];

/**
 * Reset all mock data - call in setUp()
 */
function reset_mock_data(): void {
    global $mock_post_meta, $mock_user_meta, $mock_term_meta, $mock_posts, $mock_users, $mock_terms, $mock_options;
    $mock_post_meta = [];
    $mock_user_meta = [];
    $mock_term_meta = [];
    $mock_posts = [];
    $mock_users = [];
    $mock_terms = [];
    $mock_options = [];
}

// === Post Meta Functions ===

function get_post_meta($post_id, $key = '', $single = false) {
    global $mock_post_meta;

    if (!isset($mock_post_meta[$post_id])) {
        return $single ? '' : [];
    }

    if (empty($key)) {
        return $mock_post_meta[$post_id];
    }

    if (!isset($mock_post_meta[$post_id][$key])) {
        return $single ? '' : [];
    }

    $value = $mock_post_meta[$post_id][$key];
    return $single ? ($value[0] ?? '') : $value;
}

function update_post_meta($post_id, $key, $value) {
    global $mock_post_meta;

    if (!isset($mock_post_meta[$post_id])) {
        $mock_post_meta[$post_id] = [];
    }

    $mock_post_meta[$post_id][$key] = [$value];
    return true;
}

function add_post_meta($post_id, $key, $value, $unique = false) {
    global $mock_post_meta;

    if (!isset($mock_post_meta[$post_id])) {
        $mock_post_meta[$post_id] = [];
    }

    if ($unique && isset($mock_post_meta[$post_id][$key])) {
        return false;
    }

    if (!isset($mock_post_meta[$post_id][$key])) {
        $mock_post_meta[$post_id][$key] = [];
    }

    $mock_post_meta[$post_id][$key][] = $value;
    return true;
}

function delete_post_meta($post_id, $key, $value = '') {
    global $mock_post_meta;

    if (!isset($mock_post_meta[$post_id][$key])) {
        return false;
    }

    if ($value === '') {
        unset($mock_post_meta[$post_id][$key]);
    } else {
        $mock_post_meta[$post_id][$key] = array_filter(
            $mock_post_meta[$post_id][$key],
            fn($v) => $v !== $value
        );
    }

    return true;
}

// === User Meta Functions ===

function get_user_meta($user_id, $key = '', $single = false) {
    global $mock_user_meta;

    if (!isset($mock_user_meta[$user_id])) {
        return $single ? '' : [];
    }

    if (empty($key)) {
        return $mock_user_meta[$user_id];
    }

    if (!isset($mock_user_meta[$user_id][$key])) {
        return $single ? '' : [];
    }

    $value = $mock_user_meta[$user_id][$key];
    return $single ? ($value[0] ?? '') : $value;
}

function update_user_meta($user_id, $key, $value) {
    global $mock_user_meta;

    if (!isset($mock_user_meta[$user_id])) {
        $mock_user_meta[$user_id] = [];
    }

    $mock_user_meta[$user_id][$key] = [$value];
    return true;
}

// === Term Meta Functions ===

function get_term_meta($term_id, $key = '', $single = false) {
    global $mock_term_meta;

    if (!isset($mock_term_meta[$term_id])) {
        return $single ? '' : [];
    }

    if (empty($key)) {
        return $mock_term_meta[$term_id];
    }

    if (!isset($mock_term_meta[$term_id][$key])) {
        return $single ? '' : [];
    }

    $value = $mock_term_meta[$term_id][$key];
    return $single ? ($value[0] ?? '') : $value;
}

function update_term_meta($term_id, $key, $value) {
    global $mock_term_meta;

    if (!isset($mock_term_meta[$term_id])) {
        $mock_term_meta[$term_id] = [];
    }

    $mock_term_meta[$term_id][$key] = [$value];
    return true;
}

// === Post Functions ===

function get_post($post_id) {
    global $mock_posts;

    if (!isset($mock_posts[$post_id])) {
        return null;
    }

    return (object) $mock_posts[$post_id];
}

function get_post_type($post_id) {
    global $mock_posts;

    if (!isset($mock_posts[$post_id])) {
        return false;
    }

    return $mock_posts[$post_id]['post_type'] ?? 'post';
}

function get_posts($args = []) {
    global $mock_posts;

    $results = [];
    $post_type = $args['post_type'] ?? 'post';

    foreach ($mock_posts as $id => $post) {
        if (($post['post_type'] ?? 'post') === $post_type) {
            $post_obj = (object) $post;
            $post_obj->ID = $id;
            $results[] = $post_obj;
        }
    }

    return $results;
}

function get_permalink($post_id) {
    global $mock_posts;

    if (!isset($mock_posts[$post_id])) {
        return false;
    }

    return 'https://example.com/' . ($mock_posts[$post_id]['post_name'] ?? 'post-' . $post_id);
}

function get_the_title($post_id) {
    global $mock_posts;

    if (!isset($mock_posts[$post_id])) {
        return '';
    }

    return $mock_posts[$post_id]['post_title'] ?? '';
}

// === User Functions ===

function get_user_by($field, $value) {
    global $mock_users;

    if ($field === 'ID') {
        if (!isset($mock_users[$value])) {
            return false;
        }
        $user = (object) $mock_users[$value];
        $user->ID = $value;
        return $user;
    }

    foreach ($mock_users as $id => $user) {
        if (isset($user[$field]) && $user[$field] === $value) {
            $user_obj = (object) $user;
            $user_obj->ID = $id;
            return $user_obj;
        }
    }

    return false;
}

function get_author_posts_url($user_id) {
    global $mock_users;

    if (!isset($mock_users[$user_id])) {
        return '';
    }

    return 'https://example.com/author/' . ($mock_users[$user_id]['user_nicename'] ?? 'user-' . $user_id);
}

// === Term Functions ===

function get_term($term_id, $taxonomy = '') {
    global $mock_terms;

    if (!isset($mock_terms[$term_id])) {
        return null;
    }

    $term = $mock_terms[$term_id];

    if ($taxonomy && ($term['taxonomy'] ?? '') !== $taxonomy) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }

    $term_obj = (object) $term;
    $term_obj->term_id = $term_id;
    return $term_obj;
}

function get_term_link($term) {
    if (is_object($term)) {
        return 'https://example.com/' . ($term->taxonomy ?? 'category') . '/' . ($term->slug ?? 'term-' . $term->term_id);
    }
    return 'https://example.com/term/' . $term;
}

function get_taxonomies($args = [], $output = 'names') {
    // Return a minimal set of public taxonomies
    $taxonomies = [
        'category' => (object) ['name' => 'category', 'label' => 'Categories'],
        'post_tag' => (object) ['name' => 'post_tag', 'label' => 'Tags'],
    ];

    if ($output === 'objects') {
        return $taxonomies;
    }

    return array_keys($taxonomies);
}

// === WP_Error Class ===

class WP_Error {
    public $code;
    public $message;
    public $data;

    public function __construct($code = '', $message = '', $data = '') {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code() {
        return $this->code;
    }

    public function get_error_message($code = '') {
        return $this->message;
    }
}

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

// === Hook Functions (No-ops for unit tests) ===

function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
    // No-op in unit tests
}

function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
    // No-op in unit tests
}

function do_action($tag, ...$args) {
    // No-op in unit tests
}

function apply_filters($tag, $value, ...$args) {
    return $value;
}

// === Content Functions ===

function wp_strip_all_tags($string, $remove_breaks = false) {
    $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
    $string = strip_tags($string);

    if ($remove_breaks) {
        $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
    }

    return trim($string);
}

function wp_trim_words($text, $num_words = 55, $more = null) {
    if ($more === null) {
        $more = '&hellip;';
    }

    $text = wp_strip_all_tags($text);
    $words = preg_split('/[\s]+/', $text, $num_words + 1);

    if (count($words) > $num_words) {
        array_pop($words);
        $text = implode(' ', $words) . $more;
    } else {
        $text = implode(' ', $words);
    }

    return $text;
}

function has_excerpt($post_id) {
    global $mock_posts;
    return !empty($mock_posts[$post_id]['post_excerpt'] ?? '');
}

function get_the_excerpt($post_id) {
    global $mock_posts;
    return $mock_posts[$post_id]['post_excerpt'] ?? '';
}

function get_post_field($field, $post_id) {
    global $mock_posts;
    return $mock_posts[$post_id][$field] ?? '';
}

// === Site Functions ===

function get_bloginfo($show) {
    $info = [
        'name' => 'Test Site',
        'url' => 'https://example.com',
        'home' => 'https://example.com',
    ];
    return $info[$show] ?? '';
}

function home_url($path = '') {
    return 'https://example.com' . $path;
}

function wp_parse_url($url, $component = -1) {
    return parse_url($url, $component);
}

// === Time Functions ===

function current_time($type, $gmt = 0) {
    if ($type === 'timestamp') {
        return $gmt ? time() : time() + (get_option('gmt_offset') * 3600);
    }
    return gmdate('Y-m-d H:i:s');
}

function get_option($option, $default = false) {
    global $mock_options;
    return $mock_options[$option] ?? $default;
}

// === Query Functions ===

function get_query_var($var, $default = '') {
    global $mock_query_vars;
    return $mock_query_vars[$var] ?? $default;
}

// === Escaping Functions ===

function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

// === Type Check Functions ===

function taxonomy_exists($taxonomy) {
    return in_array($taxonomy, ['category', 'post_tag', 'gruppe'], true);
}

function post_type_exists($post_type) {
    return in_array($post_type, ['post', 'page', 'kartpunkt', 'tribe_events', 'link'], true);
}

// === Utility Functions ===

function wp_parse_args($args, $defaults = []) {
    if (is_object($args)) {
        $args = get_object_vars($args);
    } elseif (is_string($args)) {
        parse_str($args, $args);
    }
    if (!is_array($args)) {
        $args = [];
    }
    return array_merge($defaults, $args);
}

function __($text, $domain = 'default') {
    return $text;
}

function esc_html__($text, $domain = 'default') {
    return esc_html($text);
}

function delete_term_meta($term_id, $key, $value = '') {
    global $mock_term_meta;

    if (!isset($mock_term_meta[$term_id][$key])) {
        return false;
    }

    if ($value === '') {
        unset($mock_term_meta[$term_id][$key]);
    } else {
        $mock_term_meta[$term_id][$key] = array_filter(
            $mock_term_meta[$term_id][$key],
            fn($v) => $v !== $value
        );
    }

    return true;
}

function delete_user_meta($user_id, $key, $value = '') {
    global $mock_user_meta;

    if (!isset($mock_user_meta[$user_id][$key])) {
        return false;
    }

    if ($value === '') {
        unset($mock_user_meta[$user_id][$key]);
    } else {
        $mock_user_meta[$user_id][$key] = array_filter(
            $mock_user_meta[$user_id][$key],
            fn($v) => $v !== $value
        );
    }

    return true;
}
