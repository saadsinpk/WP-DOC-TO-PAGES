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
     * Supports text, numbers, and mixed queries like "Hadith 365"
     */
    public static function search_in_book($book_id, $search_term) {
        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        // Trim and clean the search term
        $search_term = trim($search_term);

        if (empty($search_term)) {
            return [];
        }

        $escaped_term = '%' . $wpdb->esc_like($search_term) . '%';

        // First try exact match
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE book_id = %d
             AND (page_content LIKE %s OR page_title LIKE %s)
             ORDER BY page_number ASC",
            $book_id,
            $escaped_term,
            $escaped_term
        ));

        // If no results and search contains multiple words, try each word
        if (empty($results) && strpos($search_term, ' ') !== false) {
            $words = preg_split('/\s+/', $search_term);
            $conditions = [];
            $params = [$book_id];

            foreach ($words as $word) {
                if (strlen($word) >= 1) {
                    $escaped_word = '%' . $wpdb->esc_like($word) . '%';
                    $conditions[] = "(page_content LIKE %s OR page_title LIKE %s)";
                    $params[] = $escaped_word;
                    $params[] = $escaped_word;
                }
            }

            if (!empty($conditions)) {
                $where_clause = implode(' AND ', $conditions);
                $sql = "SELECT * FROM {$table} WHERE book_id = %d AND ({$where_clause}) ORDER BY page_number ASC";
                $results = $wpdb->get_results($wpdb->prepare($sql, $params));
            }
        }

        // If still no results, try searching with stripped HTML tags
        if (empty($results)) {
            // Search in stripped content (for cases where HTML interferes)
            $all_pages = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE book_id = %d ORDER BY page_number ASC",
                $book_id
            ));

            $search_lower = mb_strtolower($search_term, 'UTF-8');
            foreach ($all_pages as $page) {
                $stripped_content = strip_tags($page->page_content);
                $content_lower = mb_strtolower($stripped_content, 'UTF-8');
                $title_lower = mb_strtolower($page->page_title ?? '', 'UTF-8');

                if (mb_strpos($content_lower, $search_lower, 0, 'UTF-8') !== false ||
                    mb_strpos($title_lower, $search_lower, 0, 'UTF-8') !== false) {
                    $results[] = $page;
                }
            }
        }

        return $results;
    }
}
