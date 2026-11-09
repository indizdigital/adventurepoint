<?php
/**
 * Title: Hero – river
 * Slug: adventurepoint/hero
 * Categories: adventurepoint, banner
 * Description: Full-width hero with booking call to action.
 */
$book = adventurepoint_page_url( 'ap_reservations_page_id' );
$boats = adventurepoint_page_url( 'ap_canoes_page_id' );
$img  = get_theme_file_uri( 'assets/img/hero.svg' );
?>
<!-- wp:cover {"url":"<?php echo esc_url( $img ); ?>","dimRatio":20,"overlayColor":"primary-dark","minHeight":78,"minHeightUnit":"vh","contentPosition":"center left","align":"full","className":"ap-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-center-left ap-hero" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);min-height:78vh"><span aria-hidden="true" class="wp-block-cover__background has-primary-dark-background-color has-background-dim-20 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( $img ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
	<!-- wp:paragraph {"className":"ap-eyebrow"} -->
	<p class="ap-eyebrow"><?php esc_html_e( 'Canoe & kayak rental', 'adventurepoint' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:heading {"level":1} -->
	<h1 class="wp-block-heading"><?php esc_html_e( 'Your adventure on the river starts here', 'adventurepoint' ); ?></h1>
	<!-- /wp:heading -->
	<!-- wp:paragraph {"fontSize":"large"} -->
	<p class="has-large-font-size"><?php esc_html_e( 'Rent a canoe for a few hours or several days. Book online in two minutes – life vests and dry bags are ready for you.', 'adventurepoint' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $book ); ?>"><?php esc_html_e( 'Book a canoe', 'adventurepoint' ); ?></a></div>
		<!-- /wp:button -->
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $boats ); ?>"><?php esc_html_e( 'See our boats', 'adventurepoint' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->
