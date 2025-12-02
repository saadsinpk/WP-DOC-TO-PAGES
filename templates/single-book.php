<?php
get_header();

$book_id = get_the_ID();
$book = Book_Manager::get_book($book_id);

// Get page number using WordPress custom query var
$current_page = intval(get_query_var('sdtb_page', 1));
if ($current_page < 1) {
    $current_page = 1;
}

$page = Book_Manager::get_page($book_id, $current_page);

// Always count actual pages in database, not metadata
$total_pages = Book_Manager::get_total_pages($book_id);

// Update metadata if it doesn't match actual page count
if (intval($book['total_pages']) !== $total_pages) {
    update_post_meta($book_id, '_sdtb_total_pages', $total_pages);
}

$language = $book['language'] ?? 'urdu';
$is_rtl = in_array($language, ['urdu', 'arabic', 'mixed']);

// Build current page URL for sharing
$current_url = get_permalink($book_id);
if ($current_page > 1) {
    $current_url = add_query_arg('sdtb_page', $current_page, $current_url);
}
$share_title = $book['title'];
if ($page && $page->page_title) {
    $share_title .= ' - ' . $page->page_title;
}
?>

<main id="main" class="site-main">
    <div class="container">
        <div class="sdtb-book-container" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" data-book-id="<?php echo intval($book_id); ?>" data-language="<?php echo esc_attr($language); ?>">
    <div class="sdtb-book-header">
        <div class="sdtb-book-info">
            <?php if ($book['thumbnail']): ?>
                <div class="sdtb-book-thumbnail">
                    <img src="<?php echo esc_url($book['thumbnail']); ?>" alt="<?php echo esc_attr($book['title']); ?>">
                </div>
            <?php endif; ?>

            <div class="sdtb-book-meta">
                <h1><?php echo esc_html($book['title']); ?></h1>
                <?php if ($book['author']): ?>
                    <p class="sdtb-author">
                        <strong><?php _e('Author:', 'sid-doc-to-book'); ?></strong>
                        <?php echo esc_html($book['author']); ?>
                    </p>
                <?php endif; ?>
                <p class="sdtb-pages">
                    <strong><?php _e('Total Pages:', 'sid-doc-to-book'); ?></strong>
                    <?php echo intval($total_pages); ?>
                </p>
                <?php if ($book['excerpt']): ?>
                    <div class="sdtb-excerpt"><?php echo wp_kses_post($book['excerpt']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search functionality -->
        <div class="sdtb-search-box">
            <input type="text" id="sdtb-search" placeholder="<?php _e('Search in book...', 'sid-doc-to-book'); ?>" class="sdtb-search-input">
            <button type="button" id="sdtb-search-btn" class="sdtb-search-button">
                <?php _e('Search', 'sid-doc-to-book'); ?>
            </button>
            <div id="sdtb-search-results" class="sdtb-search-results"></div>
        </div>
    </div>

    <!-- Page viewer -->
    <div class="sdtb-reader">
        <?php if ($page): ?>
            <div class="sdtb-page" id="sdtb-page-<?php echo intval($page->page_number); ?>">
                <div class="sdtb-page-number">
                    <?php printf(__('Page %d of %d', 'sid-doc-to-book'), intval($current_page), intval($total_pages)); ?>
                </div>

                <?php if ($page->page_title): ?>
                    <h2 class="sdtb-page-title"><?php echo esc_html($page->page_title); ?></h2>
                <?php endif; ?>

                <div class="sdtb-page-content">
                    <!-- Content is selectable and copyable -->
                    <!-- Check if content contains HTML formatting -->
                    <?php
                        if (strpos($page->page_content, '<') !== false && strpos($page->page_content, '>') !== false) {
                            // Content has HTML formatting, display as-is
                            echo wp_kses_post($page->page_content);
                        } else {
                            // Plain text, add line breaks
                            echo nl2br(esc_html($page->page_content));
                        }
                    ?>
                </div>

                <div class="sdtb-page-selection-tools">
                    <button type="button" class="sdtb-select-all">
                        <?php _e('Select All', 'sid-doc-to-book'); ?>
                    </button>
                    <button type="button" class="sdtb-copy-selection">
                        <?php _e('Copy Selected', 'sid-doc-to-book'); ?>
                    </button>
                </div>

                <!-- Share & Print Buttons -->
                <div class="sdtb-share-print-tools">
                    <div class="sdtb-share-buttons">
                        <span class="sdtb-share-label"><?php _e('Share:', 'sid-doc-to-book'); ?></span>

                        <!-- WhatsApp -->
                        <a href="https://wa.me/?text=<?php echo urlencode($share_title . ' ' . $current_url); ?>"
                           target="_blank"
                           class="sdtb-share-btn sdtb-share-whatsapp"
                           title="<?php _e('Share on WhatsApp', 'sid-doc-to-book'); ?>">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </a>

                        <!-- Facebook -->
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>"
                           target="_blank"
                           class="sdtb-share-btn sdtb-share-facebook"
                           title="<?php _e('Share on Facebook', 'sid-doc-to-book'); ?>">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>

                        <!-- Twitter/X -->
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($share_title); ?>&url=<?php echo urlencode($current_url); ?>"
                           target="_blank"
                           class="sdtb-share-btn sdtb-share-twitter"
                           title="<?php _e('Share on Twitter', 'sid-doc-to-book'); ?>">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>

                        <!-- Copy Link -->
                        <button type="button"
                                class="sdtb-share-btn sdtb-share-copy"
                                data-url="<?php echo esc_attr($current_url); ?>"
                                title="<?php _e('Copy Link', 'sid-doc-to-book'); ?>">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Print Button -->
                    <button type="button" class="sdtb-print-btn" title="<?php _e('Print this page', 'sid-doc-to-book'); ?>">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                        </svg>
                        <?php _e('Print', 'sid-doc-to-book'); ?>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <p class="sdtb-no-content"><?php _e('Page not found', 'sid-doc-to-book'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Navigation -->
    <div class="sdtb-navigation">
        <div class="sdtb-nav-buttons">
            <?php
            // Build pagination URLs with query parameters
            $book_url = get_permalink($book_id);
            $prev_page = $current_page - 1;
            $next_page = $current_page + 1;
            ?>

            <?php if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('sdtb_page', $prev_page, $book_url)); ?>" class="sdtb-nav-prev">
                    &larr; <?php _e('Previous', 'sid-doc-to-book'); ?>
                </a>
            <?php endif; ?>

            <div class="sdtb-page-input">
                <label for="sdtb-go-to-page"><?php _e('Go to page:', 'sid-doc-to-book'); ?></label>
                <input type="number" id="sdtb-go-to-page" min="1" max="<?php echo intval($total_pages); ?>" value="<?php echo intval($current_page); ?>" class="sdtb-page-number-input" data-base-url="<?php echo esc_attr($book_url); ?>">
                <button type="button" class="sdtb-go-btn"><?php _e('Go', 'sid-doc-to-book'); ?></button>
            </div>

            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg('sdtb_page', $next_page, $book_url)); ?>" class="sdtb-nav-next">
                    <?php _e('Next', 'sid-doc-to-book'); ?> &rarr;
                </a>
            <?php endif; ?>
        </div>

        <!-- Page indicator -->
        <div class="sdtb-progress">
            <div class="sdtb-progress-bar" style="width: <?php echo intval(($current_page / $total_pages) * 100); ?>%"></div>
        </div>
    </div>
        </div>
    </div>
</main>

<?php get_footer();
