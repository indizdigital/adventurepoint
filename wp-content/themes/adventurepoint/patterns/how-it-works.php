<?php
/**
 * Title: How it works
 * Slug: adventurepoint/how-it-works
 * Categories: adventurepoint
 */
$steps = [
	[ __( 'Choose date & time', 'adventurepoint' ), __( 'A few hours in the afternoon or a multi-day tour – you decide.', 'adventurepoint' ) ],
	[ __( 'Pick your boats', 'adventurepoint' ), __( 'Canadian canoes for two or three, kayaks or stand-up paddle boards.', 'adventurepoint' ) ],
	[ __( 'Add equipment', 'adventurepoint' ), __( 'Life vests, waterproof dry bags, barrels and cool boxes – just add them to your booking.', 'adventurepoint' ) ],
	[ __( 'Paddle off', 'adventurepoint' ), __( 'Arrive, get a short briefing and enjoy the river. You pay on site.', 'adventurepoint' ) ],
];
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"sand","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull has-sand-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'How it works', 'adventurepoint' ); ?></h2>
	<!-- /wp:heading -->
	<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
	<p class="has-text-align-center has-muted-color has-text-color"><?php esc_html_e( 'From booking to the water in four easy steps.', 'adventurepoint' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:columns {"className":"ap-steps","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
	<div class="wp-block-columns ap-steps" style="margin-top:var(--wp--preset--spacing--50)">
	<?php foreach ( $steps as [ $title, $text ] ) : ?>
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"ap-step-card","layout":{"type":"default"}} -->
			<div class="wp-block-group ap-step-card">
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><?php echo esc_html( $title ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"textColor":"muted"} -->
				<p class="has-muted-color has-text-color"><?php echo esc_html( $text ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	<?php endforeach; ?>
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
