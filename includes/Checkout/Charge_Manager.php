<?php
/**
 * Checkout charge controller.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS\Checkout;

use ViralM\FDPS\Settings;

defined( 'ABSPATH' ) || exit;

final class Charge_Manager {
	/** Register charge hooks. */
	public function register() {
		add_filter( 'woocommerce_package_rates', array( $this, 'filter_rates' ), 100, 2 );
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'vary_packages' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'maybe_add_delivery_fee' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_block_update' ) );
	}

	/** Register a nonce-protected WooCommerce Store API cart update. */
	public function register_block_update() {
		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				array(
					'namespace' => 'fdps',
					'callback'  => array( $this, 'update_block_method' ),
				)
			);
		}
	}

	/**
	 * Validate the extension payload before updating the session.
	 *
	 * @param array $data Extension data.
	 */
	public function update_block_method( $data ) {
		$method = isset( $data['method'] ) && is_string( $data['method'] ) ? sanitize_key( $data['method'] ) : '';
		if ( '' !== $method && ! in_array( $method, (array) Settings::get( 'modes' ), true ) ) {
			throw new \WC_REST_Exception( 'fdps_method_invalid', esc_html__( 'Please select a valid fulfilment method.', 'viralmaisuriyaa-delivery-pickup-booking' ), 400 );
		}
		self::set_method( '' === $method ? Settings::get( 'default_mode' ) : $method );
	}

	/**
	 * Save the active method. Package hashes also include the method and free-pickup setting.
	 *
	 * @param string $method Enabled fulfilment method.
	 */
	public static function set_method( $method ) {
		if ( WC()->session && in_array( $method, (array) Settings::get( 'modes' ), true ) ) {
			WC()->session->set( 'fdps_method', $method );
		}
	}

	/**
	 * Determine the current fulfilment method.
	 *
	 * @return string
	 */
	private function current_method() {
		$method = WC()->session ? WC()->session->get( 'fdps_method', '' ) : '';
		return in_array( $method, (array) Settings::get( 'modes' ), true ) ? $method : Settings::get( 'default_mode' );
	}

	/**
	 * Vary cached shipping rates when fulfilment settings change.
	 *
	 * @param array $packages Cart shipping packages.
	 * @return array
	 */
	public function vary_packages( $packages ) {
		foreach ( $packages as &$package ) {
			$package['fdps_method']      = $this->current_method();
			$package['fdps_pickup_free'] = Settings::get( 'pickup_free' );
		}
		unset( $package );
		return $packages;
	}

	/**
	 * Offer only matching shipping rates; never waive a delivery rate for pickup.
	 *
	 * @param array $rates   Package rates.
	 * @param array $package Shipping package.
	 * @return array
	 */
	public function filter_rates( $rates, $package ) {
		$pickup = 'pickup' === $this->current_method();
		foreach ( $rates as $key => $rate ) {
			$is_pickup = in_array( $rate->get_method_id(), array( 'local_pickup', 'pickup_location' ), true );
			if ( $pickup !== $is_pickup ) {
				unset( $rates[ $key ] );
				continue;
			}
			if ( $is_pickup && 'yes' === Settings::get( 'pickup_free' ) ) {
				$rate->set_cost( 0 );
				$rate->set_taxes( array() );
			}
		}
		return $rates;
	}

	/**
	 * Add configured fee for delivery only.
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public function maybe_add_delivery_fee( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		$amount = (float) Settings::get( 'delivery_fee' );
		if ( 'fixed' === Settings::get( 'delivery_fee_type' ) && $amount > 0 && 'delivery' === $this->current_method() ) {
			$cart->add_fee( __( 'Delivery fee', 'viralmaisuriyaa-delivery-pickup-booking' ), $amount, false );
		}
	}
}
