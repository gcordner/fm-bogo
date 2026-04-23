<?php
/**
 * Registers the BOGO Promo custom post type.
 *
 * @package Fm_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Fm_Bogo_Post_Type {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type( 'fm_bogo_promo', array(
			'label'               => __( 'BOGO Promos', 'fm-bogo' ),
			'labels'              => array(
				'name'               => __( 'BOGO Promos', 'fm-bogo' ),
				'singular_name'      => __( 'BOGO Promo', 'fm-bogo' ),
				'add_new'            => __( 'Add New', 'fm-bogo' ),
				'add_new_item'       => __( 'Add New BOGO Promo', 'fm-bogo' ),
				'edit_item'          => __( 'Edit BOGO Promo', 'fm-bogo' ),
				'new_item'           => __( 'New BOGO Promo', 'fm-bogo' ),
				'view_item'          => __( 'View BOGO Promo', 'fm-bogo' ),
				'search_items'       => __( 'Search BOGO Promos', 'fm-bogo' ),
				'not_found'          => __( 'No BOGO Promos found', 'fm-bogo' ),
				'not_found_in_trash' => __( 'No BOGO Promos found in trash', 'fm-bogo' ),
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
