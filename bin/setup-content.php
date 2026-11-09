<?php
/**
 * Demo content for AdventurePoint. Run via: wp eval-file /scripts/setup-content.php
 * Idempotent: languages/pages/items are only created when missing (matched by slug).
 */

use AP\Reservations\Settings;

if ( ! function_exists( 'PLL' ) || ! function_exists( 'pll_set_post_language' ) ) {
	WP_CLI::error( 'Polylang is not active.' );
}

/* ------------------------------------------------------------------ languages */

$model = PLL()->model;
$wanted = [
	[ 'name' => 'Deutsch', 'slug' => 'de', 'locale' => 'de_DE', 'rtl' => 0, 'term_group' => 0, 'flag' => 'ch' ],
	[ 'name' => 'Français', 'slug' => 'fr', 'locale' => 'fr_FR', 'rtl' => 0, 'term_group' => 1, 'flag' => 'ch' ],
];
foreach ( $wanted as $lang ) {
	if ( $model->get_language( $lang['slug'] ) ) {
		continue;
	}
	$result = isset( $model->languages ) && method_exists( $model->languages, 'add' )
		? $model->languages->add( $lang )
		: $model->add_language( $lang );
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( 'Language ' . $lang['slug'] . ': ' . $result->get_error_message() );
	}
	WP_CLI::log( 'Added language ' . $lang['slug'] );
}
$model->clean_languages_cache();

// URL setup: /de/… and /fr/…, detect browser language on the home page, translate our post types.
$options = get_option( 'polylang', [] );
$options = array_merge( is_array( $options ) ? $options : [], [
	'default_lang'  => 'de',
	'force_lang'    => 1,
	'rewrite'       => 1,
	'hide_default'  => 0,
	'redirect_lang' => 1,
	'browser'       => 1,
	'media_support' => 0,
	'post_types'    => array_values( array_unique( array_merge( (array) ( $options['post_types'] ?? [] ), [ 'ap_canoe', 'ap_extra' ] ) ) ),
] );
update_option( 'polylang', $options );
delete_transient( 'pll_languages_list' );
if ( isset( PLL()->options ) && is_object( PLL()->options ) && method_exists( PLL()->options, 'set' ) ) {
	foreach ( [ 'default_lang', 'force_lang', 'rewrite', 'hide_default', 'redirect_lang', 'browser', 'post_types' ] as $k ) {
		PLL()->options->set( $k, $options[ $k ] );
	}
	if ( method_exists( PLL()->options, 'save' ) ) {
		PLL()->options->save();
	}
}

// Polylang string translations (Languages → Translations): site title stays, tagline per language.
if ( class_exists( 'PLL_MO' ) ) {
	$strings = [ 'fr' => [ 'Kanu- und Kajakvermietung' => 'Location de canoës et kayaks', 'j. F Y' => 'j F Y' ] ];
	foreach ( $strings as $slug => $pairs ) {
		$language = $model->get_language( $slug );
		$mo       = new PLL_MO();
		$mo->import_from_db( $language );
		foreach ( $pairs as $source => $translation ) {
			$mo->add_entry( $mo->make_entry( $source, $translation ) );
		}
		$mo->export_to_db( $language );
	}
}

/* ------------------------------------------------------------------ helpers */

/**
 * Create (or find) a post per language and link them as translations.
 *
 * @param array $per_lang lang => [title, slug, content, excerpt]
 * @return array<string,int> lang => post ID
 */
function ap_demo_post( string $type, array $per_lang, array $meta = [], int $order = 0 ): array {
	$ids = [];
	foreach ( $per_lang as $lang => $p ) {
		$existing = get_posts( [ 'post_type' => $type, 'name' => $p['slug'], 'post_status' => 'any', 'numberposts' => 1, 'lang' => '' ] );
		if ( $existing ) {
			$id = $existing[0]->ID;
		} else {
			$id = wp_insert_post( [
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $p['title'],
				'post_name'    => $p['slug'],
				'post_content' => $p['content'] ?? '',
				'post_excerpt' => $p['excerpt'] ?? '',
				'menu_order'   => $order,
			], true );
			if ( is_wp_error( $id ) ) {
				WP_CLI::error( $id->get_error_message() );
			}
			WP_CLI::log( "Created {$type} {$p['title']} ({$lang})" );
		}
		pll_set_post_language( $id, $lang );
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, '_ap_' . $k, $v );
		}
		$ids[ $lang ] = $id;
	}
	pll_save_post_translations( $ids );
	return $ids;
}

