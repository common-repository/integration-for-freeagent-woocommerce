<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//Load PDF API
use iio\libmergepdf\Merger;
use iio\libmergepdf\Driver\TcpdiDriver;

if ( ! class_exists( 'WC_FreeAgent_Bulk_Actions', false ) ) :

	class WC_FreeAgent_Bulk_Actions {

		public static function init() {
      add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'add_bulk_options'), 20, 1);
      add_filter( 'handle_bulk_actions-edit-shop_order', array( __CLASS__, 'handle_bulk_actions'), 10, 3 );
      add_action( 'admin_notices', array( __CLASS__, 'bulk_actions_results') );
		}

    public static function add_bulk_options( $actions ) {
      $actions['wc_freeagent_bulk_print'] = __( 'Download invoices', 'wc-freeagent' );
      $actions['wc_freeagent_bulk_download'] = __( 'Print invoices', 'wc-freeagent' );
      return $actions;
    }

    public static function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
      if ( ! in_array($action, array('wc_freeagent_bulk_print', 'wc_freeagent_bulk_download'))) {
    		return $redirect_to;
    	}

			//Remove exusting params from url
			$redirect_to = remove_query_arg(array('wc_freeagent_bulk_print', 'wc_freeagent_bulk_download', 'wc_freeagent_bulk_pdf'), $redirect_to);

			//Init PDF merger
			require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';
			$merger = new Merger(new TcpdiDriver);

			$processed = array();

			//Process selected posts
			foreach ( $post_ids as $order_id ) {
				$order = wc_get_order($order_id);
				$pdf_file = WC_FreeAgent()->generate_download_link($order, 'invoice', true);
				if($pdf_file) {
					$merger->addFile($pdf_file);
					$processed[] = $order_id;
				}
			}

			//Create bulk pdf file
			$bulk_pdf_file = WC_FreeAgent()->get_pdf_file_path('bulk', 0);
			$merged_pdf_file = $merger->merge();

			//Store PDF
			global $wp_filesystem;
			if ( !$wp_filesystem ) WP_Filesystem();
			$wp_filesystem->put_contents( $bulk_pdf_file['path'], $merged_pdf_file );

			//Set redirect url that will show the download message notice
			$redirect_to = add_query_arg( array($action => count( $processed ), 'wc_freeagent_bulk_pdf' => urlencode($bulk_pdf_file['name'])), $redirect_to );
			return $redirect_to;

    }

    public static function bulk_actions_results() {
      if ( !empty( $_REQUEST['wc_freeagent_bulk_print'] ) || !empty( $_REQUEST['wc_freeagent_bulk_download'] ) ) {
        if(!empty( $_REQUEST['wc_freeagent_bulk_print'] )) {
          $print_count = intval( $_REQUEST['wc_freeagent_bulk_print'] );
          $type = 'print';
        } else {
          $print_count = intval( $_REQUEST['wc_freeagent_bulk_download'] );
          $type = 'download';
        }

				$paths = WC_FreeAgent()->get_pdf_file_path('bulk', 0);
        $pdf_file_name = esc_url( $_REQUEST['wc_freeagent_bulk_pdf'] );
        $pdf_file_url = $paths['baseurl'].$pdf_file_name;

        include( dirname( __FILE__ ) . '/views/html-notice-bulk.php' );
    	}
    }
  }

	WC_FreeAgent_Bulk_Actions::init();

endif;
