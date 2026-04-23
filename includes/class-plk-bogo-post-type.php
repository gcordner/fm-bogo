<?php
/**
 * Registers the BOGO Promo custom post type.
 *
 * @package Plk_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Plk_Bogo_Post_Type {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type( 'plk_bogo_promo', array(
			'label'               => __( 'BOGO Promos', 'plk-bogo' ),
			'labels'              => array(
				'name'               => __( 'BOGO Promos', 'plk-bogo' ),
				'singular_name'      => __( 'BOGO Promo', 'plk-bogo' ),
				'add_new'            => __( 'Add New', 'plk-bogo' ),
				'add_new_item'       => __( 'Add New BOGO Promo', 'plk-bogo' ),
				'edit_item'          => __( 'Edit BOGO Promo', 'plk-bogo' ),
				'new_item'           => __( 'New BOGO Promo', 'plk-bogo' ),
				'view_item'          => __( 'View BOGO Promo', 'plk-bogo' ),
				'search_items'       => __( 'Search BOGO Promos', 'plk-bogo' ),
				'not_found'          => __( 'No BOGO Promos found', 'plk-bogo' ),
				'not_found_in_trash' => __( 'No BOGO Promos found in trash', 'plk-bogo' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-tag',
			'menu_position'       => 56,
			'supports'            => array( 'title' ),
			'show_in_rest'        => false,
			'capability_type'     => 'post',
		) );
	}
}
