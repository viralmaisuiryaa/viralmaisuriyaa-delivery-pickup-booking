<?php
/**
 * Main plugin coordinator.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS;

use ViralM\FDPS\Admin\Settings_Page;
use ViralM\FDPS\Checkout\Checkout_Fields;
use ViralM\FDPS\Checkout\Charge_Manager;
use ViralM\FDPS\Orders\Order_Display;
use ViralM\FDPS\Support\Assets;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/** @var self|null */
	private static $instance;

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register plugin services.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
			return;
		}

		( new Order_Display() )->register();

		if ( 'yes' === Settings::get( 'enabled' ) ) {
			( new Checkout_Fields() )->register();
			( new Charge_Manager() )->register();
			( new Assets() )->register();
		}

		if ( is_admin() ) {
			( new Settings_Page() )->register();
		}
	}

	/**
	 * Show dependency notice.
	 *
	 * @return void
	 */
	public function woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error"><p>
			<?php esc_html_e( 'Viralmaisuriyaa Delivery & Pickup Booking requires WooCommerce to be installed and active.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?>
		</p></div>
		<?php
	}
}
