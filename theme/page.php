<?php
get_header();

$delopay_shop_plugin_shortcodes = array( 'delopay_products', 'delopay_cart', 'delopay_checkout', 'delopay_complete' );

while ( have_posts() ) :
	the_post();

	$delopay_shop_content        = get_the_content();
	$delopay_shop_is_plugin_page = false;
	foreach ( $delopay_shop_plugin_shortcodes as $delopay_shop_shortcode ) {
		if ( has_shortcode( $delopay_shop_content, $delopay_shop_shortcode ) ) {
			$delopay_shop_is_plugin_page = true;
			break;
		}
	}
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php if ( ! $delopay_shop_is_plugin_page ) : ?>
			<header class="mb-10">
				<h1 class="text-3xl sm:text-4xl md:text-5xl"><?php the_title(); ?></h1>
			</header>
		<?php endif; ?>

		<div class="prose prose-lg max-w-none">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
