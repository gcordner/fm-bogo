<?php
/**
 * Plugin bootstrap file.
 *
 * @wordpress-plugin
 * Plugin Name:       FM BOGO
 * Description:       Automatic BOGO promotions for WooCommerce. Adds free products to cart when qualifying products are purchased.
 * Version:           1.1.0
 * Author:            Geoff Cordner
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       fm-bogo
 * Requires Plugins:  woocommerce
 *
 * @package Fm_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'FM_BOGO_VERSION', '1.1.0' );
define( 'FM_BOGO_DIR', plugin_dir_path( __FILE__ ) );
define( 'FM_BOGO_URL', plugin_dir_url( __FILE__ ) );

require_once FM_BOGO_DIR . 'includes/class-fm-bogo-post-type.php';
require_once FM_BOGO_DIR . 'includes/class-fm-bogo-admin.php';
require_once FM_BOGO_DIR . 'includes/class-fm-bogo-cart.php';
require_once FM_BOGO_DIR . 'includes/class-fm-bogo-pricing.php';
require_once FM_BOGO_DIR . 'includes/class-fm-bogo-display.php';
require_once FM_BOGO_DIR . 'includes/class-fm-bogo-orders.php';

add_action( 'plugins_loaded', 'fm_bogo_init' );

/**
 * Initialize FM BOGO after WooCommerce is confirmed loaded.
 */
function fm_bogo_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	Fm_Bogo_Post_Type::init();
	Fm_Bogo_Admin::init();
	Fm_Bogo_Cart::init();
	Fm_Bogo_Pricing::init();
	Fm_Bogo_Display::init();
	Fm_Bogo_Orders::init();
}
