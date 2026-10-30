<?php
/**
 * Plugin Name:       Viralmaisuriyaa Delivery & Pickup Booking for WooCommerce
 * Plugin URI:        https://www.linkedin.com/in/viralmaisuriyaa/
 * Description:       Let customers choose a delivery or pickup date and time slot at checkout.
 * Version:           1.1.5
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * WC requires at least: 8.9
 * WC tested up to:   11.1
 * Author:            Viral Maisuriya
 * Author URI:        https://www.instagram.com/viralmaisuriyaa
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       viralmaisuriyaa-delivery-pickup-booking
 * Requires Plugins:  woocommerce
 *
 * @package ViralM\FDPS
 */

defined( 'ABSPATH' ) || exit;

define( 'FDPS_VERSION', '1.1.5' );
define( 'FDPS_FILE', __FILE__ );
define( 'FDPS_PATH', plugin_dir_path( __FILE__ ) );
define( 'FDPS_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'ViralM\\FDPS\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = FDPS_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'ViralM\\FDPS\\Activator', 'activate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		ViralM\FDPS\Plugin::instance()->boot();
	}
);
