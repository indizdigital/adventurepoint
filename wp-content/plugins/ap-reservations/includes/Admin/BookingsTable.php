<?php
namespace AP\Reservations\Admin;

use AP\Reservations\BookingRepository;
use AP\Reservations\Installer;
use AP\Reservations\Money;
use AP\Reservations\Period;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class BookingsTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct( [ 'singular' => 'booking', 'plural' => 'bookings', 'ajax' => false ] );
	}

	public function get_columns(): array {
		return [
			'booking_ref' => __( 'Reference', 'ap-reservations' ),
			'start_at'    => __( 'When', 'ap-reservations' ),
			'customer'    => __( 'Customer', 'ap-reservations' ),
			'items'       => __( 'Items', 'ap-reservations' ),
			'total'       => __( 'Total', 'ap-reservations' ),
			'status'      => __( 'Status', 'ap-reservations' ),
			'created_at'  => __( 'Created', 'ap-reservations' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'booking_ref' => [ 'booking_ref', false ],
			'start_at'    => [ 'start_at', false ],
			'total'       => [ 'total', false ],
			'created_at'  => [ 'created_at', true ],
		];
	}

	private function current_status(): string {
		$s = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
		return isset( BookingRepository::statuses()[ $s ] ) || 'upcoming' === $s ? $s : '';
	}

	protected function get_views(): array {
		global $wpdb;
		$table   = Installer::bookings_table();
		$counts  = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", OBJECT_K );
		$current = $this->current_status();
		$base    = admin_url( 'admin.php?page=' . Menu::SLUG );
		$views   = [];
		$all     = array_sum( array_map( static fn( $r ) => (int) $r->n, $counts ) );

		$views['all']      = sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url( $base ), '' === $current ? 'current' : '', esc_html__( 'All', 'ap-reservations' ), $all );
		$views['upcoming'] = sprintf( '<a href="%s" class="%s">%s</a>', esc_url( add_query_arg( 'status', 'upcoming', $base ) ), 'upcoming' === $current ? 'current' : '', esc_html__( 'Upcoming', 'ap-reservations' ) );
		foreach ( BookingRepository::statuses() as $key => $label ) {
			if ( empty( $counts[ $key ] ) ) {
				continue;
			}
			$views[ $key ] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status', $key, $base ) ), $current === $key ? 'current' : '', esc_html( $label ), (int) $counts[ $key ]->n );
		}
		return $views;
	}

	public function prepare_items(): void {
		$status   = $this->current_status();
		$per_page = 25;
		[ $rows, $total ] = BookingRepository::search( [
			'status'   => 'upcoming' === $status ? '' : $status,
			'upcoming' => 'upcoming' === $status,
			'search'   => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'orderby'  => sanitize_key( wp_unslash( $_GET['orderby'] ?? 'created_at' ) ),
			'order'    => sanitize_key( wp_unslash( $_GET['order'] ?? ( 'upcoming' === $status ? 'asc' : 'desc' ) ) ),
			'per_page' => $per_page,
			'paged'    => $this->get_pagenum(),
		] );
		if ( 'upcoming' === $status && empty( $_GET['orderby'] ) ) {
			usort( $rows, static fn( $a, $b ) => strcmp( $a->start_at, $b->start_at ) );
		}
		$this->items           = $rows;
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$this->set_pagination_args( [ 'total_items' => $total, 'per_page' => $per_page ] );
	}

	public function no_items(): void {
		esc_html_e( 'No reservations found.', 'ap-reservations' );
	}

	protected function column_default( $item, $column_name ) {
		$url = admin_url( 'admin.php?page=' . Menu::SLUG . '&booking=' . $item->id );
		switch ( $column_name ) {
			case 'booking_ref':
				return sprintf( '<a class="row-title" href="%s">%s</a>', esc_url( $url ), esc_html( $item->booking_ref ) );
			case 'start_at':
				return esc_html( Period::from_booking( $item )->label() );
			case 'customer':
				return esc_html( $item->customer_name ) . '<br><span class="description">' . esc_html( $item->customer_email ) . ' · ' . esc_html( strtoupper( $item->lang ) ) . '</span>';
			case 'items':
				return esc_html( implode( ', ', array_map(
					static fn( $i ) => $i->qty . '× ' . ( get_the_title( (int) $i->item_id ) ?: $i->label ),
					BookingRepository::items( (int) $item->id )
				) ) );
			case 'total':
				return esc_html( Money::format( (float) $item->total, $item->currency ) );
			case 'status':
				return sprintf( '<span class="ap-status ap-status--%s">%s</span>', esc_attr( $item->status ), esc_html( BookingRepository::status_label( $item->status ) ) );
			case 'created_at':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $item->created_at ) );
		}
		return '';
	}
}
