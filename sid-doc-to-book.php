<?php
/**
 * Plugin Name: SID Doc to Book
 * Plugin URI: https://example.com
 * Description: Convert Word documents to interactive WordPress book display
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL2
 * Text Domain: sid-doc-to-book
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SDTB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SDTB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SDTB_VERSION', '1.0.0');

// Load required files
require_once SDTB_PLUGIN_PATH . 'includes/class-book-post-type.php';
require_once SDTB_PLUGIN_PATH . 'includes/class-document-parser.php';
require_once SDTB_PLUGIN_PATH . 'includes/class-upload-handler.php';
require_once SDTB_PLUGIN_PATH . 'includes/class-book-manager.php';
require_once SDTB_PLUGIN_PATH . 'includes/class-frontend.php';
require_once SDTB_PLUGIN_PATH . 'includes/class-admin-page-editor.php';

class SID_Doc_To_Book {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function init() {
        // Initialize all classes
        new Book_Post_Type();
        new Upload_Handler();
        new Frontend();

        // Initialize admin tools
        if (is_admin()) {
            new Admin_Page_Editor();
        }
    }

    public function activate() {
        // Create necessary database tables
        Book_Post_Type::create_tables();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
SID_Doc_To_Book::get_instance();
