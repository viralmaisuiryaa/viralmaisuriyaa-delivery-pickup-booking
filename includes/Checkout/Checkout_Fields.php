<?php
/**
 * Checkout fields controller.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS\Checkout;

use ViralM\FDPS\Settings;

defined( 'ABSPATH' ) || exit;

final class Checkout_Fields {
	const META_METHOD = '_fdps_method';
	const META_DATE   = '_fdps_date';
	const META_SLOT   = '_fdps_time_slot';

	/** Register checkout integrations. */
	public function register() {
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'store_block_method' ), 10, 2 );
		add_filter( 'woocommerce_get_default_value_for_viralm/fdps-method', array( $this, 'default_block_method' ) );
		add_action( 'woocommerce_init', array( $this, 'register_block_fields' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'store_classic_method' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'register_classic_fields' ) );

		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic_fields' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_classic_fields' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'store_selected_method' ) );
	}

	/**
	 * Synchronize final classic checkout data before WooCommerce calculates totals.
	 *
	 * @param array $data WooCommerce-sanitized checkout data.
	 * @return array
	 */
	public function store_classic_method( $data ) {
		$method = isset( $data['fdps_method'] ) && is_string( $data['fdps_method'] ) ? sanitize_key( $data['fdps_method'] ) : '';
		Charge_Manager::set_method( in_array( $method, (array) Settings::get( 'modes' ), true ) ? $method : Settings::get( 'default_mode' ) );
		return $data;
	}

	/**
	 * Register shortcode checkout fields with WooCommerce.
	 *
	 * @param array<string, array<string, mixed>> $fields Checkout fields.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_classic_fields( $fields ) {
		$required = 'yes' === Settings::get( 'required' );
		$modes    = array();

		foreach ( (array) Settings::get( 'modes' ) as $mode ) {
			$modes[ $mode ] = 'pickup' === $mode ? __( 'Pickup', 'viralmaisuriyaa-delivery-pickup-booking' ) : __( 'Delivery', 'viralmaisuriyaa-delivery-pickup-booking' );
		}

		$fields['order']['fdps_method'] = array(
			'type'     => 'radio' === Settings::get( 'method_style' ) ? 'radio' : 'select',
			'label'    => __( 'Fulfilment method', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'required' => $required,
			'options'  => $modes,
			'default'  => Settings::get( 'default_mode' ),
			'class'    => array( 'form-row-wide' ),
			'priority' => 110,
		);

		$fields['order']['fdps_date'] = array(
			'type'              => 'text',
			'label'             => __( 'Delivery / pickup date', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'placeholder'       => __( 'Select a date', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'required'          => $required,
			'class'             => array( 'form-row-wide' ),
			'description'       => __( 'Choose an available date. Closed weekdays and holidays cannot be booked.', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'custom_attributes' => array( 'autocomplete' => 'off' ),
			'priority'          => 120,
		);

		$fields['order']['fdps_time_slot'] = array(
			'type'     => 'select',
			'label'    => __( 'Time slot', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'required' => $required,
			'options'  => array( '' => __( 'Choose a time slot', 'viralmaisuriyaa-delivery-pickup-booking' ) ) + Settings::time_slots(),
			'class'    => array( 'form-row-wide' ),
			'priority' => 130,
		);

		return $fields;
	}

	/**
	 * Store the classic checkout method for fee recalculation requests.
	 *
	 * @param string $posted_data Serialized checkout data.
	 * @return void
	 */
	public function store_selected_method( $posted_data ) {
		if ( ! WC()->session ) {
			return;
		}

		$data = array();
		parse_str( $posted_data, $data );
		$method = isset( $data['fdps_method'] ) && is_string( $data['fdps_method'] ) ? sanitize_key( $data['fdps_method'] ) : '';

		if ( in_array( $method, (array) Settings::get( 'modes' ), true ) ) {
			Charge_Manager::set_method( $method );
		}
	}

	/**
	 * Register fields using the WooCommerce Additional Checkout Fields API.
	 * This API renders in both Checkout Block and supported shortcode checkouts.
	 */
	public function register_block_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$required = 'yes' === Settings::get( 'required' );
		$modes    = array();

		foreach ( (array) Settings::get( 'modes' ) as $mode ) {
			$modes[] = array(
				'value' => $mode,
				'label' => 'pickup' === $mode ? __( 'Pickup', 'viralmaisuriyaa-delivery-pickup-booking' ) : __( 'Delivery', 'viralmaisuriyaa-delivery-pickup-booking' ),
			);
		}

		$slots = array();
		foreach ( Settings::time_slots() as $value => $label ) {
			$slots[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'viralm/fdps-method',
				'label'    => __( 'Fulfilment method', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'location' => 'order',
				'type'     => 'select',
				'required' => $required,
				'options'  => $modes,
			)
		);

		$dates     = array();
		$today     = new \DateTimeImmutable( 'today', wp_timezone() );
		$validator = new Date_Validator();
		for ( $day = absint( Settings::get( 'lead_days' ) ); $day <= min( 730, absint( Settings::get( 'advance_days' ) ) ); ++$day ) {
			$date = $today->modify( '+' . $day . ' days' );
			if ( true === $validator->validate( $date->format( 'Y-m-d' ) ) ) {
				$dates[] = array( 'value' => $date->format( 'Y-m-d' ), 'label' => wp_date( get_option( 'date_format' ), $date->getTimestamp(), wp_timezone() ) );
			}
		}
		if ( empty( $dates ) ) {
			$dates[] = array( 'value' => 'unavailable', 'label' => __( 'No dates available. Please contact the store.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => 'viralm/fdps-date',
				'label'             => __( 'Delivery / pickup date', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'location'          => 'order',
				'type'              => 'select',
				'options'           => $dates,
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_block_date' ),
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'viralm/fdps-time-slot',
				'label'    => __( 'Time slot', 'viralmaisuriyaa-delivery-pickup-booking' ),
				'location' => 'order',
				'type'     => 'select',
				'required' => $required,
				'options'  => $slots,
			)
		);
	}

	/** Return the configured default for the Block checkout. */
	public function default_block_method( $value ) {
		return in_array( $value, (array) Settings::get( 'modes' ), true ) ? $value : Settings::get( 'default_mode' );
	}

	/**
	 * Synchronize the submitted Block method before Store API totals are calculated.
	 *
	 * @param \WC_Customer    $customer Customer object.
	 * @param \WP_REST_Request $request  Validated Store API request.
	 */
	public function store_block_method( $customer, $request ) {
		$fields = $request->get_param( 'additional_fields' );
		if ( ! is_array( $fields ) || ! array_key_exists( 'viralm/fdps-method', $fields ) ) {
			return;
		}
		$method = $fields['viralm/fdps-method'];
		if ( ! is_string( $method ) || ( '' !== $method && ! in_array( $method, (array) Settings::get( 'modes' ), true ) ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'fdps_method_invalid', esc_html__( 'Please select a valid fulfilment method.', 'viralmaisuriyaa-delivery-pickup-booking' ), 400 );
		}
		Charge_Manager::set_method( '' === $method ? Settings::get( 'default_mode' ) : $method );
	}

	/** Validate block date callback. */
	public function validate_block_date( $value ) {
		if ( '' === trim( (string) $value ) && 'yes' !== Settings::get( 'required' ) ) {
			return null;
		}

		$result = ( new Date_Validator() )->validate( $value );
		return true === $result ? null : $result;
	}

	/** Validate legacy checkout fields. */
	public function validate_classic_fields( $data, $errors ) {
		$method = isset( $data['fdps_method'] ) && is_string( $data['fdps_method'] ) ? sanitize_key( $data['fdps_method'] ) : '';
		$date   = isset( $data['fdps_date'] ) ? sanitize_text_field( $data['fdps_date'] ) : '';
		$slot   = isset( $data['fdps_time_slot'] ) ? sanitize_text_field( $data['fdps_time_slot'] ) : '';

		if ( 'yes' === Settings::get( 'required' ) && '' === $method ) {
			$errors->add( 'fdps_method', __( 'Please select delivery or pickup.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		if ( '' !== $method && ! in_array( $method, (array) Settings::get( 'modes' ), true ) ) {
			$errors->add( 'fdps_method_invalid', esc_html__( 'Please select a valid fulfilment method.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		if ( '' !== $date || 'yes' === Settings::get( 'required' ) ) {
			$result = ( new Date_Validator() )->validate( $date );
			if ( is_wp_error( $result ) ) {
				$errors->add( $result->get_error_code(), $result->get_error_message() );
			}
		}

		if ( 'yes' === Settings::get( 'required' ) && '' === $slot ) {
			$errors->add( 'fdps_slot', __( 'Please select a time slot.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		} elseif ( '' !== $slot && ! array_key_exists( $slot, Settings::time_slots() ) ) {
			$errors->add( 'fdps_slot_invalid', __( 'Please select a valid time slot.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}
	}

	/** Save legacy field data using the order CRUD API. */
	public function save_classic_fields( $order, $data ) {
		$map = array(
			'fdps_method'    => self::META_METHOD,
			'fdps_date'      => self::META_DATE,
			'fdps_time_slot' => self::META_SLOT,
		);

		foreach ( $map as $key => $meta_key ) {
			if ( ! empty( $data[ $key ] ) ) {
				$value = 'fdps_method' === $key ? sanitize_key( $data[ $key ] ) : sanitize_text_field( $data[ $key ] );
				$order->update_meta_data( $meta_key, $value );
			}
		}
	}
}
