<?php
/**
 * Sets free items to $0 during cart total calculation.
 *
 * @package Plk_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Plk_Bogo_Pricing {

	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'zero_free_items' ), 10 );
	}

	public static function zero_free_items( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['plk_bogo_free'] ) ) {
				$cart_item['data']->set_price( 0 );
			}
		}
	}
}
