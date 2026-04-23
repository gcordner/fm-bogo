<?php
/**
 * Controls how free items appear in the cart UI.
 *
 * @package Plk_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Plk_Bogo_Display {

	public static function init() {
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'free_item_name' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'free_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'free_item_subtotal' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( __CLASS__, 'hide_remove_link' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_class', array( __CLASS__, 'free_item_class' ), 10, 3 );
	}

	/**
	 * Appends the promo label beneath the product name.
	 */
	public static function free_item_name( $name, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item['plk_bogo_free'] ) ) {
			return $name;
		}

		$label = esc_html( $cart_item['plk_bogo_promo_label'] ?? __( 'Free item', 'plk-bogo' ) );
		return $name . '<span class="plk-bogo-promo-label"> — ' . $label . '</span>';
	}

	/**
	 * Replaces the price display with "FREE".
	 */
	public static function free_item_price( $price, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item['plk_bogo_free'] ) ) {
			return $price;
		}

		return '<span class="plk-bogo-free-price">' . esc_html__( 'FREE', 'plk-bogo' ) . '</span>';
	}

	/**
	 * Replaces the subtotal display with "FREE".
	 */
	public static function free_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item['plk_bogo_free'] ) ) {
			return $subtotal;
		}

		return '<span class="plk-bogo-free-price">' . esc_html__( 'FREE', 'plk-bogo' ) . '</span>';
	}

	/**
	 * Removes the × remove button from free items.
	 */
	public static function hide_remove_link( $link, $cart_item_key ) {
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		if ( ! empty( $cart_item['plk_bogo_free'] ) ) {
			return '';
		}

		return $link;
	}

	/**
	 * Adds a CSS class to free item rows.
	 */
	public static function free_item_class( $class, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item['plk_bogo_free'] ) ) {
			$class .= ' plk-bogo-free-item';
		}

		return $class;
	}
}