$p = static fn( string $text ) => "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
$h = static fn( string $text ) => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$text}</h2>\n<!-- /wp:heading -->";
$sc = static fn( string $code ) => "<!-- wp:shortcode -->\n{$code}\n<!-- /wp:shortcode -->";
$pattern = static fn( string $slug ) => '<!-- wp:pattern {"slug":"adventurepoint/' . $slug . '"} /-->';

/* ------------------------------------------------------------------ pages */

$home_content = implode( "\n\n", array_map( $pattern, [ 'hero', 'how-it-works', 'canoes-section', 'why-us', 'cta-banner' ] ) );

$home = ap_demo_post( 'page', [
	'de' => [ 'title' => 'Startseite', 'slug' => 'startseite', 'content' => $home_content ],
	'fr' => [ 'title' => 'Accueil', 'slug' => 'accueil', 'content' => $home_content ],
] );

$canoes_page = ap_demo_post( 'page', [
	'de' => [ 'title' => 'Unsere Boote', 'slug' => 'boote', 'content' => $p( 'Vom wendigen Einerkajak bis zum Grosskanadier für die ganze Gruppe: Alle Boote werden regelmässig gewartet, Paddel sind immer inklusive.' ) . "\n\n" . $sc( '[ap_canoes]' ) ],
	'fr' => [ 'title' => 'Nos bateaux', 'slug' => 'bateaux', 'content' => $p( 'Du kayak solo maniable au grand canoë pour tout le groupe : tous nos bateaux sont entretenus régulièrement, les pagaies sont toujours incluses.' ) . "\n\n" . $sc( '[ap_canoes]' ) ],
] );

$booking_page = ap_demo_post( 'page', [
	'de' => [ 'title' => 'Reservieren', 'slug' => 'reservieren', 'content' => $sc( '[ap_reservation]' ) ],
	'fr' => [ 'title' => 'Réserver', 'slug' => 'reserver', 'content' => $sc( '[ap_reservation]' ) ],
] );

$contact = ap_demo_post( 'page', [
	'de' => [ 'title' => 'Kontakt', 'slug' => 'kontakt', 'content' => $p( 'Fragen zu Touren, Gruppen oder Schulklassen? Wir helfen gerne weiter.' ) . "\n\n" . $p( '<strong>AdventurePoint</strong><br>Flussweg 1<br>Telefon: <a href="tel:+41000000000">+41 00 000 00 00</a><br>E-Mail: <a href="mailto:info@adventurepoint.local">info@adventurepoint.local</a>' ) . "\n\n" . $h( 'Öffnungszeiten' ) . "\n\n" . $p( 'April bis Oktober, täglich 9:00 – 18:00 Uhr. Bei Gewitter oder Hochwasser bleibt die Station geschlossen.' ) ],
	'fr' => [ 'title' => 'Contact', 'slug' => 'contact', 'content' => $p( 'Des questions sur les excursions, les groupes ou les classes ? Nous vous aidons volontiers.' ) . "\n\n" . $p( '<strong>AdventurePoint</strong><br>Flussweg 1<br>Téléphone : <a href="tel:+41000000000">+41 00 000 00 00</a><br>E-mail : <a href="mailto:info@adventurepoint.local">info@adventurepoint.local</a>' ) . "\n\n" . $h( 'Horaires' ) . "\n\n" . $p( 'D’avril à octobre, tous les jours de 9h00 à 18h00. En cas d’orage ou de crue, la station reste fermée.' ) ],
] );

