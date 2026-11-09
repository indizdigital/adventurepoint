<?php
/**
 * Title: Our canoes
 * Slug: adventurepoint/canoes-section
 * Categories: adventurepoint
 * Description: Canoe overview from the AP Reservations plugin.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php esc_html_e( 'Our boats', 'adventurepoint' ); ?></h2>
	<!-- /wp:heading -->
	<!-- wp:paragraph {"textColor":"muted"} -->
	<p class="has-muted-color has-text-color"><?php esc_html_e( 'Well-maintained boats for beginners, families and experienced paddlers. Paddles are always included.', 'adventurepoint' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:shortcode -->
	[ap_canoes]
	<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
