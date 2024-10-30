<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_FreeAgent_Product_Options', false ) ) :

	class WC_FreeAgent_Product_Options {

		//Init notices
		public static function init() {
			add_action('woocommerce_product_options_advanced', array( __CLASS__, 'product_options_fields'));
			add_action('woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_options_fields'), 10, 2);

			add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variable_options_fields'), 10, 3 );
			add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variable_options_fields'), 10, 2 );
		}

		public static function variable_options_fields($loop, $variation_data, $variation) {
			include( dirname( __FILE__ ) . '/views/html-variable-options.php' );
		}

    public static function product_options_fields() {
  		global $post;
      include( dirname( __FILE__ ) . '/views/html-product-options.php' );
  	}

  	public static function save_product_options_fields($product) {
			$fields = ['line_item_unit', 'line_item_desc', 'line_item_name'];
			foreach ($fields as $field) {
				$posted_data = ! empty( $_REQUEST['wc_freeagent_'.$field] )
					? sanitize_text_field( $_REQUEST['wc_freeagent_'.$field] )
					: '';
				$product->update_meta_data( 'wc_freeagent_'.$field, $posted_data );
			}
  		$product->save_meta_data();
  	}

		public static function save_variable_options_fields($variation_id, $i) {
			$fields = ['line_item_unit', 'line_item_desc', 'line_item_name'];
			foreach ($fields as $field) {
				$custom_field = sanitize_text_field($_POST['wc_freeagent_'.$field][$i]);
				if ( ! empty( $custom_field ) ) {
		        update_post_meta( $variation_id, 'wc_freeagent_'.$field, $custom_field );
		    } else delete_post_meta( $variation_id, 'wc_freeagent_'.$field );
			}
		}
  }

	WC_FreeAgent_Product_Options::init();

endif;
