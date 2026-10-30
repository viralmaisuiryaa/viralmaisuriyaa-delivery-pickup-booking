<?php
/**
 * Admin settings controller and view.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS\Admin;

use ViralM\FDPS\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings_Page {
	/** Register hooks. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_fdps_save_settings', array( $this, 'save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FDPS_FILE ), array( $this, 'action_links' ) );
	}

	/** Load assets only on this settings screen. */
	public function enqueue( $hook ) {
		if ( 'woocommerce_page_fdps-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'fdps-admin', FDPS_URL . 'assets/css/admin.css', array(), FDPS_VERSION );
		wp_enqueue_script( 'fdps-admin', FDPS_URL . 'assets/js/admin.js', array(), FDPS_VERSION, true );
	}

	/** Add settings submenu. */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Delivery & Pickup', 'viralmaisuriyaa-delivery-pickup-booking' ),
			esc_html__( 'Delivery & Pickup', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'manage_woocommerce',
			'fdps-settings',
			array( $this, 'render' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$input    = array_filter( $input, static function ( $value ) { return is_scalar( $value ) || is_array( $value ); } );
		foreach ( array( 'lead_days', 'advance_days', 'holidays', 'time_slots', 'delivery_fee', 'default_mode', 'method_style', 'delivery_fee_type' ) as $key ) {
			if ( isset( $input[ $key ] ) && ! is_scalar( $input[ $key ] ) ) {
				unset( $input[ $key ] );
			}
		}
		$allowed  = array( 'delivery', 'pickup' );
		$modes    = isset( $input['modes'] ) ? array_intersect( $allowed, array_filter( (array) $input['modes'], 'is_string' ) ) : array();
		$modes    = empty( $modes ) ? array( 'delivery' ) : array_values( $modes );
		$weekdays = array();
		if ( isset( $input['weekdays'] ) && is_array( $input['weekdays'] ) ) {
			foreach ( $input['weekdays'] as $weekday => $selected ) {
				$weekday = (string) absint( $weekday );
				if ( 'yes' === $selected && in_array( $weekday, array( '0', '1', '2', '3', '4', '5', '6' ), true ) ) {
					$weekdays[] = $weekday;
				}
			}
		}

		$lead_days    = min( 365, max( 0, intval( $input['lead_days'] ?? 0 ) ) );
		$advance_days = min( 730, max( $lead_days, intval( $input['advance_days'] ?? 30 ) ) );
		$weekdays     = array_values( array_unique( $weekdays ) );

		$holidays = preg_split( '/\r\n|\r|\n/', isset( $input['holidays'] ) ? $input['holidays'] : '' );
		$holidays = array_filter(
			array_map( 'trim', (array) $holidays ),
			static function ( $date ) {
				return Settings::is_iso_date( $date );
			}
		);

		$slots = preg_split( '/\r\n|\r|\n/', isset( $input['time_slots'] ) ? $input['time_slots'] : '' );
		$slots = array_unique( array_filter( array_map( 'sanitize_text_field', (array) $slots ) ) );
		if ( empty( $slots ) ) {
			$slots = preg_split( '/\r\n|\r|\n/', Settings::defaults()['time_slots'] );
		}

		$fee_type = isset( $input['delivery_fee_type'] ) && in_array( $input['delivery_fee_type'], array( 'woocommerce', 'fixed' ), true ) ? $input['delivery_fee_type'] : 'woocommerce';

		return array(
			'enabled'          => isset( $input['enabled'] ) ? 'yes' : 'no',
			'modes'            => $modes,
			'method_style'     => isset( $input['method_style'] ) && 'radio' === $input['method_style'] ? 'radio' : 'select',
			'default_mode'     => in_array( $input['default_mode'] ?? '', $modes, true ) ? sanitize_key( $input['default_mode'] ) : $modes[0],
			'lead_days'        => $lead_days,
			'advance_days'     => $advance_days,
			'weekdays'         => $weekdays,
			'holidays'         => implode( "\n", array_unique( $holidays ) ),
			'time_slots'       => implode( "\n", $slots ),
			'required'         => isset( $input['required'] ) ? 'yes' : 'no',
			'pickup_free'      => isset( $input['pickup_free'] ) ? 'yes' : 'no',
			'delivery_fee_type'=> $fee_type,
			'delivery_fee_on'  => 'fixed' === $fee_type ? 'yes' : 'no',
			'delivery_fee'     => isset( $input['delivery_fee'] ) ? wc_format_decimal( max( 0, (float) wc_format_decimal( $input['delivery_fee'] ) ) ) : '0',
			'delete_on_remove' => isset( $input['delete_on_remove'] ) ? 'yes' : 'no',
		);
	}

	/**
	 * Save settings from the plugin form.
	 *
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		check_admin_referer( 'fdps_save_settings', 'fdps_nonce' );

		$submitted = isset( $_POST['fdps_settings'] ) ? wp_unslash( $_POST['fdps_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized as a complete settings array below.
		$settings  = $this->sanitize( $submitted );

		if ( 'yes' === $settings['enabled'] && empty( $submitted['modes'] ) ) {
			wp_die( esc_html__( 'Select at least one fulfilment method before enabling the scheduler.', 'viralmaisuriyaa-delivery-pickup-booking' ), '', array( 'back_link' => true ) );
		}
		if ( 'yes' === $settings['enabled'] && empty( $settings['weekdays'] ) ) {
			wp_die( esc_html__( 'Select at least one available weekday before enabling the scheduler.', 'viralmaisuriyaa-delivery-pickup-booking' ), '', array( 'back_link' => true ) );
		}

		update_option( 'fdps_settings', $settings, false );

		wp_safe_redirect(
			add_query_arg(
				'fdps-updated',
				'1',
				admin_url( 'admin.php?page=fdps-settings' )
			)
		);
		exit;
	}

	/** Render settings. */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'viralmaisuriyaa-delivery-pickup-booking' ) );
		}

		$settings = Settings::all();
		$days     = array(
			'1' => __( 'Monday', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'2' => __( 'Tuesday', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'3' => __( 'Wednesday', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'4' => __( 'Thursday', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'5' => __( 'Friday', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'6' => __( 'Saturday', 'viralmaisuriyaa-delivery-pickup-booking' ),
			'0' => __( 'Sunday', 'viralmaisuriyaa-delivery-pickup-booking' ),
		);
		?>
		<div class="wrap fdps-admin">
			<h1><?php esc_html_e( 'Delivery & Pickup Scheduler', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></h1>
			<p class="fdps-intro"><?php esc_html_e( 'Let customers choose when their order arrives or is ready to collect.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></p>
			<?php if ( isset( $_GET['fdps-updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['fdps-updated'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only success flag. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fdps_save_settings">
				<?php wp_nonce_field( 'fdps_save_settings', 'fdps_nonce' ); ?>
				<section class="fdps-card"><h2><?php esc_html_e( 'Checkout settings', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></h2><table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Enable scheduler', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></th><td><label><input type="checkbox" name="fdps_settings[enabled]" value="yes" <?php checked( 'yes', $settings['enabled'] ); ?>> <?php esc_html_e( 'Show scheduling fields at checkout', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Available methods', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></th><td><label><input type="checkbox" name="fdps_settings[modes][]" value="delivery" <?php checked( in_array( 'delivery', $settings['modes'], true ) ); ?>> <?php esc_html_e( 'Delivery', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label><br><label><input type="checkbox" name="fdps_settings[modes][]" value="pickup" <?php checked( in_array( 'pickup', $settings['modes'], true ) ); ?>> <?php esc_html_e( 'Pickup', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></td></tr>
					<tr><th scope="row"><label for="fdps-method-style"><?php esc_html_e( 'Fulfilment method display', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></th><td><select id="fdps-method-style" name="fdps_settings[method_style]"><option value="select" <?php selected( 'select', $settings['method_style'] ); ?>><?php esc_html_e( 'Dropdown', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></option><option value="radio" <?php selected( 'radio', $settings['method_style'] ); ?>><?php esc_html_e( 'Radio buttons', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></option></select><p class="description"><?php esc_html_e( 'Choose how Delivery and Pickup appear in both classic and Block checkout.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></p></td></tr>
					<tr><th scope="row"><label for="fdps-default-mode"><?php esc_html_e( 'Default method', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></th><td><select id="fdps-default-mode" name="fdps_settings[default_mode]"><option value="delivery" <?php selected( 'delivery', $settings['default_mode'] ); ?>><?php esc_html_e( 'Delivery', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></option><option value="pickup" <?php selected( 'pickup', $settings['default_mode'] ); ?>><?php esc_html_e( 'Pickup', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></option></select></td></tr>
					</table></section><section class="fdps-card"><h2><?php esc_html_e( 'Booking availability', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></h2><p class="description"><?php esc_html_e( 'Days are calculated from today in your WordPress timezone. Select at least one weekday.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></p><table class="form-table" role="presentation"><tr><th scope="row"><label for="fdps-lead-days"><?php esc_html_e( 'Minimum lead days', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></th><td><input class="small-text" type="number" min="0" max="365" id="fdps-lead-days" name="fdps_settings[lead_days]" value="<?php echo esc_attr( $settings['lead_days'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="fdps-advance-days"><?php esc_html_e( 'Maximum advance days', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></th><td><input class="small-text" type="number" min="0" max="730" id="fdps-advance-days" name="fdps_settings[advance_days]" value="<?php echo esc_attr( $settings['advance_days'] ); ?>"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Available weekdays', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></th><td><?php foreach ( $days as $value => $label ) : ?><label class="fdps-day"><input type="checkbox" name="fdps_settings[weekdays][<?php echo esc_attr( $value ); ?>]" value="yes" <?php checked( in_array( (string) $value, $settings['weekdays'], true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></td></tr>
					<tr><th scope="row"><label for="fdps-holidays"><?php esc_html_e( 'Holiday dates', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></th><td><textarea class="large-text code" rows="6" id="fdps-holidays" name="fdps_settings[holidays]" placeholder="2026-12-25"><?php echo esc_textarea( $settings['holidays'] ); ?></textarea><p class="description"><?php esc_html_e( 'Enter one YYYY-MM-DD date per line.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></p></td></tr>
					<tr><th scope="row"><label for="fdps-time-slots"><?php esc_html_e( 'Time slots', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></th><td><textarea class="large-text" rows="7" id="fdps-time-slots" name="fdps_settings[time_slots]"><?php echo esc_textarea( $settings['time_slots'] ); ?></textarea><p class="description"><?php esc_html_e( 'Enter one customer-facing time slot per line.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Required fields', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></th><td><label><input type="checkbox" name="fdps_settings[required]" value="yes" <?php checked( 'yes', $settings['required'] ); ?>> <?php esc_html_e( 'Require method, date and time slot', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></td></tr>
					</table></section><section class="fdps-card"><h2><?php esc_html_e( 'Charges and data', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></h2><p class="description"><?php esc_html_e( 'Add a Local pickup shipping method to each shipping zone that offers pickup. Delivery fees are additional, non-taxable fees.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></p><table class="form-table" role="presentation"><tr><th scope="row"><?php esc_html_e( 'Pickup shipping charge', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></th><td><label><input type="checkbox" name="fdps_settings[pickup_free]" value="yes" <?php checked( 'yes', $settings['pickup_free'] ); ?>> <?php esc_html_e( 'Make Local pickup shipping rates free when Pickup is selected', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></td></tr>
					<tr><th scope="row"><label for="fdps-delivery-fee-type"><?php esc_html_e( 'Additional delivery fee', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></th><td>
						<select id="fdps-delivery-fee-type" name="fdps_settings[delivery_fee_type]">
							<option value="woocommerce" <?php selected( 'woocommerce', $settings['delivery_fee_type'] ); ?>><?php esc_html_e( 'WooCommerce rate only', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></option>
							<option value="fixed" <?php selected( 'fixed', $settings['delivery_fee_type'] ); ?>><?php esc_html_e( 'Fixed additional fee', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'WooCommerce rate only keeps your shipping-zone rate with no extra fee. Fixed additional fee adds the amount below to the shipping rate for Delivery only.', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></p>
						<p id="fdps-fixed-fee-row"><label for="fdps-delivery-fee"><?php esc_html_e( 'Fixed fee amount', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label> <input id="fdps-delivery-fee" class="wc_input_price" type="number" min="0" step="0.01" name="fdps_settings[delivery_fee]" value="<?php echo esc_attr( $settings['delivery_fee'] ); ?>"> <?php echo wp_kses_post( get_woocommerce_currency_symbol() ); ?></p>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Data removal', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></th><td><label><input type="checkbox" name="fdps_settings[delete_on_remove]" value="yes" <?php checked( 'yes', $settings['delete_on_remove'] ); ?>> <?php esc_html_e( 'Delete plugin settings when the plugin is uninstalled', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></label></td></tr>
				</table></section>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/** Add a settings link. */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=fdps-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'viralmaisuriyaa-delivery-pickup-booking' ) . '</a>' );
		return $links;
	}
}
