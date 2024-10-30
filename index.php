<?php
/*
Plugin Name: Integration for FreeAgent & WooCommerce
Plugin URI: http://visztpeter.me
Description: Connect FreeAgent with your WooCommerce store
Author: Viszt Péter
Version: 1.0
WC requires at least: 3.0.0
WC tested up to: 3.7.0
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


//Generate stuff on plugin activation
function wc_freeagent_activate() {
	$upload_dir =  wp_upload_dir();

	$files = array(
		array(
			'base' 		=> $upload_dir['basedir'] . '/wc_freeagent',
			'file' 		=> 'index.html',
			'content' 	=> ''
		)
	);

	foreach ( $files as $file ) {
		if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
			if ( $file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' ) ) {
				fwrite( $file_handle, $file['content'] );
				fclose( $file_handle );
			}
		}
	}
}
register_activation_hook( __FILE__, 'wc_freeagent_activate' );

class WC_FreeAgent {

	public static $plugin_prefix;
	public static $plugin_url;
	public static $plugin_path;
	public static $plugin_basename;
	public static $version;
	protected static $background_generator = null;
	public $auth = null;

	protected static $_instance = null;

	//Get main instance
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

  //Construct
	public function __construct() {

		//Default variables
		self::$plugin_prefix = 'wc_freeagent_';
		self::$plugin_basename = plugin_basename(__FILE__);
		self::$plugin_url = plugin_dir_url(self::$plugin_basename);
		self::$plugin_path = trailingslashit(dirname(__FILE__));
		self::$version = '1.0';

		//Plugin loaded
		add_action( 'plugins_loaded', array( $this, 'init' ) );

  }

	//Load plugin stuff
	public function init() {

		//Background invoice generator
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-background-generator.php' );
		self::$background_generator = new WC_FreeAgent_Background_Generator();

		//Authentication
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-auth.php' );
		$this->auth = new WC_FreeAgent_Auth();

		//IPN
		if(get_option('_wc_freeagent_pro_enabled')) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/class-ipn.php' );
		}

		// Load includes
		if(is_admin()) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/class-settings.php' );
			require_once( plugin_dir_path( __FILE__ ) . 'includes/class-admin-notices.php' );
			require_once( plugin_dir_path( __FILE__ ) . 'includes/class-product-options.php' );

			if(get_option('_wc_freeagent_pro_enabled')) {
				require_once( plugin_dir_path( __FILE__ ) . 'includes/class-bulk-actions.php' );
			}
		}

		//Plugin links
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

		//Settings page
		if(is_admin()) {
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		}

		//Admin CSS & JS
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		//Create order metaboxes
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ), 10, 2 );

		//Ajax functions related to invoices
		add_action( 'wp_ajax_wc_freeagent_generate_invoice', array( $this, 'generate_invoice_with_ajax' ) );
		add_action( 'wp_ajax_wc_freeagent_mark_completed', array( $this, 'mark_completed_with_ajax' ) );
		add_action( 'wp_ajax_wc_freeagent_toggle_invoice', array( $this, 'toggle_invoice' ) );

		//Create a hook based on the status setup in settings to auto-generate invoice
		if(get_option('_wc_freeagent_pro_enabled')) {
			$order_auto_invoice_status = str_replace( 'wc-', '', $this->get_option('auto_invoice_status', 'completed') );
			add_action( 'woocommerce_order_status_'.$order_auto_invoice_status, array( $this, 'on_order_complete' ) );
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processing' ) );
		}

		//Order list button
		add_action( 'woocommerce_admin_order_actions_end', array( $this, 'add_listing_actions' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'orders_download_button' ), 10, 2);

		//Attach invoices to emails
		if($this->get_option('email_attachment_file', 'no') == 'yes') {
			add_filter( 'woocommerce_email_attachments', array( $this, 'email_attachment_file'), 10, 3 );
		}

		//Add loading indicator to admin bar for background generation
		add_action('admin_bar_menu', array( $this, 'background_generator_loading_indicator'), 55);
		add_action('wp_ajax_wc_freeagent_bg_generate_status', array( $this, 'background_generator_status' ) );
		add_action('wp_ajax_wc_freeagent_bg_generate_stop', array( $this, 'background_generator_stop' ) );

		//Disable invoices on free orders
		add_action('woocommerce_checkout_order_processed', array( $this, 'disable_invoice_for_free_order' ), 10, 3);

		//Add freeagent contact column to users table
		add_filter( 'manage_users_columns', array( $this, 'add_users_contact_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'display_users_contact_column' ), 10, 3 );

	}

	//Integration page
	public function add_integration( $integrations ) {
		$integrations[] = 'WC_FreeAgent_Settings';
		return $integrations;
	}

  //Add CSS & JS
	public function admin_init() {
		wp_enqueue_script( 'wc_freeagent_print_js', plugins_url( '/assets/js/print.min.js',__FILE__ ), array('jquery'), WC_FreeAgent::$version, TRUE );
		wp_enqueue_script( 'wc_freeagent_admin_js', plugins_url( '/assets/js/admin.js',__FILE__ ), array('jquery'), WC_FreeAgent::$version, TRUE );
		wp_enqueue_style( 'wc_freeagent_admin_css', plugins_url( '/assets/css/admin.css',__FILE__ ), array(), WC_FreeAgent::$version );

		$wc_freeagent_local = array( 'loading' => plugins_url( '/assets/images/ajax-loader.gif',__FILE__ ) );
		wp_localize_script( 'wc_freeagent_admin_js', 'wc_freeagent_params', $wc_freeagent_local );
  }

	//Meta box on order page
	public function add_metabox( $post_type, $post ) {
		add_meta_box('wc_freeagent_metabox', 'FreeAgent', array( $this, 'render_meta_box_content' ), 'shop_order', 'side');
	}

	//Render metabox content
	public function render_meta_box_content($post) {
		$order = wc_get_order($post->ID);
		include( dirname( __FILE__ ) . '/includes/views/html-metabox.php' );
	}

	//Generate Invoice with Ajax
	public function generate_invoice_with_ajax() {
		check_ajax_referer( 'wc_freeagent_generate_invoice', 'nonce' );
		$order_id = intval($_POST['order']);
		$type = sanitize_text_field($_POST['type']);
		if($type == 'estimate') {
			$response = $this->generate_estimate($order_id, $type);
		} else {
			$response = $this->generate_invoice($order_id, $type);
		}
		wp_send_json_success($response);
	}

	//Mark completed with Ajax
	public function mark_completed_with_ajax() {
		check_ajax_referer( 'wc_freeagent_generate_invoice', 'nonce' );
		$order_id = intval($_POST['order']);
		$response = $this->generate_invoice_complete($order_id);
		wp_send_json_success($response);
	}

	//If the invoice is already generated without the plugin
	public function toggle_invoice() {
		check_ajax_referer( 'wc_freeagent_generate_invoice', 'nonce' );

		if ( !current_user_can( 'edit_shop_orders' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		$orderid = intval($_POST['order']);
		$order = wc_get_order($orderid);
		$note = sanitize_text_field($_POST['note']);
		$invoice_own = $order->get_meta('_wc_freeagent_own');
		$response = array();

		if($invoice_own) {
			$response['state'] = 'on';
			$order->delete_meta_data('_wc_freeagent_own');
			$response['messages'][] = esc_html__('Invoicing turned on.','wc-freeagent');
		} else {
			$response['state'] = 'off';
			$order->update_meta_data( '_wc_freeagent_own', $note );
			$response['messages'][] = esc_html__('Invoicing turned off.','wc-freeagent');
		}

		//Save the order
		$order->save();

		wp_send_json_success($response);
	}

	//Generate XML for Szamla Agent
	public function generate_invoice($orderId, $type = 'invoice') {
		$order = wc_get_order($orderId);
		$order_items = $order->get_items();

		//Response
		$response = array();
		$response['error'] = false;
		$response['type'] = $type;

		//Check if contact already exists
		$contact_id = $this->create_contact($order);

		//If we were unable to create a contact
		if(isset($contact_id['error'])) {
			return $contact_id; //this is actually an error response
		}

		//Now create the invoice
		$invoiceData = [
			'contact' => $contact_id,
			'status' => 'Open',
			'dated_on' => date('Y-m-d'),
			'due_on' => date('Y-m-d'),
			'payment_terms_in_days' => $this->get_payment_method_deadline($order->get_payment_method()),
			'currency' => $order->get_currency(),
			'exchange_rate' => $this->get_exchange_rate($order,true),
			'invoice_items' => array(),
			'comments' => $this->get_option('note'),
			'payment_terms' => $order->get_payment_method_title(),
			'po_reference' => $order->get_order_number(),
		];

		//Reference number
		if($this->get_invoice_number($order, $type)) {
			$invoiceData['reference'] = $this->get_invoice_number($order, $type);
		}

		//If custom details submitted
		if(isset($_POST['note']) && isset($_POST['deadline']) && isset($_POST['completed'])) {
			$invoiceData['comments'] = sanitize_text_field($_POST['note']);
			$invoiceData['payment_terms_in_days'] = intval($_POST['deadline']);
			$invoiceData['due_on'] = sanitize_text_field($_POST['completed']);
		}

		//Replace customer email and phone number in note
		$note_replacements = array('{customer_email}' => $order->get_billing_email(), '{customer_phone}' => $order->get_billing_phone());
		$invoiceData['comments'] = str_replace( array_keys( $note_replacements ), array_values( $note_replacements ), $invoiceData['comments']);

		//Product items
		$invoice_items = $this->get_invoice_items($order);
		$invoiceData['invoice_items'] = $invoice_items['items'];
		$invoiceData['comments'] .= $invoice_items["comment"];

		//Try to create the invoice
		$invoice = $this->auth->post('invoices', array('invoice' => apply_filters('wc_freeagent_invoice_data', $invoiceData, $order)));
		if($invoice['error']) {

			//Create response
			$response['error'] = true;
			$response['messages'][] = $invoice['error_message'];

			//Save order note
			$order->add_order_note(esc_html__('FreeAgent was unable to create an invoice, because:', 'wc-freeagent') . $invoice['error_message']);

			//Callbacks
			do_action('wc_freeagent_after_invoice_error', $order, $response);

			return $response;
		}

		//If we are here, we have the invoice ready
		$invoice_url = $invoice['body']['invoice']['url'];
		$invoice_name = $invoice['body']['invoice']['reference'];
		$invoice_id = $this->get_invoice_id($invoice_url);

		//Mar invoice as sent
		$mark_as_sent = $this->auth->put('invoices/'.$invoice_id.'/transitions/mark_as_sent');

		//Mark invoice as paid if needed
		$is_invoice_already_paid = false;
		if($this->check_payment_method_options($order->get_payment_method(), 'complete')) {

			//Get bank account id
			$bank_account_id = $this->check_payment_method_options($order->get_payment_method(), 'bank_account');
			if($bank_account_id) {
				$transaction = array(
					'bank_account' => $bank_account_id,
					'paid_invoice' => $invoice_url,
					'gross_value' => $this->get_order_total_in_native_currency($order),
					'description' => 'Payment for order number '.$order->get_order_number(),
					'dated_on' => date('Y-m-d'),
					'foreign_currency_value' => $order->get_total()
				);

				$transaction = $this->auth->post('bank_transaction_explanations', apply_filters('wc_freeagent_transaction_data', $transaction, $order));

				if(!$transaction['error']) {
					$is_invoice_already_paid = true;
				}
			}
		}

		//Send email if needed
		if($this->get_option('auto_email', 'yes') == 'yes') {
			$email_data = array(
				'invoice' => array(
					'email' => array(
						'use_template' => true
					)
				)
			);

			$emailed = $this->auth->post('invoices/'.$invoice_id.'/send_email', $email_data);
		}

		//Download PDF
		$invoice_pdf = $this->download_invoice('invoice', $orderId, $invoice_id);

		//Do we sent an email?
		$auto_email_sent = ($this->get_option('auto_email', 'yes') == 'yes');

		//If we have an estimate, close it
		if($this->is_invoice_generated($orderId, 'estimate')) {
			$estimate_id = $order->get_meta('_wc_freeagent_estimate_id');
			$this->auth->put('estimates/'.$estimate_id.'/', array('estimate' => array('invoice' => $invoice_url)));
		}

		//Create response
		$response['name'] = $invoice_name;
		$response['messages'][] = ($auto_email_sent) ? esc_html__('Invoice generated and sent to the customer.','wc-freeagent') : esc_html__('Invoice generated.','wc-freeagent');

		//Update order notes
		$order->add_order_note(esc_html__('FreeAgent invoice successfully generated. Reference number: ', 'wc-freeagent') . $invoice_name);

		//Store the filename
		$order->update_meta_data( '_wc_freeagent_invoice', $invoice_name );
		$order->update_meta_data( '_wc_freeagent_invoice_pdf', $invoice_pdf );
		$order->update_meta_data( '_wc_freeagent_invoice_id', $invoice_id );

		//Mark as paid if needed
		if($is_invoice_already_paid) {
			$order->update_meta_data( '_wc_freeagent_completed', time() );
			$response['completed'] = date('Y-m-d', time());
		}

		//Return download links
		$response['link'] = $this->generate_download_link($order);

		//Delete void invoice if exists
		$order->delete_meta_data( '_wc_freeagent_void' );
		$order->delete_meta_data( '_wc_freeagent_void_pdf' );

		//Save the order
		$order->save();

		//Run action on successful invoice creation
		do_action('wc_freeagent_after_invoice_success', $order, $response);

		return $response;
	}

	//Generate XML for Szamla Agent
	public function generate_estimate($orderId, $type = 'estimate') {
		$order = wc_get_order($orderId);
		$order_items = $order->get_items();

		//Response
		$response = array();
		$response['error'] = false;
		$response['type'] = $type;

		//Check if contact already exists
		$contact_id = $this->create_contact($order);

		//If we were unable to create a contact
		if(isset($contact_id['error'])) {
			return $contact_id; //this is actually an error response
		}

		//Now create the invoice
		$estimateData = [
			'contact' => $contact_id,
			'estimate_type' => $this->get_option('estimate_type', 'Estimate'),
			'status' => 'Sent',
			'dated_on' => date('Y-m-d'),
			'currency' => $order->get_currency(),
			'estimate_items' => array(),
			'notes' => $this->get_option('note'),
		];

		//Reference number
		if($this->get_invoice_number($order, $type)) {
			$estimateData['reference'] = $this->get_invoice_number($order, $type);
		}

		//If custom details submitted
		if(isset($_POST['note']) && isset($_POST['deadline']) && isset($_POST['completed'])) {
			$estimateData['notes'] = sanitize_text_field($_POST['note']);
		}

		//Replace customer email and phone number in note
		$note_replacements = array('{customer_email}' => $order->get_billing_email(), '{customer_phone}' => $order->get_billing_phone());
		$estimateData['notes'] = str_replace( array_keys( $note_replacements ), array_values( $note_replacements ), $estimateData['notes']);

		//Product items
		$estimate_items = $this->get_invoice_items($order);
		$estimateData['notes'] .= $estimate_items["comment"];

		//Set item types for esimate items of none is set
		foreach ($estimate_items['items'] as $item_id => $estimate_item) {
			if(!$estimate_item['item_type']) {
				$estimate_items['items'][$item_id]['item_type'] = 'Products';
			}
		}

		//Try to create the invoice
		$estimate = $this->auth->post('estimates', array('estimate' => apply_filters('wc_freeagent_estimate_data', $estimateData, $order)));
		if($estimate['error']) {

			//Create response
			$response['error'] = true;
			$response['messages'][] = $estimate['error_message'];

			//Save order note
			$order->add_order_note(esc_html__('FreeAgent was unable to create an estimate, because:', 'wc-freeagent') . $estimate['error_message']);

			//Callbacks
			do_action('wc_freeagent_after_estimate_error', $order, $response);

			return $response;
		}

		//If we are here, we have the invoice ready
		$estimate_url = $estimate['body']['estimate']['url'];
		$estimate_name = $estimate['body']['estimate']['reference'];
		$estimate_id = $this->get_invoice_id($estimate_url);

		//After estimate was created, update it with the line items(this time we can set custom units, not just time units)
		$test = $this->auth->put('estimates/'.$estimate_id.'/', array('estimate' => array('estimate_items' => $estimate_items['items'])));

		//Send email if needed
		if($this->get_option('auto_email', 'yes') == 'yes') {
			$mark_as_sent = $this->auth->put('estimates/'.$estimate_id.'/transitions/mark_as_sent');
		}

		//Download PDF
		$estimate_pdf = $this->download_invoice('estimate', $orderId, $estimate_id);

		//Do we sent an email?
		$auto_email_sent = ($this->get_option('auto_email', 'yes') == 'yes');

		//Create response
		$response['name'] = $estimate_name;
		$response['messages'][] = ($auto_email_sent) ? esc_html__('Estimate generated and sent to the customer.','wc-freeagent') : esc_html__('Estimate generated.','wc-freeagent');

		//Update order notes
		$order->add_order_note(esc_html__('FreeAgent estimate successfully generated. Reference number: ', 'wc-freeagent') . $estimate_name);

		//Store the filename
		$order->update_meta_data( '_wc_freeagent_estimate', $estimate_name );
		$order->update_meta_data( '_wc_freeagent_estimate_pdf', $estimate_pdf );
		$order->update_meta_data( '_wc_freeagent_estimate_id', $estimate_id );

		//Return download links
		$response['link'] = $this->generate_download_link($order, 'estimate');

		//Delete void invoice if exists
		$order->delete_meta_data( '_wc_freeagent_void' );
		$order->delete_meta_data( '_wc_freeagent_void_pdf' );

		//Save the order
		$order->save();

		//Run action on successful estimate creation
		do_action('wc_freeagent_after_estimate_success', $order, $response);

		return $response;
	}

	//Mark invoice as paid
	public function generate_invoice_complete($orderId) {
		$order = wc_get_order($orderId);

		//Response
		$response = array();
		$response['error'] = false;

		//Get bank account id
		$bank_account_id = $this->check_payment_method_options($order->get_payment_method(), 'bank_account');

		//Create transaction info
		$transaction = array(
			'bank_account' => $bank_account_id,
			'paid_invoice' => $order->get_meta('_wc_freeagent_invoice'),
			'gross_value' => $this->get_order_total_in_native_currency($order),
			'description' => 'Payment for order number '.$order->get_order_number(),
			'dated_on' => date('Y-m-d'),
			'foreign_currency_value' => $order->get_total()
		);

		//Try to create transaction
		$transaction = $this->auth->post('bank_transaction_explanations', apply_filters('wc_freeagent_transaction_data', $transaction, $order));

		if($transaction['error']) {
			$response['error'] = true;
			$response['messages'][] = esc_html__('Unable to mark this invoice as paid.', 'wc-freeagent');
			$response['messages'][] = $transaction['error_message'];

			return $response;
		} else {

			//Store as a custom field
			$order->update_meta_data( '_wc_freeagent_completed', time() );

			//Update order notes
			$order->add_order_note( esc_html__( 'Invoice marked as paid.', 'wc-freeagent' ) );

			//Save order
			$order->save();

			//Response
			$response['completed'] = date('Y-m-d');

			return $response;
		}

	}

	//Autogenerate invoice
	public function on_order_complete( $order_id ) {

		//Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled
		if($this->get_option('auto_generate') == 'yes') {

			//What are we creating?
			$order = wc_get_order($order_id);
			$document_type = 'invoice';
			$is_already_generated = $this->is_invoice_generated($order_id, $document_type);
			$return_info = false;
			$deferred = ($this->get_option('defer', 'no') == 'yes');

			//Don't create deferred if we are in an admin page and only mark one order completed
			if(is_admin() && isset( $_GET['action']) && $_GET['action'] == 'woocommerce_mark_order_status') {
				$deferred = false;
			}

			//Don't defer if we are just changing one or two order status using bulk actions
			if(is_admin() && isset($_GET['_wp_http_referer']) && isset($_GET['post']) && count($_GET['post']) < 3) {
				$deferred = false;
			}

			if(!$is_already_generated) {

				//Check if we generate this invoice deferred
				if($deferred) {
					self::$background_generator->push_to_queue(
						array(
							'invoice_type' => 'invoice',
							'order_id' => $order_id
						)
					);
					self::$background_generator->save()->dispatch();
				} else {
					$return_info = $this->generate_invoice($order_id);
				}

			}

			if($return_info && $return_info['error']) {
				$this->on_auto_invoice_error($order_id);
			}

		}

	}

	//Autogenerate estimate or contact
	public function on_order_processing( $order_id ) {

		//Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled
		$order = wc_get_order($order_id);
		$payment_method = $order->get_payment_method();

		//Generate contact if needed
		if($this->get_option('auto_contact', 'no') == 'yes') {
			$contact_id = $this->create_contact($order);
		}

		//Generate estimate
		if($this->check_payment_method_options($payment_method, 'estimate') && !$this->is_invoice_generated($order_id, 'estimate')) {
			if($this->get_option('defer') == 'yes') {
				self::$background_generator->push_to_queue(
					array(
						'invoice_type' => 'estimate',
						'order_id' => $order_id
					)
				);
				self::$background_generator->save()->dispatch();
			} else {
				$return_info = $this->generate_estimate($order_id);
			}

		}

	}

	//Send email on error
	public function on_auto_invoice_error( $order_id ) {
		update_option('_wc_freeagent_error', $order_id);

		//Check if we need to send an email todo
		if($this->get_option('error_email')) {
			$order = wc_get_order($order_id);
			$mailer = WC()->mailer();
			$content = wc_get_template_html( 'includes/emails/invoice-error.php', array(
				'order'         => $order,
				'email_heading' => __('Sikertelen számlakészítés', 'wc-freeagent'),
				'plain_text'    => false,
				'email'         => $mailer,
				'sent_to_admin' => true,
			), '', plugin_dir_path( __FILE__ ) );
			$recipient = $this->get_option('error_email');
			$subject = __("Sikertelen számlakészítés", 'wc-freeagent');
			$headers = "Content-Type: text/html\r\n";
			$mailer->send( $recipient, $subject, $content, $headers );
		}

	}

	//Check if it was already generated or not
	public function is_invoice_generated( $order_id, $type = 'invoice' ) {
		$order = wc_get_order($order_id);
		return ($order->get_meta('_wc_freeagent_'.$type));
	}

	//Add icon to order list to show invoice
	public function add_listing_actions( $order ) {
		$order_id = $order->get_id();

		$invoice_types = array(
			'invoice' => esc_attr__('Invoice','wc-freeagent'),
			'estimate' => esc_attr__('Estimate','wc-freeagent')
		);

		foreach ($invoice_types as $invoice_type => $invoice_label) {
			if($this->is_invoice_generated($order_id, $invoice_type)):
			?>
				<a href="<?php echo $this->generate_download_link($order, $invoice_type); ?>" class="button tips wc-freeagent-button" target="_blank" alt="" data-tip="<?php echo $invoice_label; ?>">
					<img src="<?php echo WC_FreeAgent::$plugin_url . 'assets/images/icon-'.$invoice_type.'.svg'; ?>" alt="" width="16" height="16">
				</a>
			<?php
			endif;
		}
	}

	//Generate download url
	public function generate_download_link( $order, $type = 'invoice', $absolute = false) {
		if($order) {
			$pdf_name = '';
			$pdf_name = $order->get_meta('_wc_freeagent_'.$type.'_pdf');

			if($pdf_name) {
				$paths = $this->get_pdf_file_path('invoice', 0);
				if($absolute) {
					$pdf_file_url = $paths['basedir'].$pdf_name;
				} else {
					$pdf_file_url = $paths['baseurl'].$pdf_name;
				}
				return apply_filters('wc_freeagent_download_link', esc_url($pdf_file_url), $order);
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	//Add download icons to order details page
	public function orders_download_button($actions, $order) {
		$order_id = $order->get_id();
		if($this->get_option('customer_download','no') == 'yes') {
			$document_types = array(
	      'estimate' => esc_html__('Estimate','wc-freeagent'),
	      'invoice' => esc_html__('Invoice','wc-freeagent')
	    );

			foreach ($document_types as $document_type => $document_label) {
				if($this->is_invoice_generated($order_id, $document_type)) {
					$link = $this->generate_download_link($order, $document_type);
					$actions['wc_freeagent_pdf'] = array(
						'url'  => $link,
						'name' => $document_label
					);
				}
			}
		}
		return $actions;
	}

	//Get options stored
	public function get_option($key, $default = '') {
		$settings = get_option( 'woocommerce_wc_freeagent_settings', null );
		$value = $default;

		if($settings && isset($settings[$key]) && !empty($settings[$key])) {
			$value = $settings[$key];
		} else if(get_option($key)) {
			$value = get_option($key);
		}

		return apply_filters('wc_freeagent_get_option', $value, $key);
	}

	//Email attachment file
	public function email_attachment_file($attachments, $email_id, $order){
		if(!is_a( $order, 'WC_Order' )) return $attachments;
		$order_id = $order->get_id();
		$order = wc_get_order($order_id);

		$invoice_types = array('invoice', 'estimate', 'void');
		foreach ($invoice_types as $invoice_type) {
			$invoice_email_ids = $this->get_option('email_attachment_'.$invoice_type, array());
			if($invoice_email_ids && !empty($invoice_email_ids)) {
				if(in_array($email_id,$invoice_email_ids)) {
					if($this->is_invoice_generated($order_id, $invoice_type)) {
						$pdf_name = $order->get_meta('_wc_freeagent_'.$invoice_type.'_pdf');
						if(strpos($pdf_name, '.pdf') !== false) {
							$attachments[] = $this->generate_download_link($order, $invoice_type, true);
						}
					}
				}
			}
		}
		return $attachments;
	}

	//Plugin links
	public function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url(admin_url( 'admin.php?page=wc-settings&tab=integration&section=wc_freeagent' )) . '" aria-label="' . esc_attr__( 'FreeAgent WooCommerce Settings', 'wc-freeagent' ) . '">' . esc_html__( 'Settings', 'wc-freeagent' ) . '</a>',
			'documentation' => '<a href="https://freeagent.visztpeter.me/documentation/" target="_blank" aria-label="' . esc_attr__( 'FreeAgent WooCommerce Documentation', 'wc-freeagent' ) . '">' . esc_html__( 'Documentation', 'wc-freeagent' ) . '</a>'
		);

		if (!get_option('_wc_freeagent_pro_enabled') ) {
			$action_links['get-pro'] = '<a target="_blank" rel="noopener noreferrer" style="color:#46b450;" href="https://freeagent.visztpeter.me/" aria-label="' . esc_attr__( 'FreeAgent WooCommerce Pro version', 'wc-freeagent' ) . '">' . esc_html__( 'Pro version', 'wc-freeagent' ) . '</a>';
		}
		return array_merge( $action_links, $links );
	}

	public function check_payment_method_options($payment_method_id, $option) {
		$found = false;
		$payment_method_options = $this->get_option('wc_freeagent_payment_method_options');
		if(isset($payment_method_options[$payment_method_id]) && isset($payment_method_options[$payment_method_id][$option])) {
			$found = $payment_method_options[$payment_method_id][$option];
		}
		return $found;
	}

	public function get_payment_method_deadline($payment_method_id) {
		$deadline = $this->get_option('payment_deadline');
		$custom_deadline = $this->check_payment_method_options($payment_method_id, 'deadline');
		if($custom_deadline) $deadline = $custom_deadline;
		return $deadline;
	}

	public function get_order_item_tax_label($order, $item) {
		$tax_item_label = 0;

		if($this->get_option('separate_coupon') == 'yes') {
			if(round($item->get_subtotal(), 2) == 0) {
				$tax_item_label = 0;
			} else {
				$tax_item_label = round( ($item->get_subtotal_tax()/$item->get_subtotal()) * 100 );
			}
		} else {
			if(round($item->get_total(), 2) == 0) {
				$tax_item_label = 0;
			} else {
				$tax_item_label = round( ($item->get_total_tax()/$item->get_total()) * 100 );
			}
		}

		return $tax_item_label;
	}

	public function get_order_shipping_tax_label($order, $shipping_item_obj) {
		$tax_item_label = 0;

		$order_shipping = $shipping_item_obj->get_total();
		$order_shipping_tax = $shipping_item_obj->get_total_tax();
		$tax_item_label = round(($order_shipping_tax/$order_shipping)*100);

		return $tax_item_label;
	}

	public function get_coupon_invoice_item_details($order) {
		$details = array(
			"title" => esc_html__('Kedvezmény', 'wc-freeagent'),
			"desc" => ''
		);

		$order_discount = method_exists( $order, 'get_discount_total' ) ? $order->get_discount_total() : $order->order_discount;
		if ( $order_discount > 0 ) {
			$coupons = implode(', ', $order->get_used_coupons());
			$discount = strip_tags(html_entity_decode($order->get_discount_to_display()));
			$details["desc"] = sprintf( __( '%1$s kedvezmény a következő kupon kóddal: %2$s', 'wc-freeagent' ), $discount, $coupons );

			if($this->get_option('separate_coupon_name')) {
				$details["title"] = $this->get_option('separate_coupon_name');
			}

			if($this->get_option('separate_coupon_desc')) {
				$discount_note_replacements = array('{kedvezmeny_merteke}' => $discount, '{kupon}' => $coupons);
				$discount_note = str_replace( array_keys( $discount_note_replacements ), array_values( $discount_note_replacements ), $this->get_option('separate_coupon_desc'));
				$details["desc"] = $discount_note;
			}
		}

		return $details;
	}

	//Check background generation status with ajax
	public function background_generator_status() {
		check_ajax_referer( 'wc-freeagent-bg-generator', 'nonce' );
		$in_progress = get_option('_wc_freeagent_bg_generate_in_progress');
		$response = array();
		if($in_progress) {
			$response['finished'] = false;
		} else {
			$response['finished'] = true;
		}
		wp_send_json_success($response);
		wp_die();
	}

	//Stop background generation with ajax
	public static function background_generator_stop() {
		check_ajax_referer( 'wc-freeagent-bg-generator', 'nonce' );
		self::$background_generator->kill_process();
		delete_option('_wc_freeagent_bg_generate_in_progress');
		wp_send_json_success();
		wp_die();
	}

	//Add loading indicator to menu bar
	public function background_generator_loading_indicator($wp_admin_bar) {
		if(get_option('_wc_freeagent_bg_generate_in_progress')) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'top-secondary',
					'id'     => 'wc-freeagent-bg-generate-loading',
					'title'  => '<div class="loading"><em></em><strong>Generating invoices...</strong></div><div class="finished"><em></em><strong>Invoices generated</strong></div>',
					'href'   => '',
				)
			);

			$wp_admin_bar->add_menu(
				array(
					'parent' => 'wc-freeagent-bg-generate-loading',
					'id'     => 'wc-freeagent-bg-generate-loading-msg',
					'title'  => '<div class="loading"><span>FreeAgent is generating invoices in the background.</span> <a href="#" id="wc-freeagent-bg-generate-stop" data-nonce="'.wp_create_nonce( 'wc-freeagent-bg-generator' ).'">Stop</a></div><div class="finished"><span>Invoices generated successfully. Refresh the site(press F5), so you can see the invoices.</span> <a href="#" id="wc-freeagent-bg-generate-refresh">Refresh</a></div>',
					'href'   => '',
				)
			);
		}
	}

	//Log error message if needed
	public function log_error_messages($error, $source) {
		$logger = wc_get_logger();
		$logger->error(
			$source.' - '.json_encode($error),
			array( 'source' => 'wc_freeagent' )
		);
	}

	//Log debug messages if needed
	public function log_debug_messages($data, $source, $force = false) {
		if($this->get_option('debug', 'no') == 'yes' || $force) {
			$logger = wc_get_logger();
			$logger->debug(
				$source.' - '.json_encode($data),
				array( 'source' => 'wc_freeagent' )
			);
		}
	}

	//Disable invoice generation for free orders
	function disable_invoice_for_free_order($order_id, $data, $order) {
		$order_total = $order->get_total();

		if($order_total == 0) {
			$order->update_meta_data( '_wc_freeagent_own', __('Invoicing disabled for free orders', 'wc-freeagent') );
			$order->save();
		}
	}

	//Get file path for pdf files
	public function get_pdf_file_path($type, $order_id) {
		$upload_dir = wp_upload_dir( null, false );
		$basedir = $upload_dir['basedir'] . '/wc_freeagent/';
		$baseurl = $upload_dir['baseurl'] . '/wc_freeagent/';
		$random_file_name = substr(md5(rand()),5);
		$pdf_file_name = implode( '-', array( $type, $order_id, $random_file_name ) ).'.pdf';
		$pdf_file_path = $basedir.$pdf_file_name;
		return array('name' => $pdf_file_name, 'path' => $pdf_file_path, 'baseurl' => $baseurl, 'basedir' => $basedir);
	}

	//Get contact info
	public function get_contact_id($order) {
		$customer_id = $order->get_customer_id();
		$contact_id = false;

		//If its a registered user, look up meta
		if($customer_id) {
			$contact_id = get_user_meta($customer_id, '_wc_freeagent_contact_id', true);
			if($contact_id) {
				return $contact_id;
			}
		}

		//Otherwise, find an order with the same email address and see if meta is stored there(guest checkout doesn't create a user)
		$orders = wc_get_orders( array(
			'billing_email' => $order->get_billing_email()
		));

		foreach($orders as $order_found) {
			if($order_found->get_meta('_wc_freeagent_contact_id')) {
				$contact_id = $order_found->get_meta('_wc_freeagent_contact_id');
			}
		}

		//If still nothing, get all contacts from FreeAgent and find a matching one via email address and name
		$api_response = $this->auth->get('contacts?view=active');
		if(!$api_response['error'] && isset($api_response['body']) && isset($api_response['body']['contacts'])) {
			foreach ($api_response['body']['contacts'] as $contact) {
				if($order->get_billing_email() == $contact['email'] && $order->get_billing_first_name() == $contact['first_name'] && $order->get_billing_last_name() == $contact['last_name']) {
					$contact_id = $contact['url'];
				}
			}
		}

		return $contact_id;
	}

	public function get_invoice_number($order, $type) {
		$invoice_number = '';
		$invoice_number_template = $this->get_option($type.'_number');
		$placeholders = array(
			'{year}' => $order->get_date_created()->date("Y"),
			'{month}' => $order->get_date_created()->date("m"),
			'{day}' => $order->get_date_created()->date("d"),
			'{order_number}' => $order->get_order_number(),
		);

		if($type == 'estimate' && !$invoice_number_template) $invoice_number_template = 'ESTIMATE/{order_number}';

		if($invoice_number_template) {
			$invoice_number = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $invoice_number_template);
		}

		return $invoice_number;
	}

	public function get_exchange_rate($order, $refresh = false) {
		$base_currency = $this->get_option('company_currency', get_woocommerce_currency());
		$invoice_currency = $order->get_currency();
		if($base_currency == $invoice_currency) {
			return 1;
		} else {
			$exchange_rate = get_transient( '_wc_freeagent_exchange_rate_'.strtolower($invoice_currency) );
			if(!$exchange_rate || $refresh) {
				$exchange_rate = json_decode(wp_remote_retrieve_body( wp_remote_get( 'https://api.exchangeratesapi.io/latest?symbols='.$base_currency.'&base='.$invoice_currency ) ));
				$exchange_rate = $exchange_rate->rates->$base_currency;
				set_transient( '_wc_freeagent_exchange_rate_'.strtolower($invoice_currency), $exchange_rate, 60*60*12 );
			}

			return $exchange_rate;
		}
	}

	public function get_invoice_id($invoice_url) {
		$parts = explode('/', $invoice_url);
		return end($parts);
	}

	public function get_bank_accounts($refresh = false) {
		$bank_accounts = get_transient('wc_freeagent_bank_accounts');
		if (!$bank_accounts || $refresh) {

			//Create a simple array
			$bank_accounts = array();

			//Get categores
			$accounts = $this->auth->get('bank_accounts');

			//Check for errors
			if($accounts['error']) {
				$this->log_error_messages($categories, 'get_bank_accounts');
			} else {
				foreach ($accounts['body']['bank_accounts'] as $account) {
					$bank_accounts[$account['url']] = $account['name'];
				}
			}

			set_transient('wc_freeagent_bank_accounts', $bank_accounts, 60 * 60 * 24);
		}

		return $bank_accounts;
	}

	public function get_order_total_in_native_currency($order) {
		$order_total = $order->get_total();
		$exchange_rate = $this->get_exchange_rate($order, true);
		return $order_total*$exchange_rate;
	}

	public function download_invoice($type, $orderId, $invoice_id) {
		//Setup PDF file
		$pdf_file_path = $this->get_pdf_file_path($type, $orderId);
		$pdf_file_name = $pdf_file_path['name'];

		$pdf_file_request = $this->auth->get($type.'s/'.$invoice_id.'/pdf');
		if(isset($pdf_file_request['body']) && isset($pdf_file_request['body']['pdf'])) {
			file_put_contents($pdf_file_path['path'], base64_decode($pdf_file_request['body']['pdf']['content']));
		}

		return $pdf_file_name;
	}

	public function create_contact($order) {
		$contact_id = $this->get_contact_id($order);

		//If we don't have a contact id yet, create a contact
		if(!$contact_id) {

			//Setup contact data
			$contactData = [
				'first_name' => $order->get_billing_first_name(),
				'last_name' => $order->get_billing_last_name(),
				'organisation_name' => $order->get_billing_company(),
				'email' => $order->get_billing_email(),
				'billing_email' => $order->get_billing_email(),
				'phone_number' => $order->get_billing_phone(),
				'mobile' => $order->get_billing_phone(),
				'address1' => $order->get_billing_address_1(),
				'address2' => $order->get_billing_address_2(),
				'town' => $order->get_billing_city(),
				'region' => $order->get_billing_state(),
				'postcode' => $order->get_billing_postcode(),
				'country' => WC()->countries->countries[$order->get_billing_country()] ? : '',
				'locale' => $this->get_option('language', 'en')
			];

			//Language based on WPML
			if($this->get_option('language_wpml') == 'yes') {
				$wpml_lang_code = get_post_meta( $orderId, 'wpml_language', true );
				if(!$wpml_lang_code && function_exists('pll_get_post_language')){
					$wpml_lang_code = pll_get_post_language($orderId, 'locale');
				}
				if($wpml_lang_code && in_array($wpml_lang_code, array('bg', 'ca', 'cy', 'cz', 'de', 'dk', 'en', 'es', 'et', 'fi', 'fr', 'is', 'it', 'nl', 'nk', 'ro', 'rs', 'ru', 'se', 'sk', 'tr'))) {
					$contactData['locale'] = $wpml_lang_code;
				}
			}

			//Create contact
			$contact = $this->auth->post('contacts', apply_filters('wc_freeagent_contact_data', $contactData, $order));

			if($contact['error']) {
				$response['error'] = true;
				$response['messages'][] = $contact['error_message'];
				return $response;
			} else {

				//Get contact id
				$contact_id = $contact['body']['contact']['url'];

				//Store the contact id
				if($order->get_customer_id()) {
					update_user_meta($order->get_customer_id(), '_wc_freeagent_contact_id', $contact_id);
				} else {
					$order->update_meta_data('_wc_freeagent_contact_id', $contact_id);
					$order->save();
				}
			}

		}

		//If its not a registered customer, store the ID in order meta
		if(!$order->get_customer_id()) {
			$order->update_meta_data('_wc_freeagent_contact_id', $contact_id);
			$order->save();
		}

		return $contact_id;
	}

	public function get_local_contact($order) {
		$customer_id = $order->get_customer_id();
		$contact_href = '';
		$contact_id = '';

		//If its a registered user, look up meta
		if($customer_id) {
			$contact_href = get_user_meta($customer_id, '_wc_freeagent_contact_id', true);
		} else {
			$contact_href = $order->get_meta('_wc_freeagent_contact_id');
		}


		if($contact_href) {

			//Get ID from url
			$contact_id = explode('/', $contact_href);
			$contact_id = end($contact_id);

			return array(
				'contact_id' => $contact_id,
				'contact_link' => $this->get_contact_link($contact_id)
			);
		} else {
			return false;
		}

	}

	public function get_contact_link($contact_id) {
		$subdomain = get_option('_wc_freeagent_domain', '');
		$domain = ($this->get_option('sandbox')) ? 'https://'.$subdomain.'.sandbox.freeagent.com/' : 'https://'.$subdomain.'.freeagent.com/';
		return $domain.'contacts/'.$contact_id;
	}

	public function add_users_contact_column( $column ) {
		$column['wc_freeagent_contact'] = 'FreeAgent Contact';
		return $column;
	}

	public function display_users_contact_column( $val, $column_name, $user_id ) {
		switch ($column_name) {
			case 'wc_freeagent_contact' :
				$contact_href = get_user_meta($user_id, '_wc_freeagent_contact_id', true);
				if($contact_href) {
					$contact_id = explode('/', $contact_href);
					$contact_id = end($contact_id);
					return '<a target="_blank" href="'.esc_url($this->get_contact_link($contact_id)).'">#'.esc_html($contact_id).'</a>';
				}
			default:
		}
		return $val;
	}

	public function get_invoice_items($order) {
		$order_items = $order->get_items();
		$invoice_items = array();
		$invoice_comment = '';
		foreach( $order_items as $order_item ) {
			$line_item = array(
				'item_type' => $this->get_option('unit_type'),
				'quantity' => $order_item->get_quantity(),
				'description' => esc_html($order_item->get_name())
			);

			//Custom product name
			if($order_item->get_product() && $order_item->get_product()->get_meta('wc_freeagent_line_item_name')) {
				$line_item['description'] = esc_html($order_item->get_product()->get_meta('wc_freeagent_line_item_name'));
			}

			//Custom unit type
			if($order_item->get_product() && $order_item->get_product()->get_meta('wc_freeagent_line_item_unit')) {
				$line_item['item_type'] = $order_item->get_product()->get_meta('wc_freeagent_line_item_unit');
			}

			//Check if we need total or subtotal(total includes discount)
			$subtotal = $order_item->get_total();
			if($this->get_option('separate_coupon') == 'yes') {
				$subtotal = $order_item->get_subtotal();
			}

			//Set price
			$line_item['price'] = $subtotal/$order_item->get_quantity();

			//Show variation details if needed
			$product_name = $order_item->get_name();
			$note = '';
			if(strpos($product_name, ' - ') === false) {
				$note = html_entity_decode(wp_strip_all_tags(wc_display_item_meta( $order_item, array(
					'before'    => "\n- ",
					'separator' => "\n- ",
					'after'     => "",
					'echo'      => false,
					'autop'     => false,
				))));
				if($note != '') $note = "\n".$note;
			}

			//Custom note
			if($order_item->get_product() && $order_item->get_product()->get_meta('wc_freeagent_line_item_desc')) {
				$note .= "\n".$order_item->get_product()->get_meta('wc_freeagent_line_item_desc');
			}

			//Add notes
			$line_item['description'] .= $note;

			//Tax
			$line_item['sales_tax_rate'] = $this->get_order_item_tax_label($order, $order_item);

			//Add line item to invoice
			$invoice_items[] = $line_item;

		}

		//Shipping
		foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
			$order_shipping = $shipping_item_obj->get_total();

			if($this->get_option('hide_free_shipping') == 'yes' && $order_shipping == 0) {
				continue;
			}

			$line_item = array(
				'quantity' => 1,
				'description' => esc_html($shipping_item_obj->get_method_title()),
				'sales_tax_rate' => $this->get_order_shipping_tax_label($order, $shipping_item_obj),
				'price' => $order_shipping
			);

			//Add line item to invoice
			$invoice_items[] = $line_item;
		}

		//Fees
		$fees = $order->get_fees();
		if(!empty($fees)) {
			foreach( $fees as $fee ) {
				$line_item = array(
					'quantity' => 1,
					'description' => esc_html($fee->get_name()),
					'sales_tax_rate' => $this->get_order_shipping_tax_label($order, $fee),
					'price' => $fee->get_total()
				);

				//Add line item to invoice
				$invoice_items[] = $line_item;
			}
		}

		//Discount
		if ( $order->get_discount_total() > 0 ) {
			$discout_details = $this->get_coupon_invoice_item_details($order);

			//If coupon is a separate item
			if($this->get_option('separate_coupon') == 'yes') {

				$line_item = array(
					'item_type' => 'Discount',
					'quantity' => 1,
					'description' => $discout_details["title"]."\n".$discout_details["desc"],
					'sales_tax_rate' => round( ($order->get_discount_tax()/$order->get_total_discount()) * 100 ),
					'price' => $order->get_total_discount()*-1
				);

				//Append to xml
				$invoice_items[] = $line_item;

			} else {
				$invoice_comment = $discout_details["desc"];
			}
		}

		return array(
			'items' => $invoice_items,
			'comment' => $invoice_comment
		);
	}

}

//WC Detection
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	function is_woocommerce_active() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins ) ;
	}
}


//WooCommerce inactive notice.
function wc_freeagent_woocommerce_inactive_notice() {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<div id="message" class="error"><p>';
		printf( __( '%1$sIntegration for FreeAgent & WooCommerce is inactive%2$s. %3$sWooCommerce %4$s needs to be installed and activated. %5$sInstall and turn on WooCommerce &raquo;%6$s', 'wc-freeagent' ), '<strong>', '</strong>', '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>', '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">', '</a>' );
		echo '</p></div>';
	}
}

//Initialize
if ( is_woocommerce_active() ) {
	function WC_FreeAgent() {
		return WC_FreeAgent::instance();
	}

	//For backward compatibility
	$GLOBALS['wc_freeagent'] = WC_FreeAgent();
} else {
	add_action( 'admin_notices', 'wc_freeagent_woocommerce_inactive_notice' );
}
