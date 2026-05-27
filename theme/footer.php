<?php
$delopay_shop_show_badge = delopay_shop_customizer_get( 'footer_show_badge' );
?>
	</div><!-- .ds-container -->
</main>

<footer class="ds-footer">
	<?php if ( has_nav_menu( 'footer' ) ) : ?>
		<nav class="ds-footer-nav" aria-label="<?php esc_attr_e( 'Footer', 'delopay-shop' ); ?>">
			<div class="ds-footer-nav-inner">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => '',
						'fallback_cb'    => false,
						'menu_class'     => 'ds-menu ds-menu-footer',
						'depth'          => 2,
					)
				);
				?>
			</div>
		</nav>
	<?php endif; ?>
	<div class="ds-footer-inner">
		<span><?php echo wp_kses_post( delopay_shop_footer_copy() ); ?></span>
		<?php if ( $delopay_shop_show_badge ) : ?>
			<span><?php esc_html_e( 'Payments by DeloPay', 'delopay-shop' ); ?></span>
		<?php endif; ?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
