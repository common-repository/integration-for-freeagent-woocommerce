<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_FreeAgent_Auth', false ) ) :
	class WC_FreeAgent_Auth {
		public $sandbox;

		public function __construct() {
			$this->sandbox = false;
			if(WC_FreeAgent()->get_option('sandbox', 'no') == 'yes') {
				$this->sandbox = true;
			}
		}

		public function get_api_url($route) {
			if($this->sandbox) {
				return 'https://api.sandbox.freeagent.com/v2/'.$route;
			} else {
				return 'https://api.freeagent.com/v2/'.$route;
			}
		}

		public function get_auth_url($client_id, $state) {
			return add_query_arg( array(
				'redirect_uri' => urlencode(self::get_redirect_uri()),
				'response_type' => 'code',
				'client_id' => $client_id,
				'state' => $state
			), self::get_api_url('approve_app') );
		}

		//Get redirection url
		public function get_redirect_uri() {
			return admin_url('admin.php?page=wc-settings&tab=integration&section=wc_freeagent&wc_freeagent_auth=1');
		}

		//Setup basic auth header(client_id and secret with base64)
		public function get_basic_auth() {
			return array(
				'Authorization' => 'Basic ' . base64_encode(get_option('_wc_freeagent_client_id').':'.get_option('_wc_freeagent_client_secret'))
			);
		}

		public function get_bearer_auth($with_headers = false) {
			if($with_headers) {
				return array(
					'headers' => array(
						'Authorization' => 'Bearer ' . self::get_access_token()
					)
				);
			} else {
				return array(
					'Authorization' => 'Bearer ' . self::get_access_token()
				);
			}
		}

		//Save the client id, secret and generate a state
		public function start_auth($client_id, $client_secret, $sandbox = false) {

			//Save Client ID and Secret
			update_option('_wc_freeagent_client_id', $client_id);
			update_option('_wc_freeagent_client_secret', $client_secret);

			//Create a state to check
			$state = wp_generate_password( 10, false );
			update_option('_wc_freeagent_oauth_state', $state);

			//If we are in sandbox
			if($sandbox) {
				$this->sandbox = true;
				$settings = get_option( 'woocommerce_wc_freeagent_settings', array() );
				$settings['sandbox'] = 'yes';
				update_option('woocommerce_wc_freeagent_settings', $settings);
			}

			//Return the auth url
			return self::get_auth_url($client_id, $state);

		}

		//Submit the received code in exchange for refresh and access tokens
    public function authenticate($code, $state) {
			$response = array();
			$response['error'] = false;

			//If state not a match
			if($state != get_option('_wc_freeagent_oauth_state')) {
				$response['error'] = true;
				$response['messages'][] = esc_html__('Something went wrong. Please try again.', 'wc-freeagent');
				return $response;
			}

			//Get tokens
			$token_request = wp_remote_post( self::get_api_url('token_endpoint'), array(
				'headers' => self::get_basic_auth(),
				'body' => array( 'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::get_redirect_uri() )
			));

			if(is_wp_error($token_request) || wp_remote_retrieve_response_code($token_request) != 200 ) {
				$response['error'] = true;
				if(is_wp_error($token_request)) {
					$response['messages'][] = $token_request->get_error_message();
				} else {
					$response['messages'][] = esc_html__('Something went wrong. Please try again.', 'wc-freeagent');
				}
			} else {
				$api_response = json_decode( wp_remote_retrieve_body($token_request), true );
				if(isset($api_response['access_token'])) {
					self::save_auth_results($api_response);
					$response['profile'] = self::get_profile_info();
				} else {
					$response['error'] = true;
					$response['messages'][] = esc_html__('Something went wrong. Please try again.', 'wc-freeagent');
				}
			}

      return $response;
    }

		//Get profile info with access token
		public function get_profile_info() {
			$response = wp_remote_get(self::get_api_url('company'), self::get_bearer_auth(true));
			if(is_wp_error($response)) {
				$response = false;
			} else {
				$response = json_decode( wp_remote_retrieve_body($response), true );

				//Save domain so we can create links
				if(isset($response['company'])) {
					update_option('_wc_freeagent_domain', $response['company']['subdomain']);
				}
			}
			return $response;
		}

		//Save tokens and expiration date
		public function save_auth_results($api_response) {
			update_option('_wc_freeagent_access_token', $api_response['access_token']);
			update_option('_wc_freeagent_refresh_token', $api_response['refresh_token']);
			update_option('_wc_freeagent_token_expiration', time()+$api_response['expires_in']);
			delete_option('_wc_freeagent_oauth_state');
		}

		//Update expiration and access token in db
		public function update_access_token($api_response) {
			update_option('_wc_freeagent_access_token', $api_response['access_token']);
			update_option('_wc_freeagent_token_expiration', time()+$api_response['expires_in']);
		}

		//Receive a new access token using a refresh token
		public function renew_token() {
			$refresh_token = get_option('_wc_freeagent_refresh_token');
			$access_token = false;
			$token_request = wp_remote_post( self::get_api_url('token_endpoint'), array(
				'headers' => self::get_basic_auth(),
				'body' => array( 'grant_type' => 'refresh_token', 'refresh_token' => $refresh_token )
			));

			if(!is_wp_error($token_request) && wp_remote_retrieve_response_code($token_request) == 200 ) {
				$api_response = json_decode( wp_remote_retrieve_body($token_request), true );
				if(isset($api_response['access_token'])) {
					self::update_access_token($api_response);
					$access_token = $api_response['access_token'];
				}
			}

			return $access_token;
		}

		//Get access token(renew if expired)
		public function get_access_token() {
			$access_token = get_option('_wc_freeagent_access_token');
			$expiration_date = get_option('_wc_freeagent_token_expiration');

			//If theres no access token
			if(!$access_token) {
				return false;
			}

			//If token expired(5 minutes leeway), refresh it first
			if($expiration_date-300 < time()) {
				$access_token = self::renew_token();
			}

			return $access_token;
		}

		//Simple check if user is authenticated
		public function is_user_authenticated() {
			return (get_option('_wc_freeagent_access_token'));
		}

		//On sign out, delete every option from db
		public function logout() {
			delete_option('_wc_freeagent_access_token');
			delete_option('_wc_freeagent_refresh_token');
			delete_option('_wc_freeagent_oauth_state');
			delete_option('_wc_freeagent_client_id');
			delete_option('_wc_freeagent_client_secret');
		}

		//POST function helper
		public function post($route, $data) {
			$response = array();
			$response['error'] = false;

			$request = wp_remote_post( self::get_api_url($route), array(
				'headers' => array(
					'Authorization' => 'Bearer ' . self::get_access_token(),
					'Content-Type' => 'application/json; charset=utf-8'
				),
				'body' => json_encode($data),
				'method'      => 'POST',
				'data_format' => 'body'
			));

			WC_FreeAgent()->log_debug_messages($request, $route);

			if(is_wp_error($request)) {
				$response['error'] = true;
				$response['error_message'] = $request->get_error_message();
				WC_FreeAgent()->log_error_messages($request, $route);
				return $response;
			} else {
				$api_response = json_decode( wp_remote_retrieve_body($request), true );

				if(isset($api_response['errors'])) {
					$response['error'] = true;
					if(isset($api_response['errors'][0])) {
						$response['error_message'] = ucfirst($api_response['errors'][0]['message']);
					} else {
						$response['error_message'] = ucfirst($api_response['errors']['error']['message']);
					}
					WC_FreeAgent()->log_error_messages($api_response, $route);
					return $response;
				} else {
					$response['body'] = $api_response;
					return $response;
				}
			}
		}

		//PUT function helper
		public function put($route, $data = false) {
			$response = array();
			$response['error'] = false;

			$params = array(
				'headers' => array(
					'Authorization' => 'Bearer ' . self::get_access_token(),
					'Content-Type' => 'application/json',
					'Accept' => 'application/json'
				),
				'method' => 'PUT'
			);

			if($data) {
				$params['body'] = json_encode($data);
			}

			$request = wp_remote_request( self::get_api_url($route), $params);

			WC_FreeAgent()->log_debug_messages($request, $route);

			if(is_wp_error($request)) {
				$response['error'] = true;
				$response['error_message'] = $request->get_error_message();
				WC_FreeAgent()->log_error_messages($request, $route);
				return $response;
			} else {
				$api_response = json_decode( wp_remote_retrieve_body($request), true );

				if(isset($api_response['errors'])) {
					$response['error'] = true;
					if(isset($api_response['errors'][0])) {
						$response['error_message'] = ucfirst($api_response['errors'][0]['message']);
					} else {
						$response['error_message'] = ucfirst($api_response['errors']['error']['message']);
					}
					WC_FreeAgent()->log_error_messages($api_response, $route);
				} else {
					$response['body'] = $api_response;
				}
				return $response;
			}
		}

		public function get($route) {
			$response = array();
			$response['error'] = false;

			$request = wp_remote_get( self::get_api_url($route), array(
				'headers' => array(
					'Authorization' => 'Bearer ' . self::get_access_token(),
					'Content-Type' => 'application/json; charset=utf-8'
				)
			));

			WC_FreeAgent()->log_debug_messages($request, $route);

			if(is_wp_error($request)) {
				$response['error'] = true;
				$response['error_message'] = $request->get_error_message();
				WC_FreeAgent()->log_error_messages($request, $route);
			} else {
				$api_response = json_decode( wp_remote_retrieve_body($request), true );

				if(isset($api_response['errors'])) {
					$response['error'] = true;
					if(isset($api_response['errors'][0])) {
						$response['error_message'] = ucfirst($api_response['errors'][0]['message']);
					} else {
						$response['error_message'] = ucfirst($api_response['errors']['error']['message']);
					}
					WC_FreeAgent()->log_error_messages($api_response, $route);
				} else {
					$response['body'] = $api_response;
				}
			}

			return $response;
		}
  }

endif;
