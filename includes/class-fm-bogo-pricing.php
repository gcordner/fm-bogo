<?php
/**
 * Sets free items to $0 during cart total calculation.
 *
 * @package Fm_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Fm_Bogo_Pricing {

	public static function init() {
		// Set price to $0 for calculation — runs after tiered pricing (99999) so we win.
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'zero_free_items' ), PHP_INT_MAX );

		// Advanced Coupons (and similar plugins) hook woocommerce_product_get_price at priority 100
		// to restore a "locked" price they stored in product meta — which overrides our set_price(0).
		// Running at priority 200 ensures we always win for products we've flagged as fm_bogo_free.
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'force_free_price' ), 200, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'force_free_price' ), 200, 2 );

		// Tell the tiered pricing plugin to skip our free items entirely.
		// 'need_price_recalculation'       → skips set_price() in calculateTieredPricingInCart.
		// 'need_price_recalculation/item'  → skips the woocommerce_cart_item_price display override.
		add_filter( 'tiered_pricing_table/cart/need_price_recalculation', array( __CLASS__, 'skip_tiered_for_free_item' ), 10, 2 );
		add_filter( 'tiered_pricing_table/cart/need_price_recalculation/item', array( __CLASS__, 'skip_tiered_for_free_item' ), 10, 2 );

		// Exclude free items from tiered pricing's quantity count so they don't
		// push paid items into a higher tier they haven't earned.
		add_filter( 'tiered_pricing_table/cart/total_product_count', array( __CLASS__, 'adjust_tiered_product_count' ), 10, 2 );
	}

	/**
	 * Clones the product for each free cart item and forces its price to $0.
	 * Clone prevents touching the paid item's product object when both lines share the same product.
	 */
	public static function zero_free_items( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $key => $cart_item ) {
			if ( empty( $cart_item['fm_bogo_free'] ) ) {
				continue;
			}

			$free_product = clone $cart_item['data'];
			$free_product->set_price( 0 );
			// Flag the clone so our priority-200 woocommerce_product_get_price filter
			// can override any plugin that restores a cached price at priority ≤ 100.
			$free_product->update_meta_data( '_fm_bogo_force_free', '1' );
			$cart->cart_contents[ $key ]['data'] = $free_product;
		}
	}

	/**
	 * Returns false for fm_bogo_free cart items, telling the tiered pricing plugin
	 * to skip both the price calculation and the display override for those lines.
	 */
	public static function skip_tiered_for_free_item( $recalculate, $cart_item ) {
		if ( ! empty( $cart_item['fm_bogo_free'] ) ) {
			return false;
		}
		return $recalculate;
	}

	/**
	 * Returns 0 for any product object flagged as an fm_bogo free clone.
	 * Runs at priority 200, after Advanced Coupons' restore_bogo_set_price at 100,
	 * so our zero price always wins regardless of what other plugins lock into meta.
	 */
	public static function force_free_price( $price, $product ) {
		if ( $product->get_meta( '_fm_bogo_force_free', true ) ) {
			return 0;
		}
		return $price;
	}

	/**
	 * Subtracts free item quantities from the count used by tiered pricing
	 * so free items do not artificially inflate the tier level for paid items.
	 */
	public static function adjust_tiered_product_count( $count, $cart_item ) {
		if ( empty( $cart_item['fm_bogo_free'] ) ) {
			// We're calculating the count for a PAID item — subtract any free lines of same product.
			foreach ( WC()->cart->cart_contents as $item ) {
				if ( ! empty( $item['fm_bogo_free'] ) && $item['product_id'] === $cart_item['product_id'] ) {
					$count -= (int) $item['quantity'];
				}
			}
		}
		return max( 0, $count );
	}
}
