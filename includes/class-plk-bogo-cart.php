<?php
/**
 * Manages free item addition and removal in the cart.
 *
 * @package Plk_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Plk_Bogo_Cart {

	private static $is_processing = false;

	public static function init() {
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'sync_free_items' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'sync_free_items' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'block_manual_free_add' ), 10, 2 );
	}

	/**
	 * Returns all active promos, cached in a transient.
	 */
	public static function get_active_promos() {
		$cached = get_transient( 'plk_bogo_active_promos' );
		if ( false !== $cached ) {
			return $cached;
		}

		$posts = get_posts( array(
			'post_type'      => 'plk_bogo_promo',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_plk_bogo_active',
					'value' => '1',
				),
			),
		) );

		$promos = array();
		foreach ( $posts as $post ) {
			$max_free  = get_post_meta( $post->ID, '_plk_bogo_max_free_items', true );
			$used      = (int) get_post_meta( $post->ID, '_plk_bogo_free_items_used', true );
			$remaining = '' === $max_free ? PHP_INT_MAX : max( 0, (int) $max_free - $used );

			if ( $remaining <= 0 ) {
				continue;
			}

			$promos[] = array(
				'id'               => $post->ID,
				'label'            => get_post_meta( $post->ID, '_plk_bogo_promo_label', true ) ?: $post->post_title,
				'qualifying_type'  => get_post_meta( $post->ID, '_plk_bogo_qualifying_type', true ) ?: 'products',
				'qualifying_ids'   => get_post_meta( $post->ID, '_plk_bogo_qualifying_ids', true ) ?: array(),
				'qualifying_cat'   => (int) get_post_meta( $post->ID, '_plk_bogo_qualifying_category', true ),
				'free_product_id'  => (int) get_post_meta( $post->ID, '_plk_bogo_free_product_id', true ),
				'buy_qty'          => (int) get_post_meta( $post->ID, '_plk_bogo_buy_qty', true ) ?: 1,
				'get_qty'          => (int) get_post_meta( $post->ID, '_plk_bogo_get_qty', true ) ?: 1,
				'recursive'        => '1' === get_post_meta( $post->ID, '_plk_bogo_recursive', true ),
				'remaining_quota'  => $remaining,
			);
		}

		set_transient( 'plk_bogo_active_promos', $promos, HOUR_IN_SECONDS );
		return $promos;
	}

	/**
	 * Main sync: calculates desired free items per promo and reconciles cart.
	 */
	public static function sync_free_items() {
		if ( self::$is_processing ) {
			return;
		}
		self::$is_processing = true;

		$cart   = WC()->cart;
		$promos = self::get_active_promos();

		// Build a map of what's currently in cart.
		// existing_free[ promo_id ] = array of cart_item_keys for free items of that promo.
		$existing_free = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['plk_bogo_free'] ) ) {
				$pid = (int) $item['plk_bogo_free'];
				if ( ! isset( $existing_free[ $pid ] ) ) {
					$existing_free[ $pid ] = array();
				}
				$existing_free[ $pid ][] = $key;
			}
		}

		foreach ( $promos as $promo ) {
			$desired_qty = self::calculate_desired_free_qty( $promo, $cart );
			$desired_qty = min( $desired_qty, $promo['remaining_quota'] );

			$current_keys = $existing_free[ $promo['id'] ] ?? array();
			$current_qty  = 0;
			foreach ( $current_keys as $key ) {
				$current_qty += (int) $cart->cart_contents[ $key ]['quantity'];
			}

			if ( $desired_qty === $current_qty ) {
				continue;
			}

			// Remove all existing free items for this promo, then re-add at desired qty.
			foreach ( $current_keys as $key ) {
				$cart->remove_cart_item( $key );
			}

			if ( $desired_qty > 0 ) {
				$added = $cart->add_to_cart(
					$promo['free_product_id'],
					$desired_qty,
					0,
					array(),
					array(
						'plk_bogo_free'       => $promo['id'],
						'plk_bogo_promo_label' => $promo['label'],
					)
				);
			}
		}

		// Remove free items for promos that are no longer active or no longer in cart.
		$active_ids = wp_list_pluck( $promos, 'id' );
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['plk_bogo_free'] ) && ! in_array( (int) $item['plk_bogo_free'], $active_ids, true ) ) {
				$cart->remove_cart_item( $key );
			}
		}

		self::$is_processing = false;
	}

	/**
	 * Calculates how many free items a promo should contribute, given current cart contents.
	 */
	private static function calculate_desired_free_qty( $promo, $cart ) {
		$qualifying_qty = 0;

		foreach ( $cart->get_cart() as $item ) {
			// Don't count free items as qualifying.
			if ( ! empty( $item['plk_bogo_free'] ) ) {
				continue;
			}

			if ( self::item_qualifies( $item, $promo ) ) {
				$qualifying_qty += (int) $item['quantity'];
			}
		}

		if ( $qualifying_qty < $promo['buy_qty'] ) {
			return 0;
		}

		if ( $promo['recursive'] ) {
			$sets = (int) floor( $qualifying_qty / $promo['buy_qty'] );
			return $sets * $promo['get_qty'];
		}

		return $promo['get_qty'];
	}

	/**
	 * Checks whether a cart item satisfies the qualifying condition for a promo.
	 */
	private static function item_qualifies( $item, $promo ) {
		$product_id = (int) $item['product_id'];

		if ( 'category' === $promo['qualifying_type'] ) {
			return has_term( $promo['qualifying_cat'], 'product_cat', $product_id );
		}

		// Products mode: check product ID and parent (for variations).
		$ids_to_check = array_map( 'intval', (array) $promo['qualifying_ids'] );
		if ( in_array( $product_id, $ids_to_check, true ) ) {
			return true;
		}
		if ( ! empty( $item['variation_id'] ) && in_array( (int) $item['variation_id'], $ids_to_check, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Prevent customers from manually adding a free-flagged product via URL tricks.
	 * (The flag is set server-side on add_to_cart calls from sync_free_items only.)
	 */
	public static function block_manual_free_add( $cart_item_data, $product_id ) {
		unset( $cart_item_data['plk_bogo_free'] );
		unset( $cart_item_data['plk_bogo_promo_label'] );
		return $cart_item_data;
	}
}
