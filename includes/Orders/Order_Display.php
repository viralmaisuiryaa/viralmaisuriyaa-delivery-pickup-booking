<?php
/**
 * Order display integration.
 *
 * @package ViralM\FDPS
 */

namespace ViralM\FDPS\Orders;

use ViralM\FDPS\Checkout\Checkout_Fields;
use ViralM\FDPS\Settings;

defined( 'ABSPATH' ) || exit;

final class Order_Display {
	/** Register display hooks. */
	public function register() {
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_details' ) );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'customer_details' ), 20, 2 );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_fields' ), 20, 3 );
	}

	/**
	 * Read legacy values and disabled-scheduler Block order history.
	 *
	 * WooCommerce renders fields registered through its Additional Checkout
	 * Fields API, so reading only legacy values prevents duplicate output.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, string>
	 */
	private function values( $order ) {
		$values = array(
			'method' => (string) $order->get_meta( Checkout_Fields::META_METHOD ),
			'date'   => (string) $order->get_meta( Checkout_Fields::META_DATE ),
			'slot'   => (string) $order->get_meta( Checkout_Fields::META_SLOT ),
		);
		if ( 'yes' !== Settings::get( 'enabled' ) ) {
			foreach ( array( 'method' => 'fdps-method', 'date' => 'fdps-date', 'slot' => 'fdps-time-slot' ) as $key => $field ) {
				if ( '' === $values[ $key ] ) {
					$values[ $key ] = (string) $order->get_meta( '_wc_other/viralm/' . $field );
				}
			}
		}
		return $values;
	}

	/** Format method. */
	private function method_label( $method ) {
		return 'pickup' === $method ? __( 'Pickup', 'viralmaisuriyaa-delivery-pickup-booking' ) : __( 'Delivery', 'viralmaisuriyaa-delivery-pickup-booking' );
	}

	/** Format date. */
	private function date_label( $date ) {
		$parsed    = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$timestamp = $parsed ? $parsed->getTimestamp() : false;
		return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $date;
	}

	/** Admin order output. */
	public function admin_details( $order ) {
		$values = $this->values( $order );
		if ( empty( $values['method'] ) && empty( $values['date'] ) && empty( $values['slot'] ) ) {
			return;
		}
		?>
		<div class="fdps-order-details">
			<h3><?php esc_html_e( 'Delivery / pickup schedule', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></h3>
			<?php if ( $values['method'] ) : ?><p><strong><?php esc_html_e( 'Method:', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></strong> <?php echo esc_html( $this->method_label( $values['method'] ) ); ?></p><?php endif; ?>
			<?php if ( $values['date'] ) : ?><p><strong><?php esc_html_e( 'Date:', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></strong> <?php echo esc_html( $this->date_label( $values['date'] ) ); ?></p><?php endif; ?>
			<?php if ( $values['slot'] ) : ?><p><strong><?php esc_html_e( 'Time:', 'viralmaisuriyaa-delivery-pickup-booking' ); ?></strong> <?php echo esc_html( $values['slot'] ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	/** Add schedule to customer order totals. */
	public function customer_details( $rows, $order ) {
		$values = $this->values( $order );
		if ( $values['method'] ) {
			$rows['fdps_method'] = array( 'label' => __( 'Fulfilment method:', 'viralmaisuriyaa-delivery-pickup-booking' ), 'value' => esc_html( $this->method_label( $values['method'] ) ) );
		}
		if ( $values['date'] ) {
			$rows['fdps_date'] = array( 'label' => __( 'Scheduled date:', 'viralmaisuriyaa-delivery-pickup-booking' ), 'value' => esc_html( $this->date_label( $values['date'] ) ) );
		}
		if ( $values['slot'] ) {
			$rows['fdps_slot'] = array( 'label' => __( 'Time slot:', 'viralmaisuriyaa-delivery-pickup-booking' ), 'value' => esc_html( $values['slot'] ) );
		}
		return $rows;
	}

	/** Add schedule to WooCommerce emails. */
	public function email_fields( $fields, $sent_to_admin, $order ) {
		$values = $this->values( $order );
		if ( $values['method'] ) {
			$fields['fdps_method'] = array( 'label' => __( 'Fulfilment method', 'viralmaisuriyaa-delivery-pickup-booking' ), 'value' => $this->method_label( $values['method'] ) );
		}
		if ( $values['date'] ) {
			$fields['fdps_date'] = array( 'label' => __( 'Scheduled date', 'viralmaisuriyaa-delivery-pickup-booking' ), 'value' => $this->date_label( $values['date'] ) );
		}
		if ( $values['slot'] ) {
			$fields['fdps_slot'] = array( 'label' => __( 'Time slot', 'viralmaisuriyaa-delivery-pickup-booking' ), 'value' => $values['slot'] );
		}
		return $fields;
	}
}
