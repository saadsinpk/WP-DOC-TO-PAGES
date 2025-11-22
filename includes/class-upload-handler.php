<?php

class Upload_Handler {

    public function __construct() {
        add_action('save_post_sdtb_book', [$this, 'handle_document_upload'], 10, 1);
    }

    public function handle_document_upload($post_id) {
        // Prevent infinite loops
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check nonce
        if (!isset($_POST['sdtb_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['sdtb_nonce'], 'sdtb_book_nonce')) {
            return;
        }

        // Check if file is uploaded
        if (!isset($_FILES['sdtb_document'])) {
            return;
        }

        $file = $_FILES['sdtb_document'];

        // Check for file upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        // Check file exists
        if (!file_exists($file['tmp_name'])) {
            return;
        }

        // Validate file type by original filename (more reliable)
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, ['doc', 'docx'])) {
            return;
        }

        // Move uploaded file to temp directory
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/sdtb_temp/' . uniqid() . '_' . sanitize_file_name($file['name']);
        $temp_dir = dirname($temp_file);

        if (!is_dir($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            return;
        }

        // Parse document
        $parsed = Document_Parser::parse_document($temp_file);

        if (!$parsed || !isset($parsed['success']) || !$parsed['success']) {
            @unlink($temp_file);
            return;
        }

        // Save parsed content to database
        $this->save_parsed_content($post_id, $parsed);

        // Clean up temp file
        @unlink($temp_file);

        // Update book metadata
        update_post_meta($post_id, '_sdtb_document_id', uniqid('sdtb_'));
        update_post_meta($post_id, '_sdtb_total_pages', intval($parsed['total_pages']));
    }

    /**
     * Save parsed content to database
     */
    private function save_parsed_content($post_id, $parsed) {
        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        // Delete existing pages for this book
        $wpdb->delete($table, ['book_id' => $post_id]);

        // Insert pages with SEQUENTIAL numbering (always 1, 2, 3, ...)
        // Generate titles for all pages based on content
        $page_number = 1;
        foreach ($parsed['pages'] as $page) {
            // Extract title from page content
            $page_title = $this->extract_page_title($page['content']);

            $wpdb->insert($table, [
                'book_id' => $post_id,
                'page_number' => $page_number,
                'page_title' => $page_title,
                'page_content' => $page['content'],
            ], ['%d', '%d', '%s', '%s']);

            $page_number++;
        }
    }

    /**
     * Extract a meaningful title from page content
     * Ensures title never exceeds 255 characters (database limit)
     */
    private function extract_page_title($content) {
        // Remove HTML tags for analysis
        $text = strip_tags($content);

        // Trim whitespace
        $text = trim($text);

        if (empty($text)) {
            return 'Untitled Page';
        }

        // Split by newlines and get the first non-empty line
        $lines = array_filter(array_map('trim', explode("\n", $text)));

        if (empty($lines)) {
            return 'Untitled Page';
        }

        // Get first line as potential title
        $first_line = reset($lines);

        // Clean up any extra whitespace
        $title = trim(preg_replace('/\s+/', ' ', $first_line));

        // Truncate to 200 bytes to be absolutely safe with database limit (255 bytes)
        // This accounts for multi-byte UTF-8 characters and ensures no issues
        if (strlen($title) > 200) {
            // Use mb_strcut to safely truncate UTF-8 strings without cutting characters
            // mb_strcut truncates by bytes while respecting multi-byte boundaries
            $title = mb_strcut($title, 0, 197, 'UTF-8') . '...';
        }

        // Final safety check - ensure title never exceeds 255 bytes
        if (strlen($title) > 255) {
            $title = mb_strcut($title, 0, 255, 'UTF-8');
        }

        return !empty($title) ? $title : 'Untitled Page';
    }

    /**
     * Check if file is valid DOC/DOCX
     */
    private function is_valid_doc_file($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        return in_array($ext, ['doc', 'docx']);
    }

    /**
     * Get human-readable upload error message
     */
    private function get_upload_error_message($error_code) {
        $errors = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];

        return $errors[$error_code] ?? 'Unknown upload error';
    }
}
