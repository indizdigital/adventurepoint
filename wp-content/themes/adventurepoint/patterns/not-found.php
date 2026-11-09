<?php
/**
 * Title: Not found
 * Slug: adventurepoint/not-found
 * Inserter: no
 */
?>
<!-- wp:heading {"level":1,"textAlign":"center"} -->
<h1 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Drifted off course', 'adventurepoint' ); ?></h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php esc_html_e( 'This page does not exist (anymore). Paddle back to the start page.', 'adventurepoint' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( adventurepoint_home_url() ); ?>"><?php esc_html_e( 'Back to the start page', 'adventurepoint' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
