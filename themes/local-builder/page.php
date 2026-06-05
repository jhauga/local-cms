<?php
declare(strict_types=1);
?>
<?php get_header(); ?>
<div class="with-sidebar">
    <main id="content" class="site-main">
        <?php if (have_posts()): ?>
            <?php the_post(); ?>
            <?php get_template_part('template-parts/content', 'article', ['kind' => 'page']); ?>
            <?php rewind_posts(); ?>
        <?php endif; ?>
    </main>
    <?php if (function_exists('dynamic_sidebar')) { get_sidebar(); } else { get_template_part('sidebar'); } ?>
</div>
<?php get_footer(); ?>
