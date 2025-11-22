<?php

class Frontend {

    public function __construct() {
        add_filter('template_include', [$this, 'load_book_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Load custom book template
     */
    public function load_book_template($template) {
        // Check if we're viewing a book post
        if (is_singular('sdtb_book')) {
            $custom_template = SDTB_PLUGIN_PATH . 'templates/single-book.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        // Check if we're viewing book archive
        if (is_post_type_archive('sdtb_book')) {
            $custom_template = SDTB_PLUGIN_PATH . 'templates/archive-book.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        if (is_singular('sdtb_book') || is_post_type_archive('sdtb_book')) {
            wp_enqueue_style('sdtb-frontend', SDTB_PLUGIN_URL . 'assets/css/frontend.css', [], SDTB_VERSION);
            wp_enqueue_script('sdtb-frontend', SDTB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], SDTB_VERSION, true);

            // Localize script with AJAX data
            wp_localize_script('sdtb-frontend', 'sdtbData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sdtb_frontend_nonce'),
            ]);
        }
    }
}

// AJAX handler for page navigation
add_action('wp_ajax_nopriv_sdtb_get_page', 'sdtb_ajax_get_page');
add_action('wp_ajax_sdtb_get_page', 'sdtb_ajax_get_page');

function sdtb_ajax_get_page() {
    check_ajax_referer('sdtb_frontend_nonce', 'nonce');

    $book_id = intval($_POST['book_id']);
    $page_number = intval($_POST['page_number']);

    $page = Book_Manager::get_page($book_id, $page_number);

    if (!$page) {
        wp_send_json_error('Page not found');
    }

    wp_send_json_success($page);
}

// AJAX handler for search
add_action('wp_ajax_nopriv_sdtb_search', 'sdtb_ajax_search');
add_action('wp_ajax_sdtb_search', 'sdtb_ajax_search');

function sdtb_ajax_search() {
    check_ajax_referer('sdtb_frontend_nonce', 'nonce');

    $book_id = intval($_POST['book_id']);
    $search_term = sanitize_text_field($_POST['search_term']);

    if (strlen($search_term) < 2) {
        wp_send_json_error('Search term too short');
    }

    $results = Book_Manager::search_in_book($book_id, $search_term);

    wp_send_json_success($results);
}
