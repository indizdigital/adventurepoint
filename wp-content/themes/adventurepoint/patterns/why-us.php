<?php
/**
 * Title: Why paddle with us
 * Slug: adventurepoint/why-us
 * Categories: adventurepoint
 */
$features = [
	[ __( 'Safety first', 'adventurepoint' ), __( 'Certified life vests in all sizes, including for children, and a briefing before every tour.', 'adventurepoint' ) ],
	[ __( 'Everything stays dry', 'adventurepoint' ), __( 'Waterproof bags and barrels keep your phone, clothes and picnic dry.', 'adventurepoint' ) ],
	[ __( 'Flexible rental', 'adventurepoint' ), __( 'By the hour or by the day, alone or with the whole group.', 'adventurepoint' ) ],
];
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"gradient":"river","textColor":"base","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull has-base-color has-river-gradient-background has-text-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:heading {"textColor":"base"} -->
	<h2 class="wp-block-heading has-base-color has-text-color"><?php esc_html_e( 'Why paddle with us?', 'adventurepoint' ); ?></h2>
	<!-- /wp:heading -->
	<!-- wp:columns -->
	<div class="wp-block-columns">
	<?php foreach ( $features as [ $title, $text ] ) : ?>
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"ap-feature","layout":{"type":"default"}} -->
			<div class="wp-block-group ap-feature">
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><?php echo esc_html( $title ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"style":{"color":{"text":"#d7ecf1"}}} -->
				<p class="has-text-color" style="color:#d7ecf1"><?php echo esc_html( $text ); ?></p>
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