$terms = ap_demo_post( 'page', [
	'de' => [ 'title' => 'AGB', 'slug' => 'agb', 'content' => $h( 'Mietbedingungen' ) . "\n\n" . $p( 'Das Tragen einer Schwimmweste ist auf dem Wasser Pflicht. Mieterinnen und Mieter müssen schwimmen können. Kinder unter 14 Jahren nur in Begleitung Erwachsener.' ) . "\n\n" . $p( 'Kostenlose Stornierung bis 24 Stunden vor Mietbeginn. Bei schlechtem Wetter oder Hochwasser kann die Vermietung durch uns abgesagt werden; bereits bezahlte Beträge werden erstattet.' ) . "\n\n" . $p( 'Für Schäden durch unsachgemässen Gebrauch haftet der Mieter. <em>(Beispieltext – bitte durch eigene AGB ersetzen.)</em>' ) ],
	'fr' => [ 'title' => 'CGV', 'slug' => 'cgv', 'content' => $h( 'Conditions de location' ) . "\n\n" . $p( 'Le port du gilet de sauvetage est obligatoire sur l’eau. Les locataires doivent savoir nager. Les enfants de moins de 14 ans doivent être accompagnés d’un adulte.' ) . "\n\n" . $p( 'Annulation gratuite jusqu’à 24 heures avant le début de la location. En cas de mauvais temps ou de crue, nous pouvons annuler la location ; les montants déjà payés sont remboursés.' ) . "\n\n" . $p( 'Le locataire est responsable des dommages dus à une utilisation inappropriée. <em>(Texte d’exemple – à remplacer par vos propres CGV.)</em>' ) ],
] );

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home['de'] );
update_option( 'ap_reservations_page_id', $booking_page['de'] );
update_option( 'ap_canoes_page_id', $canoes_page['de'] );

$settings                  = Settings::all();
$settings['terms_page_id'] = $terms['de'];
$settings['admin_email']   = get_option( 'admin_email' );
$settings['max_advance_days'] = 365;
update_option( Settings::OPTION, $settings );

/* ------------------------------------------------------------------ canoes */

$canoes = [
	[ [ 'de' => [ 'Kanadier 2er', 'kanadier-2er', 'Der Klassiker für zwei – stabil, gutmütig und ideal für Einsteiger.', 'Unser meistgemieteter Kanadier bietet Platz für zwei Erwachsene plus Gepäck. Dank breitem Rumpf liegt er sehr ruhig im Wasser.' ],
		  'fr' => [ 'Canoë canadien biplace', 'canoe-canadien-biplace', 'Le classique pour deux – stable, docile et idéal pour débuter.', 'Notre canoë le plus loué accueille deux adultes et leurs bagages. Sa coque large lui assure une grande stabilité.' ] ],
	  [ 'capacity' => 2, 'stock' => 6, 'price_hour' => 15, 'price_day' => 65, 'min_hours' => 1, 'allow_hour' => 1, 'allow_day' => 1 ] ],
	[ [ 'de' => [ 'Familienkanadier 3er', 'familienkanadier-3er', 'Platz für zwei Erwachsene und ein Kind oder drei Erwachsene.', 'Mit drei Sitzplätzen perfekt für Familien. Viel Stauraum für Picknick und Packsäcke.' ],
		  'fr' => [ 'Canoë familial 3 places', 'canoe-familial-3-places', 'De la place pour deux adultes et un enfant, ou trois adultes.', 'Avec trois places assises, idéal pour les familles. Beaucoup d’espace pour le pique-nique et les sacs étanches.' ] ],
	  [ 'capacity' => 3, 'stock' => 4, 'price_hour' => 20, 'price_day' => 80, 'min_hours' => 1, 'allow_hour' => 1, 'allow_day' => 1 ] ],
	[ [ 'de' => [ 'Kajak Einer', 'kajak-einer', 'Wendig und schnell – für alle, die alleine unterwegs sind.', 'Sit-on-top-Kajak mit bequemer Rückenlehne. Kippstabil und einfach zu paddeln.' ],
		  'fr' => [ 'Kayak solo', 'kayak-solo', 'Maniable et rapide – pour celles et ceux qui pagaient seuls.', 'Kayak sit-on-top avec dossier confortable. Stable et facile à manier.' ] ],
	  [ 'capacity' => 1, 'stock' => 5, 'price_hour' => 12, 'price_day' => 50, 'min_hours' => 1, 'allow_hour' => 1, 'allow_day' => 1 ] ],
	[ [ 'de' => [ 'Stand-up-Paddle', 'stand-up-paddle', 'Aufblasbares SUP-Board inklusive Paddel und Leash.', 'Ideal für ruhige Abschnitte und warme Sommertage. Nur stundenweise buchbar.' ],
		  'fr' => [ 'Stand-up paddle', 'stand-up-paddle-fr', 'Planche SUP gonflable avec pagaie et leash.', 'Idéal pour les tronçons calmes et les chaudes journées d’été. Uniquement à l’heure.' ] ],
	  [ 'capacity' => 1, 'stock' => 4, 'price_hour' => 15, 'price_day' => 0, 'min_hours' => 1, 'allow_hour' => 1, 'allow_day' => 0 ] ],
	[ [ 'de' => [ 'Grosskanadier (8 Personen)', 'grosskanadier', 'Das Gruppenerlebnis für Vereine, Firmen und Schulklassen.', 'Bis zu acht Personen paddeln gemeinsam in einem Boot. Mindestmietdauer 2 Stunden.' ],
		  'fr' => [ 'Grand canoë (8 personnes)', 'grand-canoe', 'L’expérience de groupe pour associations, entreprises et classes.', 'Jusqu’à huit personnes pagaient ensemble dans un même bateau. Durée minimale 2 heures.' ] ],
	  [ 'capacity' => 8, 'stock' => 1, 'price_hour' => 60, 'price_day' => 240, 'min_hours' => 2, 'allow_hour' => 1, 'allow_day' => 1 ] ],
];

