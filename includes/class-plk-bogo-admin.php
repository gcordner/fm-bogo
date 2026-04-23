<?php
/**
 * Admin interface for BOGO Promo post type.
 *
 * @package Plk_Bogo
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Plk_Bogo_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_plk_bogo_promo', array( __CLASS__, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_filter( 'manage_plk_bogo_promo_posts_columns', array( __CLASS__, 'list_columns' ) );
		add_action( 'manage_plk_bogo_promo_posts_custom_column', array( __CLASS__, 'list_column_content' ), 10, 2 );
	}

	public static function enqueue_scripts( $hook ) {
		global $post;

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		if ( ! isset( $post ) || 'plk_bogo_promo' !== $post->post_type ) {
			return;
		}

		wp_enqueue_script(
			'plk-bogo-admin',
			PLK_BOGO_URL . 'admin/js/plk-bogo-admin.js',
			array( 'jquery', 'wc-enhanced-select', 'selectWoo' ),
			PLK_BOGO_VERSION,
			true
		);

		wp_enqueue_style(
			'plk-bogo-admin',
			PLK_BOGO_URL . 'admin/css/plk-bogo-admin.css',
			array(),
			PLK_BOGO_VERSION
		);
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'plk_bogo_settings',
			__( 'Promotion Settings', 'plk-bogo' ),
			array( __CLASS__, 'render_meta_box' ),
			'plk_bogo_promo',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'plk_bogo_save_meta', 'plk_bogo_nonce' );

		$active             = get_post_meta( $post->ID, '_plk_bogo_active', true );
		$qualifying_type    = get_post_meta( $post->ID, '_plk_bogo_qualifying_type', true ) ?: 'products';
		$qualifying_ids     = get_post_meta( $post->ID, '_plk_bogo_qualifying_ids', true ) ?: array();
		$qualifying_cat     = get_post_meta( $post->ID, '_plk_bogo_qualifying_category', true ) ?: '';
		$free_product_id    = get_post_meta( $post->ID, '_plk_bogo_free_product_id', true ) ?: '';
		$buy_qty            = get_post_meta( $post->ID, '_plk_bogo_buy_qty', true ) ?: 1;
		$get_qty            = get_post_meta( $post->ID, '_plk_bogo_get_qty', true ) ?: 1;
		$recursive          = get_post_meta( $post->ID, '_plk_bogo_recursive', true );
		$max_free_items     = get_post_meta( $post->ID, '_plk_bogo_max_free_items', true ) ?: '';
		$free_items_used    = (int) get_post_meta( $post->ID, '_plk_bogo_free_items_used', true );
		$promo_label        = get_post_meta( $post->ID, '_plk_bogo_promo_label', true ) ?: $post->post_title;

		// Pre-populate qualifying product titles for selectWoo.
		$qualifying_products = array();
		if ( ! empty( $qualifying_ids ) && is_array( $qualifying_ids ) ) {
			foreach ( $qualifying_ids as $pid ) {
				$product = wc_get_product( $pid );
				if ( $product ) {
					$qualifying_products[ $pid ] = $product->get_formatted_name();
				}
			}
		}

		// Pre-populate free product title for selectWoo.
		$free_product_name = '';
		if ( $free_product_id ) {
			$free_product = wc_get_product( $free_product_id );
			if ( $free_product ) {
				$free_product_name = $free_product->get_formatted_name();
			}
		}

		// Get all product categories.
		$categories = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		) );
		?>

		<div class="plk-bogo-meta-box">

			<div class="plk-bogo-field plk-bogo-field--active">
				<label>
					<input type="checkbox" name="plk_bogo_active" value="1" <?php checked( $active, '1' ); ?>>
					<?php esc_html_e( 'Promotion active', 'plk-bogo' ); ?>
				</label>
			</div>

			<div class="plk-bogo-field">
				<label for="plk_bogo_promo_label"><?php esc_html_e( 'Promotion label', 'plk-bogo' ); ?> <span class="required">*</span></label>
				<input type="text" id="plk_bogo_promo_label" name="plk_bogo_promo_label" value="<?php echo esc_attr( $promo_label ); ?>" class="widefat">
				<p class="description"><?php esc_html_e( 'Shown on the free item line in the cart.', 'plk-bogo' ); ?></p>
			</div>

			<div class="plk-bogo-field">
				<label><?php esc_html_e( 'Qualifying products', 'plk-bogo' ); ?> <span class="required">*</span></label>
				<div class="plk-bogo-radio-group">
					<label>
						<input type="radio" name="plk_bogo_qualifying_type" value="products" <?php checked( $qualifying_type, 'products' ); ?>>
						<?php esc_html_e( 'Specific products', 'plk-bogo' ); ?>
					</label>
					<label>
						<input type="radio" name="plk_bogo_qualifying_type" value="category" <?php checked( $qualifying_type, 'category' ); ?>>
						<?php esc_html_e( 'Category', 'plk-bogo' ); ?>
					</label>
				</div>

				<div class="plk-bogo-qualifying-products" <?php echo 'category' === $qualifying_type ? 'style="display:none"' : ''; ?>>
					<select id="plk_bogo_qualifying_ids" name="plk_bogo_qualifying_ids[]" multiple class="wc-product-search" style="width:100%" data-placeholder="<?php esc_attr_e( 'Search for products…', 'plk-bogo' ); ?>" data-action="woocommerce_json_search_products_and_variations">
						<?php foreach ( $qualifying_products as $pid => $name ) : ?>
							<option value="<?php echo esc_attr( $pid ); ?>" selected><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="plk-bogo-qualifying-category" <?php echo 'products' === $qualifying_type ? 'style="display:none"' : ''; ?>>
					<select id="plk_bogo_qualifying_category" name="plk_bogo_qualifying_category" style="width:100%">
						<option value=""><?php esc_html_e( '— Select a category —', 'plk-bogo' ); ?></option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $qualifying_cat, $cat->term_id ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="plk-bogo-field">
				<label for="plk_bogo_free_product_id"><?php esc_html_e( 'Free product', 'plk-bogo' ); ?> <span class="required">*</span></label>
				<select id="plk_bogo_free_product_id" name="plk_bogo_free_product_id" class="wc-product-search" style="width:100%" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'plk-bogo' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
					<?php if ( $free_product_id && $free_product_name ) : ?>
						<option value="<?php echo esc_attr( $free_product_id ); ?>" selected><?php echo esc_html( $free_product_name ); ?></option>
					<?php endif; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The product added to cart for free. Can be the same as the qualifying product.', 'plk-bogo' ); ?></p>
			</div>

			<div class="plk-bogo-field plk-bogo-field--inline">
				<div>
					<label for="plk_bogo_buy_qty"><?php esc_html_e( 'Buy quantity', 'plk-bogo' ); ?></label>
					<input type="number" id="plk_bogo_buy_qty" name="plk_bogo_buy_qty" value="<?php echo esc_attr( $buy_qty ); ?>" min="1" step="1" class="small-text">
				</div>
				<div>
					<label for="plk_bogo_get_qty"><?php esc_html_e( 'Get quantity (free)', 'plk-bogo' ); ?></label>
					<input type="number" id="plk_bogo_get_qty" name="plk_bogo_get_qty" value="<?php echo esc_attr( $get_qty ); ?>" min="1" step="1" class="small-text">
				</div>
			</div>

			<div class="plk-bogo-field">
				<label>
					<input type="checkbox" name="plk_bogo_recursive" value="1" <?php checked( $recursive, '1' ); ?>>
					<?php esc_html_e( 'Recursive — repeat for every additional buy quantity', 'plk-bogo' ); ?>
				</label>
			</div>

			<div class="plk-bogo-field plk-bogo-field--inline">
				<div>
					<label for="plk_bogo_max_free_items"><?php esc_html_e( 'Max free items (quota)', 'plk-bogo' ); ?></label>
					<input type="number" id="plk_bogo_max_free_items" name="plk_bogo_max_free_items" value="<?php echo esc_attr( $max_free_items ); ?>" min="0" step="1" class="small-text" placeholder="∞">
					<p class="description"><?php esc_html_e( 'Total free units across all orders. Leave blank for unlimited.', 'plk-bogo' ); ?></p>
				</div>
				<div>
					<label for="plk_bogo_free_items_used"><?php esc_html_e( 'Free items used', 'plk-bogo' ); ?></label>
					<input type="number" id="plk_bogo_free_items_used" name="plk_bogo_free_items_used" value="<?php echo esc_attr( $free_items_used ); ?>" min="0" step="1" class="small-text">
					<p class="description"><?php esc_html_e( 'Running total. Adjust manually if needed.', 'plk-bogo' ); ?></p>
				</div>
			</div>

		</div>
		<?php
	}

	public static function save_meta( $post_id ) {
		if (
			! isset( $_POST['plk_bogo_nonce'] ) ||
			! wp_verify_nonce( $_POST['plk_bogo_nonce'], 'plk_bogo_save_meta' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Active toggle.
		update_post_meta( $post_id, '_plk_bogo_active', isset( $_POST['plk_bogo_active'] ) ? '1' : '0' );

		// Promo label.
		if ( isset( $_POST['plk_bogo_promo_label'] ) ) {
			update_post_meta( $post_id, '_plk_bogo_promo_label', sanitize_text_field( $_POST['plk_bogo_promo_label'] ) );
		}

		// Qualifying type.
		$qualifying_type = isset( $_POST['plk_bogo_qualifying_type'] ) && 'category' === $_POST['plk_bogo_qualifying_type'] ? 'category' : 'products';
		update_post_meta( $post_id, '_plk_bogo_qualifying_type', $qualifying_type );

		// Qualifying products.
		$qualifying_ids = array();
		if ( isset( $_POST['plk_bogo_qualifying_ids'] ) && is_array( $_POST['plk_bogo_qualifying_ids'] ) ) {
			$qualifying_ids = array_map( 'absint', $_POST['plk_bogo_qualifying_ids'] );
			$qualifying_ids = array_filter( $qualifying_ids );
		}
		update_post_meta( $post_id, '_plk_bogo_qualifying_ids', $qualifying_ids );

		// Qualifying category.
		update_post_meta( $post_id, '_plk_bogo_qualifying_category', absint( $_POST['plk_bogo_qualifying_category'] ?? 0 ) );

		// Free product — required, don't save if empty.
		$free_product_id = absint( $_POST['plk_bogo_free_product_id'] ?? 0 );
		update_post_meta( $post_id, '_plk_bogo_free_product_id', $free_product_id );

		// Buy/get quantities.
		update_post_meta( $post_id, '_plk_bogo_buy_qty', max( 1, absint( $_POST['plk_bogo_buy_qty'] ?? 1 ) ) );
		update_post_meta( $post_id, '_plk_bogo_get_qty', max( 1, absint( $_POST['plk_bogo_get_qty'] ?? 1 ) ) );

		// Recursive.
		update_post_meta( $post_id, '_plk_bogo_recursive', isset( $_POST['plk_bogo_recursive'] ) ? '1' : '0' );

		// Quota.
		$max_free = isset( $_POST['plk_bogo_max_free_items'] ) && '' !== $_POST['plk_bogo_max_free_items']
			? absint( $_POST['plk_bogo_max_free_items'] )
			: '';
		update_post_meta( $post_id, '_plk_bogo_max_free_items', $max_free );

		// Free items used (editable for manual adjustment).
		update_post_meta( $post_id, '_plk_bogo_free_items_used', absint( $_POST['plk_bogo_free_items_used'] ?? 0 ) );

		// Flush cached promos.
		delete_transient( 'plk_bogo_active_promos' );
	}

	public static function list_columns( $columns ) {
		return array(
			'cb'             => $columns['cb'],
			'title'          => __( 'Promo Name', 'plk-bogo' ),
			'active'         => __( 'Active', 'plk-bogo' ),
			'buy_get'        => __( 'Deal', 'plk-bogo' ),
			'quota'          => __( 'Quota', 'plk-bogo' ),
		);
	}

	public static function list_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'active':
				$active = get_post_meta( $post_id, '_plk_bogo_active', true );
				echo $active ? '<span style="color:green">●</span>' : '<span style="color:#ccc">●</span>';
				break;

			case 'buy_get':
				$buy = get_post_meta( $post_id, '_plk_bogo_buy_qty', true );
				$get = get_post_meta( $post_id, '_plk_bogo_get_qty', true );
				$free_id = get_post_meta( $post_id, '_plk_bogo_free_product_id', true );
				$free_product = $free_id ? wc_get_product( $free_id ) : null;
				$free_name = $free_product ? $free_product->get_name() : '—';
				printf( 'Buy %d, Get %d free (%s)', (int) $buy, (int) $get, esc_html( $free_name ) );
				break;

			case 'quota':
				$max  = get_post_meta( $post_id, '_plk_bogo_max_free_items', true );
				$used = (int) get_post_meta( $post_id, '_plk_bogo_free_items_used', true );
				if ( '' === $max ) {
					echo esc_html( $used ) . ' / ∞';
				} else {
					echo esc_html( $used ) . ' / ' . esc_html( $max );
				}
				break;
		}
	}
}
