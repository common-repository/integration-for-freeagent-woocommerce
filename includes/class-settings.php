<?php

if ( ! class_exists( 'WC_FreeAgent_Settings' ) ) :

class WC_FreeAgent_Settings extends WC_Integration {
	public static $activation_url;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->id                 = 'wc_freeagent';
		$this->method_title       = __( 'FreeAgent', 'wc-freeagent' );
		$this->method_description = __( 'Check out the settings below to connect your WooCommerce store with FreeAgent. Important to note: this plugin and the developer of it is not affiliated with FreeAgent in any way, this is not an official extension.', 'wc-freeagent' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		$this->process_requests();

		// Action to save the fields
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'save_payment_options' ) );

		//Authentication
		add_action( 'wp_ajax_wc_freeagent_authenticate', array( $this, 'start_authenticate' ) );
		add_action( 'wp_ajax_wc_freeagent_logout', array( $this, 'logout' ) );

		//Check and save PRO version
		add_action( 'wp_ajax_wc_freeagent_pro_check', array( $this, 'pro_check' ) );
		add_action( 'wp_ajax_wc_freeagent_pro_deactivate', array( $this, 'pro_deactivate' ) );

		//Define activation url
		self::$activation_url = 'https://freeagent.visztpeter.me/';

	}

	public function process_requests() {
		if(isset($_GET['wc_freeagent_auth']) && isset($_GET['code']) && !empty($_GET['code']) ) {

			//Get the tokens
			$response = WC_FreeAgent()->auth->authenticate(sanitize_text_field($_GET['code']), sanitize_text_field($_GET['state']));

			if($response['error']) {
				header( "Location: " . admin_url('admin.php?page=wc-settings&tab=integration&section=wc_freeagent&auth_error=1') );
			} else {
				header( "Location: " . admin_url('admin.php?page=wc-settings&tab=integration&section=wc_freeagent') );
			}
		}
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$pro_required = false;
		$pro_icon = false;
		if(!get_option('_wc_freeagent_pro_enabled')) {
			$pro_required = true;
			$pro_icon = '<i class="wc_freeagent_pro_label">PRO</i>';
		}

		//Authentication settings
		$settings_top = array(
			'pro_key' => array(
				'title'    => __( 'PRO version', 'wc-freeagent' ),
				'type'     => 'pro'
			),
			'auth' => array(
				'title' => __( 'Authentication', 'wc-freeagent' ),
				'type'  => 'auth'
			)
		);

		//Every other settings
		$settings_rest = array(

			//General settings
			'section_invoice' => array(
				'title' => __( 'Invoice settings', 'wc-freeagent' ),
				'type'  => 'title',
				'description'  => __( 'General settings related to creating invoices', 'wc-freeagent' ),
			),
			'company_currency' => array(
        'title'    => __( 'Company currency', 'wc-freeagent' ),
				'type'		 => 'select',
				'class'    => 'chosen_select',
				'css'      => 'min-width:300px;',
				'options' => $this->get_currencies(),
				'default' => get_woocommerce_currency(),
				'desc_tip' => __( "Select your company's native currency set in FreeAgent.", 'wc-freeagent' )
			),
			'payment_deadline' => array(
        'title'    => __( 'Payment deadline(days)', 'wc-freeagent' ),
  			'type'     => 'number'
			),
			'note' => array(
        'title'    => __( 'Invoice note', 'wc-freeagent' ),
  			'type'     => 'textarea',
				'desc_tip' => __( "Use the {customer_email} or {customer_phone} shortcodes to show the customer's email address and phone number on the invoice.", 'wc-freeagent')
			),
			'invoice_number' => array(
        'title'    => __( 'Invoice Reference', 'wc-freeagent' ),
  			'type'     => 'text',
				'default'	 => '',
				'desc_tip' => __( 'You can use the following shortcodes: {order_number}, {year}, {month}, {day}. For example: WOO/{year}/{month}/{day}/{order_number}'),
				'description' => __( 'Leave this field empty if you want FreeAgent to create a sequencing invoice number automatically')
			),
			'estimate_number' => array(
        'title'    => __( 'Estimate Reference', 'wc-freeagent' ),
  			'type'     => 'text',
				'default'	 => 'ESTIMATE/{order_number}',
				'desc_tip' => __( 'You can use the following shortcodes: {order_number}, {year}, {month}, {day}. For example: WOO/{year}/{month}/{day}/{order_number}'),
			),
			'language' => array(
				'type'		 => 'select',
				'class'    => 'chosen_select',
				'css'      => 'min-width:300px;',
				'title'    => __( 'Invoice language', 'wc-freeagent' ),
				'options'  => array(
					'bg' => __('Bulgarian', 'wc-freeagent'),
					'ca' => __('Catalan', 'wc-freeagent'),
					'cy' => __('Welsh', 'wc-freeagent'),
					'cz' => __('Czech', 'wc-freeagent'),
					'de' => __('German', 'wc-freeagent'),
					'dk' => __('Danish', 'wc-freeagent'),
					'en' => __('English', 'wc-freeagent'),
					'en-US' => __('English (United States)', 'wc-freeagent'),
					'es' => __('Spanish', 'wc-freeagent'),
					'et' => __('Estonian', 'wc-freeagent'),
					'fi' => __('Finnish', 'wc-freeagent'),
					'fr' => __('French', 'wc-freeagent'),
					'fr-BE' => __('French (Belgium)', 'wc-freeagent'),
					'fr-CA' => __('French (Canada)', 'wc-freeagent'),
					'is' => __('Icelandic', 'wc-freeagent'),
					'it' => __('Italian', 'wc-freeagent'),
					'lv-LV' => __('Latvian', 'wc-freeagent'),
					'nl' => __('Dutch', 'wc-freeagent'),
					'nl-BE' => __('Dutch (Belgium)', 'wc-freeagent'),
					'nk' => __('Norwegian', 'wc-freeagent'),
					'pl-PL' => __('Polish', 'wc-freeagent'),
					'pt-BR' => __('Brazilian Portuguese', 'wc-freeagent'),
					'ro' => __('Romanian', 'wc-freeagent'),
					'rs' => __('Serbian', 'wc-freeagent'),
					'ru' => __('Russian', 'wc-freeagent'),
					'se' => __('Swedish', 'wc-freeagent'),
					'sk' => __('Slovak', 'wc-freeagent'),
					'tr' => __('Turkish', 'wc-freeagent')
				),
				'default'  => 'en'
			),
			'language_wpml' => array(
				'title'    => __( 'WPML and Polylang compatibility', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'desc_tip' => __('If turned on, the language will be changed automatically based on the value stored by WPML or Polylang.', 'wc-freeagent')
			),
			'unit_type' => array(
				'title'    => __( 'Product Unit', 'wc-freeagent' ),
				'type'		 => 'select',
				'class'    => 'chosen_select',
				'css'      => 'min-width:300px;',
				'options'  => array(
					'' => __('-no unit-', 'wc-freeagent'),
					'Hours' => __('Hours', 'wc-freeagent'),
					'Days' => __('Days', 'wc-freeagent'),
					'Weeks' => __('Weeks', 'wc-freeagent'),
					'Months' => __('Months', 'wc-freeagent'),
					'Years' => __('Years', 'wc-freeagent'),
					'Products' => __('Products', 'wc-freeagent'),
					'Services' => __('Services', 'wc-freeagent'),
					'Training' => __('Training', 'wc-freeagent'),
					'Expenses' => __('Expenses', 'wc-freeagent'),
					'Comment' => __('Comment', 'wc-freeagent'),
					'Bills' => __('Bills', 'wc-freeagent'),
					'Discount' => __('Discount', 'wc-freeagent'),
					'Credit' => __('Credit', 'wc-freeagent'),
				),
				'desc_tip' => __('This is the default unit for the invoice items. You can select a custom unit when you edit a product under the Advanced tab.', 'wc-freeagent')
			),
			'estimate_type' => array(
				'title'    => __( 'Estimate type', 'wc-freeagent' ),
				'type'		 => 'select',
				'class'    => 'chosen_select',
				'css'      => 'min-width:300px;',
				'options'  => array(
					'Estimate' => __('Estimate', 'wc-freeagent'),
					'Quote' => __('Quote', 'wc-freeagent'),
					'Proposal' => __('Proposal', 'wc-freeagent'),
				),
				'desc_tip' => __('This will be the type of an Estimate you create either automatically or manually', 'wc-freeagent')
			),

			//Automatic settings
			'section_automatic' => array(
				'title' => __( 'Automation', 'wc-freeagent' ).$pro_icon,
				'type'  => 'title',
				'description'  => __( 'Settings related to automatic invoicing. You can automatically create an invoice when the order status changes and mark them completed based on the available payment methods. The payment deadline can be set for each payment method(default value is set at the top).', 'wc-freeagent' ),
			),
			'auto_contact' => array(
				'disabled' => $pro_required,
				'title'    => __( 'Generate contacts automatically', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'desc_tip'     => __( 'When a new order is created, the customer is automatically saved in FreeAgent(otherwise it will be saved when you create an invoice)', 'wc-freeagent' ),
			),
			'auto_generate' => array(
				'disabled' => $pro_required,
				'title'    => __( 'Generate invoices automatically', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'desc_tip'     => __( 'If turned on, an invoice will be generated automatically once an order has been created.', 'wc-freeagent' ),
			),
			'auto_invoice_status' => array(
				'type' => 'select',
				'title' => __( 'Invoice based on order status', 'wc-freeagent' ),
				'class' => 'wc-enhanced-select',
				'default' => 'wc-completed',
				'options'  => $this->get_order_statuses(),
				'desc_tip' => __( 'If the order is in this state, the invoice will be created automatically.', 'wc-freeagent' ),
			),
			/*
			'auto_void_status' => array(
				'type' => 'select',
				'title' => __( 'Void invoices automatically', 'wc-freeagent' ),
				'class' => 'wc-enhanced-select',
				'default' => 'no',
				'options'  => $this->get_order_statuses_for_void(),
				'desc_tip' => __( 'If the order is in this status, the invoice will be marked as cancelled automatically.', 'wc-freeagent' ),
			),
			*/
			'ipn_close' => array(
				'disabled' => $pro_required,
				'title'    => __( 'Close orders with IPN', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'description' => __( 'Every half an hour WooCommerce will check if an invoice has been marked as paid in FreeAgent. If it was paid, the order will be marked as complated.', 'wc-freeagent' ),
			),
			'payment_methods' => array(
				'title'    => __( 'Payment methods', 'wc-freeagent' ),
				'type'     => 'payment_methods',
				'disabled' => $pro_required,
			),

			//Coupons
			'section_coupons' => array(
				'title' => __( 'Discounts', 'wc-freeagent' ),
				'type'  => 'title',
				'description'  => __( 'Settings related to coupons and order discounts', 'wc-freeagent' ),
			),
			'separate_coupon' => array(
				'title'    => __( 'Separate coupon line item', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'class'		 => 'wc-freeagent-toggle-group-coupon',
				'desc_tip' => __('If turned on, the coupon used for the order will be a separate line item and the order items will display the pre-discount prices on the invoice, not the discounted prices.', 'wc-freeagent')
			),
			'separate_coupon_name' => array(
				'title'    => __( 'Coupon line item name', 'wc-freeagent' ),
				'type'     => 'text',
				'placeholder' => 'Discount',
				'class'		 => 'wc-freeagent-toggle-group-coupon-item',
				'desc_tip' => __('The name of the discount invoice line item. Default value: Discount', 'wc-freeagent')
			),
			'separate_coupon_desc' => array(
				'title'    => __( 'Coupon line item description', 'wc-freeagent' ),
				'type'     => 'textarea',
				'placeholder' => '{discount_value} discount with the following coupon code: {coupon_code}',
				'class'		 => 'wc-freeagent-toggle-group-coupon-item',
				'desc_tip' => __('The description of the invoice line item. Default value: {discount_value} discount with the following coupon code: {coupon_code}', 'wc-freeagent')
			),
			'hide_free_shipping' => array(
				'title'    => __( 'Hide free shipping', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'desc_tip' => __('If turned on, the free shipping line item will be hidden on the invoice.', 'wc-freeagent')
			),
			'disable_free_order' => array(
				'title'    => __( 'Disable invoices for free orders', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'desc_tip' => __('If turned on, the automatic invoice generation will be turned off, if the order total is 0.', 'wc-freeagent'),
				'default'  => 'yes'
			),

			//Settings related to invoice notices
			'section_emails' => array(
				'title' => __( 'Invoice sharing', 'wc-freeagent' ),
				'type'  => 'title',
				'description'  => __( 'Settings related to sharing the invoices with your customers.', 'wc-freeagent' ),
			),
			'auto_email' => array(
				'title'    => __( 'FreeAgent email notifications', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'class'		 => 'wc-freeagent-toggle-group-email-notify',
				'desc_tip' => __( 'If turned on, FreeAgent will email this invoice automatically using your default template to your customer.', 'wc-freeagent' ),
				'default'  => 'yes',
				'description' => __('Make sure you have a the New Invoice and New Estimate template saved in FreeAgent / Settings / Email Templates!')
			),
			'email_attachment_file' => array(
				'title'    => __( 'Attach invoices to WC emails', 'wc-freeagent' ).$pro_icon,
				'type'     => 'checkbox',
				'disabled' => $pro_required,
				'class'		 => 'wc-freeagent-toggle-group-emails',
				'desc_tip' => __( 'You can attach the invoices to the emails sent by WooCommerce. You should turn off the FreeAgent email notifications in this case. You can select multiple email types.', 'wc-freeagent' ),
			),
			'email_attachment_invoice' => array(
				'type' => 'multiselect',
				'title' => __( 'Attach invoice to', 'wc-freeagent' ),
				'class' => 'wc-enhanced-select wc-freeagent-toggle-group-emails-item',
				'default' => array('customer_completed_order', 'customer_invoice'),
				'options'  => $this->get_email_types(),
			),
			'email_attachment_estimate' => array(
				'type' => 'multiselect',
				'title' => __( 'Attached estimate to', 'wc-freeagent' ),
				'class' => 'wc-enhanced-select wc-freeagent-toggle-group-emails-item',
				'default' => array('customer_processing_order', 'customer_on_hold_order'),
				'options'  => $this->get_email_types(),
			),
			/*
			'email_attachment_void' => array(
				'type' => 'multiselect',
				'title' => __( 'Attached cancelled invoice to', 'wc-freeagent' ),
				'class' => 'wc-enhanced-select wc-freeagent-toggle-group-emails-item',
				'default' => array('customer_refunded_order', 'cancelled_order'),
				'options'  => $this->get_email_types(),
			),
			*/
			'customer_download' => array(
				'title'    => __( 'Invoices in My Account', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'desc_tip'     => __( 'If turned on, the invoices can be downloaded by the customer from the My Account / My Orders page. x', 'wc-freeagent' ),
				'default'  => 'no'
			),

			//Other settings
			'section_other' => array(
				'title' => __( 'Other settings', 'wc-freeagent' ),
				'type'  => 'title'
			),
			'debug' => array(
        'title'    => __( 'Developer mode', 'wc-freeagent' ),
  			'type'     => 'checkbox',
				'desc_tip' => __( 'If turned on, the requests sent to FreeAgent will be logged on WooCommerce / Status / Logs.', 'wc-freeagent' ),
			),
			'sandbox' => array(
        'title'    => __( 'Sandbox mode', 'wc-freeagent' ),
  			'type'     => 'checkbox',
				'desc_tip' => __( 'If turned on, it will use the Sandbox environment of FreeAgent.', 'wc-freeagent' ),
			),
			'defer' => array(
        'title'    => __( 'Deferred invoice generation', 'wc-freeagent' ),
  			'type'     => 'checkbox',
  			'desc_tip' => __( 'If turned on, invoices will be created in the background after an order was created, so the customer can reach the Thank You screen faster. In this case, the order emails are sent before the invoice is generated, so in this case use the FreeAgent email notifications instead of attaching the invoice to the WC emails.', 'wc-freeagent' ),
			),
			'uninstall' => array(
				'title'    => __( 'Uninstall settings', 'wc-freeagent' ),
				'type'     => 'checkbox',
				'desc_tip' => __( 'If turned on, all settings and data will be deleted from the database when you uninstall this extension.', 'wc-freeagent' ),
			),
		);

		$load_settings = false;
		if(WC_FreeAgent()->auth->is_user_authenticated()) {
			$load_settings = true;
		}

		if($load_settings) {
			$this->form_fields = array_merge($settings_top, $settings_rest);
		} else {
			$this->form_fields = array_merge($settings_top);
		}
	}

	//Authenticate
	public function start_authenticate() {
		check_ajax_referer( 'wc_freeagent_auth', 'nonce' );

		//Setup response
		$response = array();
		$response['error'] = false;

		if(!isset($_POST['client_id']) || !isset($_POST['client_secret'])) {
			$response['error'] = true;
			$response['messages'][] = esc_html__('Please enter your Client ID and Secret', 'wc-freeagent');
			$response;
		} else {
			$client_id = sanitize_text_field($_POST['client_id']);
			$client_secret = sanitize_text_field($_POST['client_secret']);
			$sandbox = rest_sanitize_boolean($_POST['sandbox']);
			$response['auth_url'] = WC_FreeAgent()->auth->start_auth($client_id, $client_secret, $sandbox);
		}

		wp_send_json_success($response);
	}

	//Sign out
	public function logout() {
		check_ajax_referer( 'wc_freeagent_auth', 'nonce' );

		//Setup response
		$response = array();
		$response['error'] = false;

		WC_FreeAgent()->auth->logout();

		wp_send_json_success($response);
	}

	//Get order statues
	public function get_order_statuses() {
		if(function_exists('wc_order_status_manager_get_order_status_posts')) {
			$filtered_statuses = array();
			$custom_statuses = wc_order_status_manager_get_order_status_posts();
			foreach ($custom_statuses as $status ) {
				$filtered_statuses[ 'wc-' . $status->post_name ] = $status->post_title;
			}
			return $filtered_statuses;
		} else {
			return wc_get_order_statuses();
		}
	}

	//Order statuses
	public function get_order_statuses_for_void() {
		$built_in_statuses = array("no"=>__("Turned off")) + $this->get_order_statuses();
		return $built_in_statuses;
	}

	//Get WooCommerce currencies
	public function get_currencies() {
		$currency_code_options = get_woocommerce_currencies();
		foreach ( $currency_code_options as $code => $name ) {
			$currency_code_options[ $code ] = $name . ' (' . get_woocommerce_currency_symbol( $code ) . ')';
		}
		return $currency_code_options;
	}

  //Get payment methods
  public static function get_payment_methods() {
    $available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_methods = array();
		foreach ($available_gateways as $available_gateway) {
			if($available_gateway->enabled == 'yes') {
				$payment_methods[$available_gateway->id] = $available_gateway->title;
			}
		}
    return $payment_methods;
  }

	//Get email ids
	public static function get_email_types() {
		$mailer = WC()->mailer();
		$email_templates = $mailer->get_emails();
		$emails = array();
		$disabled = ['failed_order', 'customer_note', 'customer_reset_password', 'customer_new_account'];
		foreach ( $email_templates as $email ) {
			if(!in_array($email->id,$disabled)) {
				$emails[$email->id] = $email->get_title();
			}
		}

		return $emails;
	}

	public function generate_auth_html( $key, $data ) {
		$field    = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class'             => 'button-secondary',
			'css'               => '',
			'custom_attributes' => array(),
			'desc_tip'          => false,
			'description'       => '',
			'title'             => '',
		);
		$data = wp_parse_args( $data, $defaults );
		ob_start();
		?>

		<?php if(WC_FreeAgent()->auth->is_user_authenticated()): ?>
			<?php $freeagent_profile = WC_FreeAgent()->auth->get_profile_info(); ?>
			<tr valign="top" class="wc-freeagent-section-authenticated <?php if(isset($freeagent_profile['errors'])): ?>issues<?php endif; ?>">
				<td>
					<div class="wc-freeagent-section-authenticated-flex">
						<?php if(isset($freeagent_profile['errors'])): ?>
							<div class="wc-freeagent-section-authenticated-errors">
								<p>The following errors occured:</p>
								<ul>
									<?php foreach ($freeagent_profile['errors'] as $error): ?>
										<li><?php echo esc_html($error['message']); ?></li>
									<?php endforeach; ?>
								</ul>
								<p>Try to refresh the page. If the issue persists, sign out and try authenticate your account again</p>
							</div>
						<?php else: ?>
							<div class="wc-freeagent-section-authenticated-name">
								<strong><?php echo esc_html($freeagent_profile['company']['name']); ?></strong>
								<small><?php echo esc_html($freeagent_profile['company']['subdomain']); ?>.freeagent.com</small>
							</div>
						<?php endif; ?>
						<button class="button-secondary" type="button" id="wc_freeagent_auth_logout" data-nonce="<?php echo wp_create_nonce( "wc_freeagent_auth" ); ?>"><?php _e('Sign out', 'wc-freeagent'); ?></button>
					</div>
				</td>
			</tr>
		<?php else: ?>
			<tr valign="top" class="wc-freeagent-section-auth">
				<td>
					<div class="wc-freeagent-section-auth-step">
						<span class="wc-freeagent-section-auth-step-number">1</span>
						<h3 class="wc-freeagent-section-auth-step-title">Create a developer account on FreeAgent</h3>
						<p>Go to the FreeAgent developer dashboard and create an account</p>
						<p><a href="https://dev.freeagent.com/signup" target="_blank">Go to Developer Dashboard</a></p>
					</div>
					<div class="wc-freeagent-section-auth-step">
						<span class="wc-freeagent-section-auth-step-number">2</span>
						<h3 class="wc-freeagent-section-auth-step-title">Create a new application</h3>
						<p>On the Developer Dashboard, click <a href="https://dev.freeagent.com/apps/new" target="_blank">Create a new app</a>. Enter a name(for example your store's name) and the following OAuth Redirect URIs:</p>
						<p><input id="wc_freeagent_auth_code_field" readonly type="text" placeholder="Authorization Code" value="<?php echo esc_attr(WC_FreeAgent()->auth->get_redirect_uri()); ?>"></p>
						<p><small>You can leave the rest of the fields empty.</small></p>
					</div>
					<div class="wc-freeagent-section-auth-step wc-freeagent-section-auth-step-3">
						<span class="wc-freeagent-section-auth-step-number">3</span>
						<h3 class="wc-freeagent-section-auth-step-title">Connect your FreeAgent account</h3>
						<p>Once the app was created, enter your OAuth identifier and OAuth secret in the fields below and click on Connect.</p>
						<div class="wc-freeagent-section-auth-client-fields">
							<p><input id="wc_freeagent_auth_client_id_field" type="text" placeholder="OAuth identifier" value="<?php echo esc_attr(get_option('_wc_freeagent_client_id')); ?>"></p>
							<p><input id="wc_freeagent_auth_client_secret_field" type="text" placeholder="OAuth secret" value="<?php echo esc_attr(get_option('_wc_freeagent_client_secret')); ?>"></p>
							<p>
								<label for="wc_freeagent_auth_sandbox">
									<input type="checkbox" id="wc_freeagent_auth_sandbox" value="1" <?php checked( WC_FreeAgent()->auth->sandbox ); ?>>
									Use in Sandbox mode
								</label>
							</p>
						</div>
						<p><button data-nonce="<?php echo wp_create_nonce( "wc_freeagent_auth" ); ?>" id="wc_freeagent_authenticate" class="button-primary" type="button"><?php _e('Connect', 'wc-freeagent'); ?></button></p>
					</div>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
<table class="form-table">
	<tbody>
		<?php
		return ob_get_clean();
	}

	public function generate_pro_html( $key, $data ) {
		$field    = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class'             => 'button-secondary',
			'css'               => '',
			'custom_attributes' => array(),
			'desc_tip'          => false,
			'description'       => '',
			'title'             => '',
		);
		$data = wp_parse_args( $data, $defaults );
		ob_start();
		?>

		<tr valign="top" class="wc-freeagent-section-pro <?php if(get_option('_wc_freeagent_pro_enabled')): ?>wc-freeagent-section-pro-active<?php endif; ?>">
			<td>
				<div class="notice notice-error inline" style="display:none;"><p></p></div>
				<div class="wc-freeagent-section-pro-flex">
					<?php if(get_option('_wc_freeagent_pro_enabled')): ?>
						<div class="wc-freeagent-section-pro-activated">
							<strong>Pro version is active</strong>
							<small><?php echo esc_html(get_option('_wc_freeagent_pro_key')); ?> / <?php echo esc_html(get_option('_wc_freeagent_pro_email')); ?></small>
						</div>
						<div class="wc-freeagent-section-pro-activated-buttons">
							<a href="https://freeagent.visztpeter.me/documentation" target="_blank" class="button-primary"><?php _e('Support', 'wc-freeagent'); ?></a>
							<button class="button-secondary" type="button" name="<?php echo esc_attr( $field ); ?>-deactivate" id="<?php echo esc_attr( $field ); ?>-deactivate"><?php _e('Deactivate', 'wc-freeagent'); ?></button>
						</div>
					<?php else: ?>
						<div class="wc-freeagent-section-pro-form">
							<h3>WooCommerce + FreeAgent PRO version</h3>
							<p>If already purchased, simply enter your license key and email address:</p>
							<?php echo $this->get_tooltip_html( $data ); ?>
							<fieldset>
								<input class="input-text regular-input" type="text" name="woocommerce_wc_freeagent_pro_key" id="woocommerce_wc_freeagent_pro_key" value="" placeholder="License key"><br>
								<input class="input-text regular-input" type="text" name="woocommerce_wc_freeagent_pro_email" id="woocommerce_wc_freeagent_pro_email" value="" placeholder="E-mail address of your purchase">
								<p><button class="button-primary" type="button" name="<?php echo esc_attr( $field ); ?>-submit" id="<?php echo esc_attr( $field ); ?>-submit"><?php _e('Aktiválás', 'wc-freeagent'); ?></button></p>
							</fieldset>
						</div>
						<div class="wc-freeagent-section-pro-cta">
							<h4>Why should i buy it?</h4>
							<ul>
								<li>Automatic invoice generation</li>
								<li>Automatic estimate generation</li>
								<li>Sync contacts automatically</li>
								<li>Mark invoices as paid</li>
								<li>Premium support and a lot more</li>
							</ul>
							<div class="wc-freeagent-section-pro-cta-button">
								<a href="https://freeagent.visztpeter.me">Buy the PRO version</a>
								<span>
									<strong>$59</strong>
								</span>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</td>
		</tr>
	</tbody>
</table>
<table class="form-table">
	<tbody>
		<?php
		return ob_get_clean();
	}

	public function pro_check() {

		if ( !current_user_can( 'edit_shop_orders' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		$pro_key = sanitize_text_field($_POST['key']);
		$pro_email = sanitize_email($_POST['email']);

		$args = array(
			'request' => 'activation',
			'email' => $pro_email,
			'licence_key' => $pro_key,
			'product_id' => 'WC_FREEAGENT'
		);

		//Execute request (function below)
		$base_url = add_query_arg('wc-api', 'software-api', WC_FreeAgent_Settings::$activation_url);
		$target_url = $base_url . '&' . http_build_query( $args );
		$data = wp_remote_get( $target_url );
		$result = json_decode($data['body']);

		if(isset($result->activated) && $result->activated) {

			//Store the key and email
			update_option('_wc_freeagent_pro_key', $pro_key);
			update_option('_wc_freeagent_pro_email', $pro_email);
			update_option('_wc_freeagent_pro_enabled', true);

			wp_send_json_success();

		} else {

			wp_send_json_error(array(
				'message' => __('Nem sikerült az aktiválás, kérlek ellenőrizd az adatok helyességét.', 'wc-freeagent')
			));

		}

	}

	public function pro_deactivate() {
		if ( !current_user_can( 'edit_shop_orders' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		$pro_key = get_option('_wc_freeagent_pro_key');
		$pro_email = get_option('_wc_freeagent_pro_email');

		$args = array(
			'request' => 'activation_reset',
			'email' => $pro_email,
			'licence_key' => $pro_key,
			'product_id' => 'WC_FREEAGENT'
		);

		//Execute request (function below)
		$base_url = add_query_arg('wc-api', 'software-api', WC_FreeAgent_Settings::$activation_url);
		$target_url = $base_url . '&' . http_build_query( $args );
		$data = wp_remote_get( $target_url );
		$result = json_decode($data['body']);

		if(isset($result->reset) && $result->reset) {

			//Store the key and email
			delete_option('_wc_freeagent_pro_key');
			delete_option('_wc_freeagent_pro_email');
			delete_option('_wc_freeagent_pro_enabled');

			wp_send_json_success();

		} else {

			wp_send_json_error(array(
				'message' => __('Nem sikerült a deaktiválás, kérlek ellenőrizd az adatok helyességét.', 'wc-freeagent')
			));

		}

	}

	public function generate_payment_methods_html($key, $data) {
		ob_start();

		$payment_methods = $this->get_payment_methods();
		$saved_values = get_option('wc_freeagent_payment_method_options');
		$bank_accounts = WC_FreeAgent()->get_bank_accounts();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Fizetési módok:', 'woocommerce' ); ?></th>
			<td class="forminp">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table wc-freeagent-payment-table" cellspacing="0">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Paymenth method', 'wc-freeagent' ); ?></th>
								<th><?php esc_html_e( 'Payment deadline(days)', 'wc-freeagent' ); ?></th>
								<th><?php esc_html_e( 'Mark as paid', 'wc-freeagent' ); ?> <?php if($data['disabled']): ?><i class="wc_freeagent_pro_label">PRO</i><?php endif; ?></th>
								<th><?php esc_html_e( 'Create estimate', 'wc-freeagent' ); ?> <?php if($data['disabled']): ?><i class="wc_freeagent_pro_label">PRO</i><?php endif; ?></th>
								<th><?php esc_html_e( 'Bank Account', 'wc-freeagent' ); ?> <?php if($data['disabled']): ?><i class="wc_freeagent_pro_label">PRO</i><?php endif; ?></th>
							</tr>
						</thead>
						<tbody class="wc-freeagent-settings-inner-table">
							<?php foreach ( $payment_methods as $payment_method_id => $payment_method ): ?>
								<?php
								if($saved_values && isset($saved_values[esc_attr( $payment_method_id )])) {
									$value_deadline = esc_attr( $saved_values[esc_attr( $payment_method_id )]['deadline']);
									$value_complete = $saved_values[esc_attr( $payment_method_id )]['complete'];
									$value_estimate = $saved_values[esc_attr( $payment_method_id )]['estimate'];
									$value_bank_account = $saved_values[esc_attr( $payment_method_id )]['bank_account'];
								} else {
									$value_deadline = '';
									$value_complete = false;
									$value_estimatee = false;
									$value_bank_account = '';
								}
								?>
								<tr>
									<td class="wc-freeagent-settings-inner-table-title"><strong><?php echo $payment_method; ?></strong></td>
									<td class="wc-freeagent-settings-inner-table-field"><input type="number" name="wc_freeagent_payment_options[<?php echo esc_attr( $payment_method_id ); ?>][deadline]" value="<?php echo $value_deadline; ?>" /></td>
									<td class="wc-freeagent-settings-inner-table-cb"><input <?php disabled( $data['disabled'] ); ?> type="checkbox" name="wc_freeagent_payment_options[<?php echo esc_attr( $payment_method_id ); ?>][complete]" value="1" <?php checked( $value_complete ); ?> /></td>
									<td class="wc-freeagent-settings-inner-table-cb"><input <?php disabled( $data['disabled'] ); ?> type="checkbox" name="wc_freeagent_payment_options[<?php echo esc_attr( $payment_method_id ); ?>][estimate]" value="1" <?php checked( $value_estimate ); ?> /></td>
									<td class="wc-freeagent-settings-inner-table-select">
										<?php
										woocommerce_form_field( 'wc_freeagent_payment_options['.esc_attr($payment_method_id).'][bank_account]', array(
											'type'        => 'select',
											'options'     => $bank_accounts,
										), $value_bank_account );
										?>
									</td>
								</tr>
							<?php endforeach ?>
						</tbody>
					</table>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();

	}

	public function save_payment_options() {

		//Just in case, remove migrating message of already migrated
		if(get_option('_wc_freeagent_migrated') && get_option('_wc_freeagent_migrating')) {
			update_option('_wc_freeagent_migrating', false);
		}

		//Save payment options
		$accounts = array();
		if ( isset( $_POST['wc_freeagent_payment_options'] ) ) {
			foreach ($_POST['wc_freeagent_payment_options'] as $payment_method_id => $payment_method) {
				$deadline = wc_clean($payment_method['deadline']);
				$complete = isset($payment_method['complete']) ? true : false;
				$estimate = isset($payment_method['estimate']) ? true : false;

				if(isset($payment_method['bank_account'])) {
					$bank_account = wc_clean($payment_method['bank_account']);
				} else {
					$bank_account = 0;
				}

				$accounts[$payment_method_id] = array(
					'deadline' => $deadline,
					'complete' => $complete,
					'estimate' => $estimate,
					'bank_account' => $bank_account
				);
			}
		}
		update_option( 'wc_freeagent_payment_method_options', $accounts );

		//Delete cookies
		delete_option('_wc_freeagent_cookie_name');
	}

}

endif;