foreach ( $canoes as $i => [ $langs, $meta ] ) {
	$per_lang = [];
	foreach ( $langs as $lang => [ $title, $slug, $excerpt, $content ] ) {
		$per_lang[ $lang ] = [ 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => $p( $content ) ];
	}
	ap_demo_post( 'ap_canoe', $per_lang, $meta, $i + 1 );
}

/* ------------------------------------------------------------------ extras */

$extras = [
	[ [ 'de' => [ 'Schwimmweste Erwachsene', 'schwimmweste-erwachsene', 'Pflicht auf dem Wasser – kostenlos.' ],
		  'fr' => [ 'Gilet de sauvetage adulte', 'gilet-de-sauvetage-adulte', 'Obligatoire sur l’eau – gratuit.' ] ],
	  [ 'price_flat' => 0, 'price_hour' => 0, 'price_day' => 0, 'stock' => 40, 'max_per_booking' => 12, 'per_person' => 1 ] ],
	[ [ 'de' => [ 'Kinder-Schwimmweste', 'kinder-schwimmweste', 'Für Kinder von 15–40 kg – kostenlos.' ],
		  'fr' => [ 'Gilet de sauvetage enfant', 'gilet-de-sauvetage-enfant', 'Pour enfants de 15 à 40 kg – gratuit.' ] ],
	  [ 'price_flat' => 0, 'price_hour' => 0, 'price_day' => 0, 'stock' => 12, 'max_per_booking' => 6, 'per_person' => 0 ] ],
	[ [ 'de' => [ 'Wasserdichter Packsack 20 L', 'packsack-20l', 'Für Kleider, Handtuch und Proviant.' ],
		  'fr' => [ 'Sac étanche 20 L', 'sac-etanche-20l', 'Pour vêtements, serviette et provisions.' ] ],
	  [ 'price_flat' => 0, 'price_hour' => 1, 'price_day' => 4, 'stock' => 30, 'max_per_booking' => 8, 'per_person' => 0 ] ],
	[ [ 'de' => [ 'Wasserdichte Tonne 30 L', 'tonne-30l', 'Bruchsicher – ideal für Kamera und Wertsachen.' ],
		  'fr' => [ 'Bidon étanche 30 L', 'bidon-etanche-30l', 'Incassable – idéal pour l’appareil photo et les objets de valeur.' ] ],
	  [ 'price_flat' => 0, 'price_hour' => 2, 'price_day' => 6, 'stock' => 15, 'max_per_booking' => 4, 'per_person' => 0 ] ],
	[ [ 'de' => [ 'Wasserdichte Handyhülle', 'handyhuelle', 'Zum Behalten – mit Umhängeband.' ],
		  'fr' => [ 'Pochette étanche smartphone', 'pochette-smartphone', 'À garder – avec cordon.' ] ],
	  [ 'price_flat' => 5, 'price_hour' => 0, 'price_day' => 0, 'stock' => 0, 'max_per_booking' => 8, 'per_person' => 0 ] ],
	[ [ 'de' => [ 'Kühlbox', 'kuehlbox', 'Inklusive Kühlakkus.' ],
		  'fr' => [ 'Glacière', 'glaciere', 'Avec blocs réfrigérants.' ] ],
	  [ 'price_flat' => 0, 'price_hour' => 3, 'price_day' => 8, 'stock' => 6, 'max_per_booking' => 2, 'per_person' => 0 ] ],
	[ [ 'de' => [ 'Rücktransport (Shuttle)', 'ruecktransport', 'Wir holen Boote und Gruppe am Ziel ab (pro Fahrt, bis 8 Personen).' ],
		  'fr' => [ 'Navette retour', 'navette-retour', 'Nous récupérons bateaux et groupe à l’arrivée (par trajet, jusqu’à 8 personnes).' ] ],
	  [ 'price_flat' => 40, 'price_hour' => 0, 'price_day' => 0, 'stock' => 2, 'max_per_booking' => 2, 'per_person' => 0 ] ],
];

