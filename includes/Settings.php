<?php
/**
 * Settings value object.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS;

defined( 'ABSPATH' ) || exit;

final class Settings {
	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'          => 'yes',
			'modes'            => array( 'delivery', 'pickup' ),
			'default_mode'     => 'delivery',
			'method_style'     => 'select',
			'lead_days'        => 1,
			'advance_days'     => 30,
			'weekdays'         => array( '1', '2', '3', '4', '5' ),
			'holidays'         => '',
			'time_slots'       => "09:00 - 11:00\n11:00 - 13:00\n14:00 - 16:00",
			'required'         => 'yes',
			'pickup_free'      => 'yes',
			'delivery_fee_type'=> 'woocommerce',
			'delivery_fee_on'  => 'no',
			'delivery_fee'     => '0',
			'delete_on_remove' => 'no',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$value    = get_option( 'fdps_settings', array() );
		$settings = wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );

		if ( ! is_array( $value ) || ! isset( $value['delivery_fee_type'] ) ) {
			$settings['delivery_fee_type'] = 'yes' === $settings['delivery_fee_on'] ? 'fixed' : 'woocommerce';
		}
		$settings['delivery_fee_type'] = in_array( $settings['delivery_fee_type'], array( 'woocommerce', 'fixed' ), true ) ? $settings['delivery_fee_type'] : 'woocommerce';
		$settings['method_style'] = 'radio' === $settings['method_style'] ? 'radio' : 'select';

		$settings['modes']    = array_values( array_map( 'strval', (array) $settings['modes'] ) );
		$settings['weekdays'] = array_values( array_map( 'strval', (array) $settings['weekdays'] ) );

		return $settings;
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = self::all();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Return normalized time slots.
	 *
	 * @return array<string, string>
	 */
	public static function time_slots() {
		$lines = preg_split( '/\r\n|\r|\n/', (string) self::get( 'time_slots' ) );
		$slots = array();

		foreach ( (array) $lines as $line ) {
			$slot = trim( wp_strip_all_tags( $line ) );
			if ( '' !== $slot ) {
				$slots[ $slot ] = $slot;
			}
		}

		return $slots;
	}

	/**
	 * Check a calendar date without accepting PHP date overflow.
	 *
	 * @param string $date ISO date.
	 * @return bool
	 */
	public static function is_iso_date( $date ) {
		if ( ! is_string( $date ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$parts = array_map( 'intval', explode( '-', $date ) );
		return checkdate( $parts[1], $parts[2], $parts[0] );
	}

	/**
	 * Return holiday dates in ISO format.
	 *
	 * @return string[]
	 */
	public static function holidays() {
		$lines = preg_split( '/\r\n|\r|\n/', (string) self::get( 'holidays' ) );
		return array_values(
			array_filter(
				array_map( 'trim', (array) $lines ),
				static function ( $date ) {
					return self::is_iso_date( $date );
				}
			)
		);
	}
}
