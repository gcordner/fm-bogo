<?php
/**
 * Records free items to orders and manages quota tracking.
 *
 * @package Plk_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Plk_Bogo_Orders {

	public static function init() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_free_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'increment_quota' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'restore_quota' ) );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'restore_quota' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'restore_quota' ) );
	}

	/**
	 * Copies the plk_bogo_free flag to the order line item meta for reference.
	 */
	public static function save_free_item_meta( $item, $cart_item_key, $cart_item, $order ) {
		if ( ! empty( $cart_item['plk_bogo_free'] ) ) {
			$item->add_meta_data( '_plk_bogo_free_promo_id', (int) $cart_item['plk_bogo_free'], true );
			$item->add_meta_data( '_plk_bogo_promo_label', sanitize_text_field( $cart_item['plk_bogo_promo_label'] ?? '' ), true );
		}
	}

	/**
	 * After order is placed, increment each promo's used counter.
	 */
	public static function increment_quota( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$counts = self::get_free_item_counts( $order );

		foreach ( $counts as $promo_id => $qty ) {
			$used = (int) get_post_meta( $promo_id, '_plk_bogo_free_items_used', true );
			update_post_meta( $promo_id, '_plk_bogo_free_items_used', $used + $qty );
			delete_transient( 'plk_bogo_active_promos' );
		}

		// Store on the order so we can restore later.
		$order->update_meta_data( '_plk_bogo_quota_increments', $counts );
		$order->save();
	}

	/**
	 * On cancel/fail/refund, restore the quota that was used by this order (once only).
	 */
	public static function restore_quota( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( $order->get_meta( '_plk_bogo_quota_restored' ) ) {
			return;
		}

		$counts = $order->get_meta( '_plk_bogo_quota_increments' );
		if ( empty( $counts ) || ! is_array( $counts ) ) {
			return;
		}

		foreach ( $counts as $promo_id => $qty ) {
			$used = (int) get_post_meta( $promo_id, '_plk_bogo_free_items_used', true );
			update_post_meta( $promo_id, '_plk_bogo_free_items_used', max( 0, $used - $qty ) );
			delete_transient( 'plk_bogo_active_promos' );
		}

		$order->update_meta_data( '_plk_bogo_quota_restored', '1' );
		$order->save();
	}

	/**
	 * Returns array of promo_id => qty for all free line items in an order.
	 */
	private static function get_free_item_counts( $order ) {
		$counts = array();

		foreach ( $order->get_items() as $item ) {
			$promo_id = (int) $item->get_meta( '_plk_bogo_free_promo_id' );
			if ( ! $promo_id ) {
				continue;
			}
			if ( ! isset( $counts[ $promo_id ] ) ) {
				$counts[ $promo_id ] = 0;
			}
			$counts[ $promo_id ] += (int) $item->get_quantity();
		}

		return $counts;
	}
}
