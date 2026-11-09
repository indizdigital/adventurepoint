<?php
/**
 * Title: Header bar
 * Slug: adventurepoint/header-bar
 * Categories: header
 * Inserter: no
 */
?>
<!-- wp:html -->
<header class="ap-header">
	<div class="ap-header__inner">
		<a class="ap-brand" href="<?php echo esc_url( adventurepoint_home_url() ); ?>" rel="home">
			<img class="ap-brand__logo" src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-dark.png' ) ); ?>" width="393" height="84" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		</a>
		<button class="ap-menu-toggle" type="button" aria-expanded="false" aria-controls="ap-nav" aria-label="<?php esc_attr_e( 'Menu', 'adventurepoint' ); ?>"><span></span><span></span><span></span></button>
		<nav id="ap-nav" class="ap-nav" aria-label="<?php esc_attr_e( 'Main navigation', 'adventurepoint' ); ?>">
			<?php echo adventurepoint_menu( 'primary', 'ap-nav__list' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</nav>
		<div class="ap-header__actions">
			<?php echo adventurepoint_language_switcher(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<a class="ap-cta" href="<?php echo esc_url( adventurepoint_page_url( 'ap_reservations_page_id' ) ); ?>"><?php esc_html_e( 'Book now', 'adventurepoint' ); ?></a>
		</div>
	</div>
</header>
<!-- /wp:html -->
