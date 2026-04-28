<?php
/**
 * Manages free item addition and removal in the cart.
 *
 * @package Fm_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Fm_Bogo_Cart {

	private static $is_processing = false;
	private static $is_adding_free = false;

	public static function init() {
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'sync_free_items' ) );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'sync_free_items' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'sync_free_items' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'block_manual_free_add' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'restore_free_flag_from_session' ), 10, 2 );
	}

	/**
	 * Explicitly restores fm_bogo_free flag from session data on every page load.
	 * WooCommerce preserves all cart item keys, but this makes the intent explicit
	 * and guards against any plugin that might strip unknown keys.
	 */
	public static function restore_free_flag_from_session( $cart_item, $values ) {
		if ( ! empty( $values['fm_bogo_free'] ) ) {
			$cart_item['fm_bogo_free']        = $values['fm_bogo_free'];
			$cart_item['fm_bogo_promo_label'] = $values['fm_bogo_promo_label'] ?? '';
		}
		return $cart_item;
	}

	/**
	 * Returns all active promos, cached in a transient.
	 */
	public static function get_active_promos() {
		$cached = get_transient( 'fm_bogo_active_promos' );
		if ( false !== $cached ) {
			return $cached;
		}

		$posts = get_posts( array(
			'post_type'      => 'fm_bogo_promo',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_fm_bogo_active',
					'value' => '1',
				),
			),
		) );

		$promos = array();
		foreach ( $posts as $post ) {
			$max_free  = get_post_meta( $post->ID, '_fm_bogo_max_free_items', true );
			$used      = (int) get_post_meta( $post->ID, '_fm_bogo_free_items_used', true );
			$remaining = '' === $max_free ? PHP_INT_MAX : max( 0, (int) $max_free - $used );

			if ( $remaining <= 0 ) {
				continue;
			}

			$promos[] = array(
				'id'              => $post->ID,
				'label'           => get_post_meta( $post->ID, '_fm_bogo_promo_label', true ) ?: $post->post_title,
				'qualifying_type' => get_post_meta( $post->ID, '_fm_bogo_qualifying_type', true ) ?: 'products',
				'qualifying_ids'  => get_post_meta( $post->ID, '_fm_bogo_qualifying_ids', true ) ?: array(),
				'qualifying_cat'  => (int) get_post_meta( $post->ID, '_fm_bogo_qualifying_category', true ),
				'free_product_id' => (int) get_post_meta( $post->ID, '_fm_bogo_free_product_id', true ),
				'buy_qty'         => (int) get_post_meta( $post->ID, '_fm_bogo_buy_qty', true ) ?: 1,
				'get_qty'         => (int) get_post_meta( $post->ID, '_fm_bogo_get_qty', true ) ?: 1,
				'recursive'       => '1' === get_post_meta( $post->ID, '_fm_bogo_recursive', true ),
				'remaining_quota' => $remaining,
			);
		}

		set_transient( 'fm_bogo_active_promos', $promos, HOUR_IN_SECONDS );
		return $promos;
	}

	/**
	 * Main sync: calculates desired free items per promo and reconciles cart.
	 */
	public static function sync_free_items() {
		if ( self::$is_processing ) {
			return;
		}

		// Guard against running before the cart is ready.
		if ( ! WC()->cart ) {
			return;
		}

		self::$is_processing = true;

		$cart   = WC()->cart;
		$promos = self::get_active_promos();

		// existing_free[ promo_id ] = array of cart_item_keys for free items of that promo.
		$existing_free = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['fm_bogo_free'] ) ) {
				$pid = (int) $item['fm_bogo_free'];
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
				if ( isset( $cart->cart_contents[ $key ] ) ) {
					$current_qty += (int) $cart->cart_contents[ $key ]['quantity'];
				}
			}

			if ( $desired_qty === $current_qty ) {
				continue;
			}

			// Remove existing free items for this promo, then re-add at the correct qty.
			foreach ( $current_keys as $key ) {
				$cart->remove_cart_item( $key );
			}

			if ( $desired_qty > 0 ) {
				self::$is_adding_free = true;
				$cart->add_to_cart(
					$promo['free_product_id'],
					$desired_qty,
					0,
					array(),
					array(
						'fm_bogo_free'        => $promo['id'],
						'fm_bogo_promo_label' => $promo['label'],
					)
				);
				self::$is_adding_free = false;
			}
		}

		// Remove free items whose promo is no longer active.
		$active_ids = wp_list_pluck( $promos, 'id' );
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['fm_bogo_free'] ) && ! in_array( (int) $item['fm_bogo_free'], $active_ids, true ) ) {
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
			// Never count free items as qualifying.
			if ( ! empty( $item['fm_bogo_free'] ) ) {
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
			return (int) floor( $qualifying_qty / $promo['buy_qty'] ) * $promo['get_qty'];
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
	 * Strips fm_bogo_free from customer-initiated add_to_cart calls.
	 * Allows our own internal calls through via the $is_adding_free flag.
	 */
	public static function block_manual_free_add( $cart_item_data, $product_id ) {
		if ( self::$is_adding_free ) {
			return $cart_item_data;
		}
		unset( $cart_item_data['fm_bogo_free'] );
		unset( $cart_item_data['fm_bogo_promo_label'] );
		return $cart_item_data;
	}
}
