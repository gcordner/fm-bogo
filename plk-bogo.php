<?php
/**
 * Plugin bootstrap file.
 *
 * @wordpress-plugin
 * Plugin Name:       PLK BOGO
 * Description:       Automatic BOGO promotions for WooCommerce. Adds free products to cart when qualifying products are purchased.
 * Version:           1.0.0
 * Author:            Geoff Cordner
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       plk-bogo
 * Requires Plugins:  woocommerce
 *
 * @package Plk_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PLK_BOGO_VERSION', '1.0.0' );
define( 'PLK_BOGO_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLK_BOGO_URL', plugin_dir_url( __FILE__ ) );

require_once PLK_BOGO_DIR . 'includes/class-plk-bogo-post-type.php';
require_once PLK_BOGO_DIR . 'includes/class-plk-bogo-admin.php';
require_once PLK_BOGO_DIR . 'includes/class-plk-bogo-cart.php';
require_once PLK_BOGO_DIR . 'includes/class-plk-bogo-pricing.php';
require_once PLK_BOGO_DIR . 'includes/class-plk-bogo-display.php';
require_once PLK_BOGO_DIR . 'includes/class-plk-bogo-orders.php';

add_action( 'plugins_loaded', 'plk_bogo_init' );

function plk_bogo_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	Plk_Bogo_Post_Type::init();
	Plk_Bogo_Admin::init();
	Plk_Bogo_Cart::init();
	Plk_Bogo_Pricing::init();
	Plk_Bogo_Display::init();
	Plk_Bogo_Orders::init();
}
