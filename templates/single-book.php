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
