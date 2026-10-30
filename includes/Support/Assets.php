<?php
/**
 * Frontend asset manager.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS\Support;

use DateTimeImmutable;
use ViralM\FDPS\Settings;

defined( 'ABSPATH' ) || exit;

final class Assets {
	/** Register hooks. */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** Enqueue only on checkout. */
	public function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		wp_enqueue_style( 'fdps-checkout', FDPS_URL . 'assets/css/checkout.css', array(), FDPS_VERSION );
		$dependencies = array( 'jquery' );
		if ( has_block( 'woocommerce/checkout' ) && wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			$dependencies[] = 'wc-blocks-checkout';
		}
		wp_enqueue_script( 'fdps-checkout', FDPS_URL . 'assets/js/checkout.js', $dependencies, FDPS_VERSION, true );

		$today = new DateTimeImmutable( 'today', wp_timezone() );
		wp_localize_script(
			'fdps-checkout',
			'fdpsCheckout',
			array(
				'weekStartsOn' => absint( get_option( 'start_of_week', 0 ) ),
				'today' => $today->format( 'Y-m-d' ),
				'defaultMode' => Settings::get( 'default_mode' ),
				'methodStyle' => Settings::get( 'method_style' ),
				'locale' => str_replace( '_', '-', get_locale() ),
				'dateLabel' => __( 'Delivery / pickup date', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'methodLabel' => __( 'Fulfilment method', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'chooseDate' => __( 'Select an available date', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'previousMonth' => __( 'Previous month', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'nextMonth' => __( 'Next month', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'availableLabel' => __( 'Available', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'selectedLabel' => __( 'Selected', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'holidayLegend' => __( 'Holiday — unavailable', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'closedLegend' => __( 'Closed / outside booking period', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'clearDate' => __( 'Clear selected date', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'required' => 'yes' === Settings::get( 'required' ),
				'minDate'   => $today->modify( '+' . absint( Settings::get( 'lead_days' ) ) . ' days' )->format( 'Y-m-d' ),
				'maxDate'   => $today->modify( '+' . absint( Settings::get( 'advance_days' ) ) . ' days' )->format( 'Y-m-d' ),
				'weekdays'  => array_map( 'absint', (array) Settings::get( 'weekdays' ) ),
				'holidays'  => Settings::holidays(),
				'updateError' => __( 'Could not update shipping totals. Please reselect your fulfilment method before placing the order.', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'dateError' => __( 'Please choose an available date.', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'holidayLabel' => __( 'Holiday - unavailable', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'unavailableLabel' => __( 'Unavailable weekday', 'viralmaisuriyaa-delivery-pickup-booking' ),
			)
		);
	}
}
