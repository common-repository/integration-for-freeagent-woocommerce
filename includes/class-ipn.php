<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_FreeAgent_IPN', false ) ) :
	class WC_FreeAgent_IPN {

		public static function load() {
			add_action( 'init', array( __CLASS__, 'init' ) );
		}

		public static function init() {
			$queue = WC()->queue();
			$next  = $queue->get_next( 'wc_freeagent_ipn_check' );
			if ( ! $next ) {
				$queue->schedule_recurring( time(), HOUR_IN_SECONDS/2, 'wc_freeagent_ipn_check' );
			}
			add_action( 'wc_freeagent_ipn_check', array( __CLASS__, 'ipn_check' ) );
		}

		public static function ipn_check() {
			//Get orders that has an invoice, not marked paid and order is not completed
			$query = array(
				'limit' => -1,
				'meta_query' => [
        'relation' => 'OR',
					[
						'key'     => '_wc_freeagent_invoice',
						'compare' => 'EXISTS'
					],
					[
						'key'     => '_wc_freeagent_completed',
						'compare' => 'NOT EXISTS'
					]
		    ]
			);

			$orders = wc_get_orders( $query );

			foreach ($orders as $order) {

				//Get invoice data
				$invoice_id = $order->get_meta('_wc_freeagent_invoice_id');
				$invoice = WC_FreeAgent()->auth->get('invoices/'.$invoice_id);
				$order_status = $order->get_status();

				//If invoice exists, check status
				if(!$invoice['error']) {
					if($invoice['body']['invoice']['status'] == 'Paid') {
						$order->update_meta_data( '_wc_freeagent_completed', strtotime($invoice['body']['invoice']['paid_on']) );

						//Update order notes
						if($order_status != 'completed') {
							$order->update_status( 'completed' );
							$order->add_order_note( __( 'Order marked as completed by FreeAgent ', 'wc-freeagent' ) );
						}

						//If we need to close the order
						$order->save();
					}
				}

			}

			return true;
		}

		public static function retry() {
			WC()->queue()->cancel( 'wc_freeagent_ipn_check' );
			WC()->queue()->schedule_single( time() + MINUTE_IN_SECONDS*5, 'wc_freeagent_ipn_check' );
		}

  }

	WC_FreeAgent_IPN::load();

endif;
