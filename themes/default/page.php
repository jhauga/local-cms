<?php
declare(strict_types=1);
?>
<?php get_header(); ?>
<main id="content" class="site-main narrow-layout">
    <?php if (have_posts()): ?>
        <?php the_post(); ?>
        <?php get_template_part('template-parts/content', 'article', ['kind' => 'page']); ?>
        <?php rewind_posts(); ?>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
