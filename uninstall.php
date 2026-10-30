<?php
/**
 * Uninstall cleanup. Orders retain their historical scheduling information.
 *
 * @package ViralM\FDPS
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/** Remove only settings whose site has explicitly opted in. */
function fdps_uninstall_site_settings() {
	$settings = get_option( 'fdps_settings', array() );
	if ( is_array( $settings ) && isset( $settings['delete_on_remove'] ) && 'yes' === $settings['delete_on_remove'] ) {
		delete_option( 'fdps_settings' );
	}
}

if ( is_multisite() ) {
	$fdps_offset = 0;
	do {
		$fdps_sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $fdps_offset ) );
		foreach ( $fdps_sites as $fdps_site_id ) {
			switch_to_blog( $fdps_site_id );
			fdps_uninstall_site_settings();
			restore_current_blog();
		}
		$fdps_offset += 100;
	} while ( 100 === count( $fdps_sites ) );
} else {
	fdps_uninstall_site_settings();
}
