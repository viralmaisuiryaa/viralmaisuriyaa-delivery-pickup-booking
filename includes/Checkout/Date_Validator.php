<?php
/**
 * Scheduling validation service.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS\Checkout;

use DateTimeImmutable;
use Exception;
use ViralM\FDPS\Settings;

defined( 'ABSPATH' ) || exit;

final class Date_Validator {
	/**
	 * Validate an ISO date against current settings.
	 *
	 * @param string $value Date value.
	 * @return true|\WP_Error
	 */
	public function validate( $value ) {
		$value = sanitize_text_field( $value );

		if ( '' === $value ) {
			return new \WP_Error( 'fdps_required_date', __( 'Please select a delivery or pickup date.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return new \WP_Error( 'fdps_invalid_date', __( 'Please enter the date in YYYY-MM-DD format.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		try {
			$timezone = wp_timezone();
			$selected = new DateTimeImmutable( $value . ' 00:00:00', $timezone );
			$today    = new DateTimeImmutable( 'today', $timezone );
		} catch ( Exception $exception ) {
			return new \WP_Error( 'fdps_invalid_date', __( 'Please select a valid date.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		if ( $selected->format( 'Y-m-d' ) !== $value ) {
			return new \WP_Error( 'fdps_invalid_date', __( 'Please select a valid date.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		$minimum = $today->modify( '+' . absint( Settings::get( 'lead_days' ) ) . ' days' );
		$maximum = $today->modify( '+' . absint( Settings::get( 'advance_days' ) ) . ' days' );

		if ( $selected < $minimum || $selected > $maximum ) {
			return new \WP_Error( 'fdps_date_range', __( 'The selected date is outside the available booking period.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		if ( ! in_array( $selected->format( 'w' ), (array) Settings::get( 'weekdays' ), true ) ) {
			return new \WP_Error( 'fdps_weekday', __( 'The selected weekday is not available.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		if ( in_array( $value, Settings::holidays(), true ) ) {
			return new \WP_Error( 'fdps_holiday', __( 'The selected date is unavailable.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		return true;
	}
}
