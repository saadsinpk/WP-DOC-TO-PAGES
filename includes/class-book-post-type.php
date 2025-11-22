<?php

class Book_Post_Type {

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('query_vars', [$this, 'register_query_vars']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_sdtb_book', [$this, 'save_meta_boxes']);
        add_action('delete_post', [$this, 'cleanup_on_delete']);
    }

    public function register_post_type() {
        $labels = [
            'name' => __('Books', 'sid-doc-to-book'),
            'singular_name' => __('Book', 'sid-doc-to-book'),
            'menu_name' => __('Books', 'sid-doc-to-book'),
            'add_new' => __('Add New Book', 'sid-doc-to-book'),
            'add_new_item' => __('Add New Book', 'sid-doc-to-book'),
            'edit_item' => __('Edit Book', 'sid-doc-to-book'),
            'view_item' => __('View Book', 'sid-doc-to-book'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'book', 'with_front' => false],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-book',
            'supports' => ['title', 'thumbnail', 'excerpt'],
            'taxonomies' => ['sdtb_book_category'],
        ];

        register_post_type('sdtb_book', $args);
    }

    /**
     * Register custom query vars
     */
    public function register_query_vars($query_vars) {
        $query_vars[] = 'sdtb_page';
        return $query_vars;
    }

    public function register_taxonomy() {
        $labels = [
            'name' => __('Book Categories', 'sid-doc-to-book'),
            'singular_name' => __('Category', 'sid-doc-to-book'),
            'search_items' => __('Search Categories', 'sid-doc-to-book'),
        ];

        register_taxonomy('sdtb_book_category', 'sdtb_book', [
            'labels' => $labels,
            'show_ui' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'book-category'],
        ]);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'sdtb_book_document',
            __('Book Document', 'sid-doc-to-book'),
            [$this, 'render_document_meta_box'],
            'sdtb_book',
            'normal',
            'high'
        );

        add_meta_box(
            'sdtb_book_info',
            __('Book Information', 'sid-doc-to-book'),
            [$this, 'render_info_meta_box'],
            'sdtb_book',
            'normal',
            'high'
        );

        // Ensure multipart form encoding
        add_action('post_edit_form_tag', [$this, 'add_multipart_form']);
    }

    public function add_multipart_form() {
        echo ' enctype="multipart/form-data"';
    }

    public function render_document_meta_box($post) {
        $doc_id = get_post_meta($post->ID, '_sdtb_document_id', true);
        // Always get actual page count from database
        $total_pages = Book_Manager::get_total_pages($post->ID);
        wp_nonce_field('sdtb_book_nonce', 'sdtb_nonce');
        ?>
        <div style="padding: 10px;">
            <p>
                <label for="sdtb_document" style="display: block; font-weight: bold; margin-bottom: 8px;">
                    <?php _e('Upload Word Document', 'sid-doc-to-book'); ?>
                </label>
                <input type="file" id="sdtb_document" name="sdtb_document" accept=".docx,.doc" style="margin: 10px 0; padding: 8px; display: block;">
                <small style="display: block; margin-top: 8px; color: #666;">
                    <?php _e('Supported formats: .docx (recommended), .doc', 'sid-doc-to-book'); ?>
                </small>
                <small style="display: block; margin-top: 8px; color: #999;">
                    <?php _e('Maximum file size: 50MB', 'sid-doc-to-book'); ?>
                </small>
            </p>

            <?php if ($total_pages): ?>
                <div style="background: #e7f3ff; padding: 12px; border-left: 4px solid #0073aa; margin: 15px 0; border-radius: 3px;">
                    <p style="margin: 0 0 8px 0;">
                        <strong style="color: #0073aa;">✓ <?php _e('Document Loaded Successfully', 'sid-doc-to-book'); ?></strong>
                    </p>
                    <p style="margin: 0;">
                        <?php printf(__('Total Pages: <strong>%d</strong>', 'sid-doc-to-book'), intval($total_pages)); ?>
                    </p>
                    <?php if ($doc_id): ?>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em; color: #666;">
                            Document ID: <?php echo esc_html($doc_id); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <p style="margin-top: 15px;">
                    <button type="button" id="sdtb_reprocess" class="button button-secondary">
                        <?php _e('Upload New Document', 'sid-doc-to-book'); ?>
                    </button>
                </p>
            <?php else: ?>
                <div style="background: #fff3cd; padding: 12px; border-left: 4px solid #ffc107; margin: 15px 0; border-radius: 3px;">
                    <p style="margin: 0; color: #856404;">
                        <strong>ℹ <?php _e('No document uploaded yet', 'sid-doc-to-book'); ?></strong>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_info_meta_box($post) {
        $language = get_post_meta($post->ID, '_sdtb_language', true) ?: 'urdu';
        $author = get_post_meta($post->ID, '_sdtb_author', true);
        wp_nonce_field('sdtb_info_nonce', 'sdtb_info_nonce');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="sdtb_author"><?php _e('Author', 'sid-doc-to-book'); ?></label></th>
                <td><input type="text" id="sdtb_author" name="sdtb_author" value="<?php echo esc_attr($author); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="sdtb_language"><?php _e('Language', 'sid-doc-to-book'); ?></label></th>
                <td>
                    <select id="sdtb_language" name="sdtb_language">
                        <option value="urdu" <?php selected($language, 'urdu'); ?>><?php _e('Urdu', 'sid-doc-to-book'); ?></option>
                        <option value="arabic" <?php selected($language, 'arabic'); ?>><?php _e('Arabic', 'sid-doc-to-book'); ?></option>
                        <option value="mixed" <?php selected($language, 'mixed'); ?>><?php _e('Urdu + Arabic', 'sid-doc-to-book'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['sdtb_nonce']) || !wp_verify_nonce($_POST['sdtb_nonce'], 'sdtb_book_nonce')) {
            return;
        }

        if (isset($_POST['sdtb_author'])) {
            update_post_meta($post_id, '_sdtb_author', sanitize_text_field($_POST['sdtb_author']));
        }

        if (isset($_POST['sdtb_language'])) {
            update_post_meta($post_id, '_sdtb_language', sanitize_text_field($_POST['sdtb_language']));
        }
    }

    /**
     * Clean up pages when book post is deleted
     */
    public function cleanup_on_delete($post_id) {
        // Only delete if it's a book post
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'sdtb_book') {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        // Delete all pages for this book
        $deleted = $wpdb->delete($table, ['book_id' => $post_id], ['%d']);

        if ($deleted) {
            error_log('SDTB Cleanup: Deleted ' . $deleted . ' pages for book ID ' . $post_id);
        }
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $pages_table = $wpdb->prefix . 'sdtb_book_pages';
        $sql = "CREATE TABLE IF NOT EXISTS {$pages_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            book_id BIGINT(20) UNSIGNED NOT NULL,
            page_number INT(11) NOT NULL,
            page_title VARCHAR(255),
            page_content LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY book_id (book_id),
            KEY page_number (book_id, page_number)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
