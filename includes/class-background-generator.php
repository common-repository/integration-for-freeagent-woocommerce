<?php
/**
 * Background Emailer
 *
 * @version 3.0.1
 * @package WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Background_Process', false ) ) {
  include_once dirname( WC_PLUGIN_FILE ) . '/abstracts/class-wc-background-process.php';
}

class WC_FreeAgent_Background_Generator extends WC_Background_Process {

	/**
	 * Initiate new background process.
	 */
	public function __construct() {
		$this->prefix = 'wp_' . get_current_blog_id();
		$this->action = 'wc_freeagent_invoices';
		parent::__construct();
	}

	protected function task( $callback ) {
		if ( isset( $callback['invoice_type'], $callback['order_id'] ) ) {
			try {
        global $wc_freeagent;

        $wc_freeagent->log_debug_messages($callback, 'bg-generate-request');

        if($callback['invoice_type'] == 'estimate') {
          if(!$wc_freeagent->is_invoice_generated($callback['order_id'], 'estimate')) {
            $wc_freeagent->generate_estimate($callback['order_id'], 'proform');
          }
        }

        if($callback['invoice_type'] == 'invoice') {
          if(!$wc_freeagent->is_invoice_generated($callback['order_id'])) {
            $wc_freeagent->generate_invoice($callback['order_id']);
          }
        }

        if($callback['invoice_type'] == 'void') {
          if(!$wc_freeagent->is_invoice_generated($callback['order_id'], 'void')) {
            $wc_freeagent->generate_void_invoice($callback['order_id']);
          }
        }

        //Store in progress status
        add_option('_wc_freeagent_bg_generate_in_progress', true);

        return false;

			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					trigger_error( 'Unable to create the invoice in the background with the following details:: ' . serialize( $callback ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				}
			}
		}
		return false;
	}

  protected function complete() {
    //Remove in progress status
    delete_option('_wc_freeagent_bg_generate_in_progress');
    parent::complete();
  }

}