foreach ( $extras as $i => [ $langs, $meta ] ) {
	$per_lang = [];
	foreach ( $langs as $lang => [ $title, $slug, $excerpt ] ) {
		$per_lang[ $lang ] = [ 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt ];
	}
	ap_demo_post( 'ap_extra', $per_lang, $meta, $i + 1 );
}

/* ------------------------------------------------------------------ menus */

$menus = [
	'primary' => [
		'de' => [ 'Hauptmenü DE', [ $home['de'], $canoes_page['de'], $booking_page['de'], $contact['de'] ] ],
		'fr' => [ 'Menu principal FR', [ $home['fr'], $canoes_page['fr'], $booking_page['fr'], $contact['fr'] ] ],
	],
	'footer'  => [
		'de' => [ 'Footer DE', [ $booking_page['de'], $contact['de'], $terms['de'] ] ],
		'fr' => [ 'Footer FR', [ $booking_page['fr'], $contact['fr'], $terms['fr'] ] ],
	],
];

$theme     = get_stylesheet();
$options   = get_option( 'polylang' );
$locations = get_theme_mod( 'nav_menu_locations', [] );

foreach ( $menus as $location => $per_lang ) {
	foreach ( $per_lang as $lang => [ $name, $page_ids ] ) {
		$menu = wp_get_nav_menu_object( $name );
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $name );
			foreach ( $page_ids as $pos => $page_id ) {
				wp_update_nav_menu_item( $menu_id, 0, [
					'menu-item-object-id' => $page_id,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $pos + 1,
				] );
			}
			WP_CLI::log( "Created menu {$name}" );
		} else {
			$menu_id = $menu->term_id;
		}
		$options['nav_menus'][ $theme ][ $location ][ $lang ] = $menu_id;
		if ( 'de' === $lang ) {
			$locations[ $location ] = $menu_id;
		}
	}
}
set_theme_mod( 'nav_menu_locations', $locations );
update_option( 'polylang', $options );

// Remove WordPress sample content.
foreach ( [ 'hello-world', 'sample-page' ] as $slug ) {
	$post = get_page_by_path( $slug, OBJECT, [ 'post', 'page' ] );
	if ( $post ) {
		wp_delete_post( $post->ID, true );
	}
}

// Languages are configured – hide Polylang's setup wizard notice.
update_option( 'pll_dismissed_notices', array_values( array_unique( array_merge( (array) get_option( 'pll_dismissed_notices', [] ), [ 'wizard' ] ) ) ) );

delete_transient( 'pll_activation_redirect' ); // don't bounce the first admin login into the wizard

WP_CLI::success( 'Demo content ready.' );
