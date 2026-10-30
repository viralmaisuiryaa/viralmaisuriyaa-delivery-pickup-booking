<?php
/**
 * Activation handler.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS;

defined( 'ABSPATH' ) || exit;

final class Activator {
	/**
	 * Add safe defaults on first activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( 'fdps_settings', false ) ) {
			add_option( 'fdps_settings', Settings::defaults(), '', false );
		}
	}
}
