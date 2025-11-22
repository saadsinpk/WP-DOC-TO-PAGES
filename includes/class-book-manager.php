<?php

class Book_Manager {

    /**
     * Get book by ID with all its pages
     */
    public static function get_book($book_id) {
        $book = get_post($book_id);
        if (!$book || $book->post_type !== 'sdtb_book') {
            return false;
        }

        return [
            'id' => $book->ID,
            'title' => $book->post_title,
            'description' => $book->post_content,
            'excerpt' => $book->post_excerpt,
            'thumbnail' => get_the_post_thumbnail_url($book->ID),
            'author' => get_post_meta($book->ID, '_sdtb_author', true),
            'language' => get_post_meta($book->ID, '_sdtb_language', true),
            'total_pages' => get_post_meta($book->ID, '_sdtb_total_pages', true),
        ];
    }

    /**
     * Get all pages of a book
     */
    public static function get_book_pages($book_id, $page = 1, $per_page = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        $offset = ($page - 1) * $per_page;

        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE book_id = %d ORDER BY page_number ASC LIMIT %d OFFSET %d",
            $book_id,
            $per_page,
            $offset
        ));

        return $pages;
    }

    /**
     * Get single page
     */
    public static function get_page($book_id, $page_number) {
        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE book_id = %d AND page_number = %d",
            $book_id,
            $page_number
        ));
    }

    /**
     * Get total pages count
     */
    public static function get_total_pages($book_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE book_id = %d",
            $book_id
        ));

        return intval($count);
    }

    /**
     * Search book content
     */
    public static function search_in_book($book_id, $search_term) {
        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        $escaped_term = '%' . $wpdb->esc_like($search_term) . '%';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE book_id = %d
             AND (page_content LIKE %s OR page_title LIKE %s)
             ORDER BY page_number ASC",
            $book_id,
            $escaped_term,
            $escaped_term
        ));

        // Additional filtering if results are empty
        // Sometimes HTML tags interfere with search
        if (empty($results)) {
            error_log('SDTB Search: No results found for term: ' . $search_term);
        } else {
            error_log('SDTB Search: Found ' . count($results) . ' results for term: ' . $search_term);
        }

        return $results;
    }
}
