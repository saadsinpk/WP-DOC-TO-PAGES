<?php
get_header();
?>

<main id="main" class="site-main">
    <div class="container">
        <div class="sdtb-archive">
    <h1><?php post_type_archive_title(); ?></h1>

    <?php if (have_posts()): ?>
        <div class="sdtb-books-grid">
            <?php while (have_posts()): the_post(); ?>
                <div class="sdtb-book-card">
                    <?php if (has_post_thumbnail()): ?>
                        <div class="sdtb-card-thumbnail">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('medium'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="sdtb-card-body">
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

                        <?php
                        $author = get_post_meta(get_the_ID(), '_sdtb_author', true);
                        $total_pages = get_post_meta(get_the_ID(), '_sdtb_total_pages', true);
                        ?>

                        <?php if ($author): ?>
                            <p class="sdtb-card-author"><?php echo esc_html($author); ?></p>
                        <?php endif; ?>

                        <?php if ($total_pages): ?>
                            <p class="sdtb-card-pages">
                                <?php printf(_n('%d page', '%d pages', $total_pages, 'sid-doc-to-book'), intval($total_pages)); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (has_excerpt()): ?>
                            <div class="sdtb-card-excerpt"><?php the_excerpt(); ?></div>
                        <?php endif; ?>

                        <a href="<?php the_permalink(); ?>" class="sdtb-read-btn">
                            <?php _e('Read Book', 'sid-doc-to-book'); ?> &rarr;
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else: ?>
        <p><?php _e('No books found.', 'sid-doc-to-book'); ?></p>
    <?php endif; ?>
        </div>
    </div>
</main>

<?php get_footer();
