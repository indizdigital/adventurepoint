<?php
/**
 * Title: Footer content
 * Slug: adventurepoint/footer-content
 * Categories: footer
 * Inserter: no
 */
?>
<!-- wp:group {"tagName":"footer","className":"ap-footer","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|40"}},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"backgroundColor":"primary-dark","textColor":"base","layout":{"type":"constrained","contentSize":"1200px"}} -->
<footer class="wp-block-group ap-footer has-base-color has-primary-dark-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|50"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"40%"} -->
		<div class="wp-block-column" style="flex-basis:40%">
			<!-- wp:image {"width":"210px","sizeSlug":"full","linkDestination":"none","className":"ap-footer__logo"} -->
			<figure class="wp-block-image size-full is-resized ap-footer__logo"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-white.png' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="393" height="84" style="width:210px"/></figure>
			<!-- /wp:image -->
			<!-- wp:paragraph {"style":{"color":{"text":"#cfe3e8"}}} -->
			<p class="has-text-color" style="color:#cfe3e8"><?php esc_html_e( 'Canoe and kayak rental right on the river. Rent by the hour or for multi-day tours – including life vests, dry bags and everything you need.', 'adventurepoint' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":4,"textColor":"base","fontSize":"medium"} -->
			<h4 class="wp-block-heading has-base-color has-text-color has-medium-font-size"><?php esc_html_e( 'Opening hours', 'adventurepoint' ); ?></h4>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"style":{"color":{"text":"#cfe3e8"}}} -->
			<p class="has-text-color" style="color:#cfe3e8"><?php esc_html_e( 'April – October', 'adventurepoint' ); ?><br><?php esc_html_e( 'Daily 9:00 – 18:00', 'adventurepoint' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":4,"textColor":"base","fontSize":"medium"} -->
			<h4 class="wp-block-heading has-base-color has-text-color has-medium-font-size"><?php esc_html_e( 'Contact', 'adventurepoint' ); ?></h4>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"style":{"color":{"text":"#cfe3e8"}}} -->
			<p class="has-text-color" style="color:#cfe3e8">AdventurePoint<br>Flussweg 1<br><a href="tel:+41000000000">+41 00 000 00 00</a><br><a href="mailto:info@adventurepoint.local">info@adventurepoint.local</a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:html -->
			<?php echo adventurepoint_menu( 'footer', 'ap-footer__menu' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<!-- wp:separator {"style":{"color":{"background":"#2a6377"}},"className":"is-style-wide"} -->
	<hr class="wp-block-separator has-text-color has-alpha-channel-opacity has-background is-style-wide" style="background-color:#2a6377;color:#2a6377"/>
	<!-- /wp:separator -->
	<!-- wp:paragraph {"fontSize":"small","style":{"color":{"text":"#9fbfc8"}}} -->
	<p class="has-text-color has-small-font-size" style="color:#9fbfc8">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?> · <?php esc_html_e( 'Life vests are mandatory on the water.', 'adventurepoint' ); ?></p>
	<!-- /wp:paragraph -->
</footer>
<!-- /wp:group -->
