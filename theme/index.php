<?php
get_header();
?>

<?php if ( have_posts() ) : ?>
	<div class="prose prose-lg max-w-prose">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'mb-12' ); ?>>
				<header class="mb-6">
					<h1 class="text-3xl sm:text-4xl"><?php the_title(); ?></h1>
				</header>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	</div>
	<div class="mt-10"><?php the_posts_pagination(); ?></div>
<?php else : ?>
	<p class="text-muted"><?php esc_html_e( 'Nothing here yet.', 'delopay-shop' ); ?></p>
<?php endif; ?>

<?php get_footer(); ?>
