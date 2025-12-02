<?php

/**
 * Admin Page Editor - Edit individual book pages
 * Allows editing page content, title, and metadata without re-uploading document
 */
class Admin_Page_Editor {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_page_editor_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_fonts']);
        add_action('wp_ajax_sdtb_get_page_list', [$this, 'ajax_get_page_list']);
        add_action('wp_ajax_sdtb_get_page_content', [$this, 'ajax_get_page_content']);
        add_action('wp_ajax_sdtb_save_page_content', [$this, 'ajax_save_page_content']);
        add_action('wp_ajax_sdtb_delete_page', [$this, 'ajax_delete_page']);
        add_action('wp_ajax_sdtb_create_page', [$this, 'ajax_create_page']);
        add_action('wp_ajax_sdtb_reorder_pages', [$this, 'ajax_reorder_pages']);
    }

    /**
     * Enqueue Google Fonts for admin editor
     */
    public function enqueue_admin_fonts($hook) {
        global $post_type;

        // Only load on book edit pages
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'sdtb_book') {
            wp_enqueue_style(
                'sdtb-admin-google-fonts',
                'https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Noto+Nastaliq+Urdu:wght@400;500;600;700&family=Noto+Naskh+Arabic:wght@400;500;600;700&family=Scheherazade+New:wght@400;500;600;700&display=swap',
                [],
                null
            );
        }
    }

    /**
     * Add page editor meta box to book edit page
     */
    public function add_page_editor_meta_box() {
        add_meta_box(
            'sdtb_page_editor',
            __('Edit Pages', 'sid-doc-to-book'),
            [$this, 'render_page_editor_meta_box'],
            'sdtb_book',
            'normal',
            'high'
        );
    }

    /**
     * Render the page editor meta box
     */
    public function render_page_editor_meta_box($post) {
        global $wpdb;
        $book_id = $post->ID;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        // Get total pages
        $total_pages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE book_id = %d",
            $book_id
        ));

        wp_nonce_field('sdtb_page_editor_nonce', 'sdtb_page_editor_nonce');
        ?>
        <div style="padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
            <?php if ($total_pages === 0): ?>
                <p style="color: #666;">
                    <strong>ℹ️ No Pages</strong><br>
                    Upload a document first to edit pages.
                </p>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                    <!-- Pages List -->
                    <div style="border-right: 1px solid #ddd; padding-right: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h4 style="margin: 0;">Pages</h4>
                            <button type="button" class="button button-primary" id="sdtb-new-page-btn" style="padding: 4px 10px; font-size: 12px;">
                                ➕ New Page
                            </button>
                        </div>

                        <div style="max-height: 400px; overflow-y: auto;">
                            <div id="sdtb-page-list" style="display: flex; flex-direction: column; gap: 5px;">
                                <p style="color: #999; text-align: center;">Loading pages...</p>
                            </div>
                        </div>

                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">
                            <p style="font-size: 0.9em; color: #666; margin: 8px 0;">
                                <strong>Total Pages:</strong> <span id="sdtb-total-pages"><?php echo intval($total_pages); ?></span>
                            </p>
                            <p style="font-size: 0.85em; color: #999; margin: 8px 0;">
                                💡 <strong>Drag pages</strong> to reorder them
                            </p>
                        </div>
                    </div>

                    <!-- Page Editor -->
                    <div>
                        <h4 style="margin-top: 0;">Edit Page</h4>
                        <div id="sdtb-editor-area" style="display: none;">
                            <!-- Page Number Display -->
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: bold; margin-bottom: 8px;">
                                    Page Number
                                </label>
                                <input type="number" id="sdtb-edit-page-number" min="1" readonly style="width: 100%; padding: 8px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 3px;">
                            </div>

                            <!-- Page Title -->
                            <div style="margin-bottom: 15px;">
                                <label for="sdtb-edit-page-title" style="display: block; font-weight: bold; margin-bottom: 8px;">
                                    Page Title (Optional)
                                </label>
                                <input type="text" id="sdtb-edit-page-title" placeholder="Enter page title..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                                <small style="display: block; margin-top: 4px; color: #666;">
                                    Leave empty for no title
                                </small>
                            </div>

                            <!-- Page Content -->
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: bold; margin-bottom: 8px;">
                                    Page Content
                                </label>

                                <!-- WordPress TinyMCE Editor with Extended Font Controls -->
                                <?php
                                // Define font formats for TinyMCE (use single quotes for font names with spaces)
                                $font_formats = "Arial=Arial, Helvetica, sans-serif;" .
                                    "Arial Black='Arial Black', Gadget, sans-serif;" .
                                    "Georgia=Georgia, serif;" .
                                    "Tahoma=Tahoma, Geneva, sans-serif;" .
                                    "Times New Roman='Times New Roman', Times, serif;" .
                                    "Trebuchet MS='Trebuchet MS', Helvetica, sans-serif;" .
                                    "Verdana=Verdana, Geneva, sans-serif;" .
                                    "Amiri=Amiri, serif;" .
                                    "Scheherazade New='Scheherazade New', serif;" .
                                    "Noto Naskh Arabic='Noto Naskh Arabic', serif;" .
                                    "Noto Nastaliq Urdu='Noto Nastaliq Urdu', serif;" .
                                    "Jameel Noori Nastaleeq='Jameel Noori Nastaleeq', serif;" .
                                    "Segoe UI='Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

                                // Font sizes
                                $font_sizes = '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 22pt 24pt 26pt 28pt 36pt 48pt 72pt';

                                wp_editor('', 'sdtb-edit-page-content', [
                                    'textarea_name' => 'sdtb_page_content',
                                    'editor_class' => 'sdtb-page-editor',
                                    'media_buttons' => false,
                                    'tinymce' => [
                                        'toolbar1' => 'fontselect,fontsizeselect,|,bold,italic,underline,strikethrough,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,alignjustify',
                                        'toolbar2' => 'bullist,numlist,blockquote,|,link,unlink,|,formatselect,removeformat,|,undo,redo',
                                        'plugins' => 'lists,link,charmap,paste,textcolor',
                                        'font_formats' => $font_formats,
                                        'fontsize_formats' => $font_sizes,
                                        'content_css' => 'https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Noto+Nastaliq+Urdu:wght@400;700&family=Noto+Naskh+Arabic:wght@400;700&family=Scheherazade+New:wght@400;700&display=swap',
                                        'body_class' => 'sdtb-tinymce-editor',
                                        'content_style' => "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; direction: rtl; } body.mce-content-body { padding: 10px; }",
                                    ],
                                    'quicktags' => [
                                        'buttons' => 'strong,em,u,li,ol,link'
                                    ]
                                ]);
                                ?>
                            </div>

                            <!-- Content Length Info -->
                            <div style="background: #f0f0f0; padding: 10px; border-radius: 3px; margin-bottom: 15px; font-size: 0.9em;">
                                <strong>Content Info:</strong><br>
                                Characters: <span id="sdtb-char-count">0</span> | Words: <span id="sdtb-word-count">0</span>
                            </div>

                            <!-- Action Buttons -->
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="button button-primary" id="sdtb-save-page-btn">
                                    💾 Save Changes
                                </button>
                                <button type="button" class="button" id="sdtb-cancel-edit-btn">
                                    Cancel
                                </button>
                                <button type="button" class="button button-link-delete" id="sdtb-delete-page-btn" style="margin-left: auto;">
                                    🗑️ Delete Page
                                </button>
                            </div>

                            <!-- Status Message -->
                            <div id="sdtb-save-status" style="margin-top: 15px; padding: 10px; border-radius: 3px; display: none;">
                            </div>
                        </div>

                        <!-- No Selection -->
                        <div id="sdtb-no-selection" style="color: #666; text-align: center; padding: 40px 20px;">
                            <p>👈 Select a page from the list to edit</p>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const bookId = <?php echo intval($book_id); ?>;
                    let currentEditPage = null;

                    // Load page list
                    function loadPageList() {
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'sdtb_get_page_list',
                                book_id: bookId,
                                nonce: document.querySelector('input[name="sdtb_page_editor_nonce"]').value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const list = document.getElementById('sdtb-page-list');
                                document.getElementById('sdtb-total-pages').textContent = data.data.length;

                                list.innerHTML = data.data.map((page, index) => `
                                    <div class="sdtb-page-item" data-page-id="${page.id}" data-page-num="${page.page_number}" draggable="true" style="padding: 10px; border: 1px solid #ddd; border-radius: 3px; background: white; cursor: grab; transition: all 0.2s; display: flex; align-items: center; gap: 8px;">
                                        <span style="color: #999; cursor: grab; flex-shrink: 0;">⋮⋮</span>
                                        <button type="button" class="sdtb-page-btn" style="flex: 1; padding: 0; border: none; background: none; cursor: pointer; text-align: left;">
                                            <strong>Page ${page.page_number}</strong><br>
                                            <span style="font-size: 0.85em; color: #666;">${page.page_title || '(No title)'}</span>
                                        </button>
                                        <button type="button" class="sdtb-delete-page-quick" data-page-id="${page.id}" data-page-num="${page.page_number}" style="padding: 4px 8px; border: 1px solid #dc3545; background: #fff; color: #dc3545; border-radius: 3px; cursor: pointer; font-size: 12px; flex-shrink: 0;">
                                            🗑️
                                        </button>
                                    </div>
                                `).join('');

                                // Update total pages
                                updateTotalPages();

                                // Attach click handlers
                                document.querySelectorAll('.sdtb-page-btn').forEach(btn => {
                                    btn.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        const pageNum = parseInt(this.closest('.sdtb-page-item').dataset.pageNum);
                                        loadPageContent(bookId, pageNum);
                                    });
                                });

                                // Drag and drop handlers
                                setupDragAndDrop();

                                // Delete page quick button
                                document.querySelectorAll('.sdtb-delete-page-quick').forEach(btn => {
                                    btn.addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        const pageNum = parseInt(this.dataset.pageNum);
                                        const pageId = parseInt(this.dataset.pageId);

                                        if (confirm(`Delete page ${pageNum}? This cannot be undone.`)) {
                                            deletePageQuick(pageId);
                                        }
                                    });
                                });
                            }
                        });
                    }

                    // Load page content
                    function loadPageContent(bookId, pageNum) {
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'sdtb_get_page_content',
                                book_id: bookId,
                                page_number: pageNum,
                                nonce: document.querySelector('input[name="sdtb_page_editor_nonce"]').value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const page = data.data;
                                currentEditPage = page.id;
                                document.getElementById('sdtb-edit-page-number').value = page.page_number;
                                document.getElementById('sdtb-edit-page-title').value = page.page_title || '';

                                // Set content in TinyMCE editor
                                if (typeof tinymce !== 'undefined' && tinymce.get('sdtb-edit-page-content')) {
                                    tinymce.get('sdtb-edit-page-content').setContent(page.page_content);
                                } else {
                                    document.getElementById('sdtb-edit-page-content').value = page.page_content;
                                }

                                updateContentStats();
                                document.getElementById('sdtb-editor-area').style.display = 'block';
                                document.getElementById('sdtb-no-selection').style.display = 'none';

                                // Highlight selected page
                                document.querySelectorAll('.sdtb-page-btn').forEach(btn => {
                                    btn.style.background = btn.dataset.pageNum == pageNum ? '#e7f3ff' : 'white';
                                    btn.style.borderColor = btn.dataset.pageNum == pageNum ? '#0073aa' : '#ddd';
                                });
                            } else {
                                alert('Error loading page: ' + data.data);
                            }
                        });
                    }

                    // Create new page
                    document.getElementById('sdtb-new-page-btn').addEventListener('click', function() {
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'sdtb_create_page',
                                book_id: bookId,
                                nonce: document.querySelector('input[name="sdtb_page_editor_nonce"]').value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const pageNum = data.data.page_number;
                                alert('✅ New page created! (Page #' + pageNum + ')');
                                loadPageList();
                            } else {
                                alert('Error creating page: ' + (data.data || 'Unknown error'));
                            }
                        });
                    });

                    // Delete page quickly
                    function deletePageQuick(pageId) {
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'sdtb_delete_page',
                                page_id: pageId,
                                book_id: bookId,
                                nonce: document.querySelector('input[name="sdtb_page_editor_nonce"]').value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                loadPageList();
                                document.getElementById('sdtb-editor-area').style.display = 'none';
                                document.getElementById('sdtb-no-selection').style.display = 'block';
                            } else {
                                alert('Error deleting page: ' + (data.data || 'Unknown error'));
                            }
                        });
                    }

                    // Update total pages count
                    function updateTotalPages() {
                        const count = document.querySelectorAll('.sdtb-page-item').length;
                        document.getElementById('sdtb-total-pages').textContent = count;
                    }

                    // Drag and drop reordering
                    let draggedElement = null;

                    function setupDragAndDrop() {
                        const pageItems = document.querySelectorAll('.sdtb-page-item');

                        pageItems.forEach(item => {
                            item.addEventListener('dragstart', function(e) {
                                draggedElement = this;
                                this.style.opacity = '0.5';
                                e.dataTransfer.effectAllowed = 'move';
                            });

                            item.addEventListener('dragend', function(e) {
                                this.style.opacity = '1';
                                draggedElement = null;
                            });

                            item.addEventListener('dragover', function(e) {
                                e.preventDefault();
                                e.dataTransfer.dropEffect = 'move';
                                if (this !== draggedElement) {
                                    this.style.borderTop = '3px solid #0073aa';
                                }
                            });

                            item.addEventListener('dragleave', function(e) {
                                this.style.borderTop = '1px solid #ddd';
                            });

                            item.addEventListener('drop', function(e) {
                                e.preventDefault();
                                this.style.borderTop = '1px solid #ddd';

                                if (this !== draggedElement) {
                                    // Swap pages
                                    const draggedPageNum = parseInt(draggedElement.dataset.pageNum);
                                    const targetPageNum = parseInt(this.dataset.pageNum);

                                    reorderPages(draggedPageNum, targetPageNum);
                                }
                            });
                        });
                    }

                    // Reorder pages
                    function reorderPages(fromPageNum, toPageNum) {
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'sdtb_reorder_pages',
                                book_id: bookId,
                                from_page_num: fromPageNum,
                                to_page_num: toPageNum,
                                nonce: document.querySelector('input[name="sdtb_page_editor_nonce"]').value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                loadPageList();
                            } else {
                                alert('Error reordering pages: ' + (data.data || 'Unknown error'));
                                loadPageList();
                            }
                        });
                    }

                    // Update content statistics
                    function updateContentStats() {
                        let content = '';
                        if (typeof tinymce !== 'undefined' && tinymce.get('sdtb-edit-page-content')) {
                            content = tinymce.get('sdtb-edit-page-content').getContent();
                        } else {
                            content = document.getElementById('sdtb-edit-page-content').value;
                        }

                        const charCount = content.length;
                        const wordCount = content.trim().split(/\s+/).filter(w => w).length;
                        document.getElementById('sdtb-char-count').textContent = charCount;
                        document.getElementById('sdtb-word-count').textContent = wordCount;
                    }

                    // Save page content
                    document.getElementById('sdtb-save-page-btn').addEventListener('click', function() {
                        if (!currentEditPage) return;

                        let content = '';
                        if (typeof tinymce !== 'undefined' && tinymce.get('sdtb-edit-page-content')) {
                            content = tinymce.get('sdtb-edit-page-content').getContent();
                        } else {
                            content = document.getElementById('sdtb-edit-page-content').value;
                        }

                        if (!content.trim()) {
                            alert('Page content cannot be empty');
                            return;
                        }

                        const btn = this;
                        btn.disabled = true;
                        btn.textContent = '⏳ Saving...';

                        const pageTitle = document.getElementById('sdtb-edit-page-title').value.trim();

                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'sdtb_save_page_content',
                                page_id: currentEditPage,
                                book_id: bookId,
                                page_content: content,
                                page_title: pageTitle,
                                nonce: document.querySelector('input[name="sdtb_page_editor_nonce"]').value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            btn.disabled = false;
                            btn.textContent = '💾 Save Changes';
                            const statusDiv = document.getElementById('sdtb-save-status');

                            if (data.success) {
                                statusDiv.style.background = '#d4edda';
                                statusDiv.style.color = '#155724';
                                statusDiv.style.borderLeft = '4px solid #28a745';
                                statusDiv.innerHTML = '✅ ' + (data.data.message || 'Page saved successfully!');
                                statusDiv.style.display = 'block';

                                // Reload page list
                                setTimeout(loadPageList, 1000);

                                // Clear status message after 3 seconds
                                setTimeout(() => {
                                    statusDiv.style.display = 'none';
                                }, 3000);
                            } else {
                                statusDiv.style.background = '#f8d7da';
                                statusDiv.style.color = '#721c24';
                                statusDiv.style.borderLeft = '4px solid #f5c6cb';
                                statusDiv.innerHTML = '❌ Error: ' + (data.data || 'Failed to save page');
                                statusDiv.style.display = 'block';
                            }
                        })
                        .catch(err => {
                            btn.disabled = false;
                            btn.textContent = '💾 Save Changes';
                            const statusDiv = document.getElementById('sdtb-save-status');
                            statusDiv.style.background = '#f8d7da';
                            statusDiv.style.color = '#721c24';
                            statusDiv.style.borderLeft = '4px solid #f5c6cb';
                            statusDiv.innerHTML = '❌ Error: ' + err.message;
                            statusDiv.style.display = 'block';
                        });
                    });

                    // Cancel edit
                    document.getElementById('sdtb-cancel-edit-btn').addEventListener('click', function() {
                        document.getElementById('sdtb-editor-area').style.display = 'none';
                        document.getElementById('sdtb-no-selection').style.display = 'block';
                        document.querySelectorAll('.sdtb-page-btn').forEach(btn => {
                            btn.style.background = 'white';
                            btn.style.borderColor = '#ddd';
                        });
                        currentEditPage = null;
                    });

                    // Delete page
                    document.getElementById('sdtb-delete-page-btn').addEventListener('click', function() {
                        if (!currentEditPage) return;
                        const pageNum = document.getElementById('sdtb-edit-page-number').value;

                        if (!confirm(`Are you sure you want to delete page ${pageNum}? This action cannot be undone.`)) {
                            return;
                        }

                        const btn = this;
                        btn.disabled = true;
                        btn.textContent = '🗑️ Deleting...';

                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'sdtb_delete_page',
                                page_id: currentEditPage,
                                book_id: bookId,
                                nonce: document.querySelector('input[name="sdtb_page_editor_nonce"]').value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                alert('Page deleted successfully. Refreshing...');
                                location.reload();
                            } else {
                                btn.disabled = false;
                                btn.textContent = '🗑️ Delete Page';
                                alert('Error: ' + (data.data || 'Failed to delete page'));
                            }
                        });
                    });

                    // Update stats on content change (for textarea fallback)
                    const textarea = document.getElementById('sdtb-edit-page-content');
                    if (textarea) {
                        textarea.addEventListener('input', updateContentStats);
                    }

                    // Also update stats when TinyMCE content changes
                    if (typeof tinymce !== 'undefined') {
                        tinymce.on('AddEditor', function(e) {
                            e.editor.on('change', updateContentStats);
                        });
                    }

                    // Initial load
                    loadPageList();
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: Get list of pages for a book
     */
    public function ajax_get_page_list() {
        check_ajax_referer('sdtb_page_editor_nonce', 'nonce', false);

        $book_id = intval($_POST['book_id'] ?? 0);

        if ($book_id <= 0) {
            wp_send_json_error('Invalid book ID');
        }

        if (!current_user_can('edit_post', $book_id)) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, page_number, page_title FROM {$table} WHERE book_id = %d ORDER BY page_number ASC",
            $book_id
        ));

        wp_send_json_success($pages);
    }

    /**
     * AJAX: Get single page content
     */
    public function ajax_get_page_content() {
        check_ajax_referer('sdtb_page_editor_nonce', 'nonce', false);

        $book_id = intval($_POST['book_id'] ?? 0);
        $page_number = intval($_POST['page_number'] ?? 0);

        if ($book_id <= 0 || $page_number <= 0) {
            wp_send_json_error('Invalid parameters');
        }

        if (!current_user_can('edit_post', $book_id)) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        $page = $wpdb->get_row($wpdb->prepare(
            "SELECT id, page_number, page_title, page_content FROM {$table} WHERE book_id = %d AND page_number = %d",
            $book_id,
            $page_number
        ));

        if (!$page) {
            wp_send_json_error('Page not found');
        }

        wp_send_json_success($page);
    }

    /**
     * AJAX: Save page content
     */
    public function ajax_save_page_content() {
        check_ajax_referer('sdtb_page_editor_nonce', 'nonce', false);

        $page_id = intval($_POST['page_id'] ?? 0);
        $book_id = intval($_POST['book_id'] ?? 0);
        $page_content = wp_kses_post($_POST['page_content'] ?? '');
        $page_title = sanitize_text_field($_POST['page_title'] ?? '');

        if ($page_id <= 0 || $book_id <= 0) {
            wp_send_json_error('Invalid parameters');
        }

        if (!current_user_can('edit_post', $book_id)) {
            wp_send_json_error('Insufficient permissions');
        }

        if (empty($page_content)) {
            wp_send_json_error('Page content cannot be empty');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        $result = $wpdb->update(
            $table,
            [
                'page_title' => $page_title,
                'page_content' => $page_content,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $page_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            error_log('SDTB Page Edit Error: Failed to update page ' . $page_id);
            wp_send_json_error('Failed to save page');
        }

        error_log('SDTB Page Edit: Page ' . $page_id . ' saved successfully (Title: ' . sanitize_text_field($page_title) . ')');

        wp_send_json_success([
            'message' => 'Page updated successfully',
            'page_id' => $page_id,
        ]);
    }

    /**
     * AJAX: Delete page
     */
    public function ajax_delete_page() {
        check_ajax_referer('sdtb_page_editor_nonce', 'nonce', false);

        $page_id = intval($_POST['page_id'] ?? 0);
        $book_id = intval($_POST['book_id'] ?? 0);

        if ($page_id <= 0 || $book_id <= 0) {
            wp_send_json_error('Invalid parameters');
        }

        if (!current_user_can('edit_post', $book_id)) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        // Get page info before deletion
        $page = $wpdb->get_row($wpdb->prepare(
            "SELECT page_number FROM {$table} WHERE id = %d AND book_id = %d",
            $page_id,
            $book_id
        ));

        if (!$page) {
            wp_send_json_error('Page not found');
        }

        // Delete the page
        $result = $wpdb->delete($table, ['id' => $page_id], ['%d']);

        if ($result === false) {
            error_log('SDTB Page Delete Error: Failed to delete page ' . $page_id);
            wp_send_json_error('Failed to delete page');
        }

        // Update total pages metadata
        $total_pages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE book_id = %d",
            $book_id
        ));
        update_post_meta($book_id, '_sdtb_total_pages', intval($total_pages));

        error_log('SDTB Page Delete: Page ' . $page_id . ' (Page #' . $page->page_number . ') deleted successfully');

        wp_send_json_success([
            'message' => 'Page deleted successfully',
            'remaining_pages' => intval($total_pages),
        ]);
    }

    /**
     * AJAX: Create new page
     */
    public function ajax_create_page() {
        check_ajax_referer('sdtb_page_editor_nonce', 'nonce', false);

        $book_id = intval($_POST['book_id'] ?? 0);

        if ($book_id <= 0) {
            wp_send_json_error('Invalid book ID');
        }

        if (!current_user_can('edit_post', $book_id)) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        // Get the next page number - find the first gap in numbering
        $pages = $wpdb->get_col($wpdb->prepare(
            "SELECT page_number FROM {$table} WHERE book_id = %d ORDER BY page_number ASC",
            $book_id
        ));

        if (empty($pages)) {
            $next_page_num = 1;
        } else {
            // Find first gap in sequence
            $next_page_num = 1;
            foreach ($pages as $page_num) {
                if ($page_num == $next_page_num) {
                    $next_page_num++;
                } else {
                    // Found a gap
                    break;
                }
            }
        }

        // Create new page with blank content
        $result = $wpdb->insert($table, [
            'book_id' => $book_id,
            'page_number' => intval($next_page_num),
            'page_title' => '',
            'page_content' => '<p>New page content goes here...</p>',
        ], ['%d', '%d', '%s', '%s']);

        if ($result === false) {
            error_log('SDTB Page Create Error: Failed to create page for book ' . $book_id);
            wp_send_json_error('Failed to create page');
        }

        // Update total pages metadata
        $total_pages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE book_id = %d",
            $book_id
        ));
        update_post_meta($book_id, '_sdtb_total_pages', intval($total_pages));

        error_log('SDTB Page Create: New page created (Page #' . $next_page_num . ') for book ' . $book_id);

        wp_send_json_success([
            'message' => 'Page created successfully',
            'page_number' => intval($next_page_num),
            'total_pages' => intval($total_pages),
        ]);
    }

    /**
     * AJAX: Reorder pages
     */
    public function ajax_reorder_pages() {
        check_ajax_referer('sdtb_page_editor_nonce', 'nonce', false);

        $book_id = intval($_POST['book_id'] ?? 0);
        $from_page_num = intval($_POST['from_page_num'] ?? 0);
        $to_page_num = intval($_POST['to_page_num'] ?? 0);

        if ($book_id <= 0 || $from_page_num <= 0 || $to_page_num <= 0) {
            wp_send_json_error('Invalid parameters');
        }

        if (!current_user_can('edit_post', $book_id)) {
            wp_send_json_error('Insufficient permissions');
        }

        if ($from_page_num === $to_page_num) {
            wp_send_json_success(['message' => 'No reordering needed']);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sdtb_book_pages';

        // Get all pages for this book
        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, page_number FROM {$table} WHERE book_id = %d ORDER BY page_number ASC",
            $book_id
        ));

        if (empty($pages)) {
            wp_send_json_error('No pages found');
        }

        // Create a new numbering based on the swap
        $new_order = [];
        foreach ($pages as $page) {
            if ($page->page_number == $from_page_num) {
                $new_order[$page->id] = $to_page_num;
            } elseif ($page->page_number == $to_page_num) {
                $new_order[$page->id] = $from_page_num;
            } else {
                $new_order[$page->id] = $page->page_number;
            }
        }

        // Update all pages with new numbers
        foreach ($new_order as $page_id => $new_page_num) {
            $wpdb->update(
                $table,
                ['page_number' => $new_page_num],
                ['id' => $page_id],
                ['%d'],
                ['%d']
            );
        }

        error_log('SDTB Pages Reordered: Swapped page ' . $from_page_num . ' with page ' . $to_page_num . ' for book ' . $book_id);

        wp_send_json_success([
            'message' => 'Pages reordered successfully',
        ]);
    }
}
