<?php
/**
 * Provides automatic updates for the plugin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin Data
$api_url     = 'https://wpexplorer-updates.com/api/v1';
$plugin_slug = 'wpex-auto-link-titles';
$license_key = ''; // Free

// Updater class
if ( ! class_exists( 'WPEX_Plugin_Auto_Updates' ) ) {
	class WPEX_Plugin_Auto_Updates {
		private $api_endpoint = null;
		private $plugin_slug  = null;
		private $license_key  = null;
	
		/**
		 * Initializes the auto updates class
		 *
		 * @param $api_url     string  The URL to the updates API
		 * @param $plugin_slug  string  Theme slug
		 * @param $license_key string  License Validation
		 */
		public function __construct( $api_url = '', $plugin_slug, $license_key = ''  ) {

			// Update vars
			$this->api_endpoint = $api_url;
			$this->plugin_slug  = $plugin_slug;
			$this->license_key  = $license_key;

			// Check for updates
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );

			// This is for testing only !!!!
			//set_site_transient( 'update_plugins', null );

		}

		/**
		 * Makes a call to the API
		 *
		 * @param $params array   The parameters for the API call
		 * @return        array   The API response
		 */
		private function call_api( $action, $params ) {

			// Define url
			$url = $this->api_endpoint . '/' . $action;
			
			// Append parameters for GET request
			$url .= '?' . http_build_query( $params );
		 
			// Send the request
			$response = wp_remote_get( $url );

			// Error
			if ( is_wp_error( $response ) ) {
				return false;
			}
			
			// Get response and return response body
			$response_body = wp_remote_retrieve_body( $response );
			return json_decode( $response_body );

		}

		/**
		 * Checks the API response to see if there was an error.
		 *
		 * @param $response The API response to verify
		 * @return bool     True if there was an error. Otherwise false.
		 */
		private function is_api_error( $response ) {
			if ( $response === false ) {
				return true;
			}
			if ( ! is_object( $response ) ) {
				return true;
			}
			if ( isset( $response->error ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Calls the License Manager API to get the license information for the
		 * current product.
		 *
		 * @return object|bool   The product data, or false if API call fails.
		 */
		public function get_license_info() {
			$info = $this->call_api( 'info', array(
				'plugin' => $this->plugin_slug,
				'license' => urlencode( $this->license_key ),
			) );
			return $info;
		}

		/**
		 * Check to see if a license is available
		 *
		 * @return object|bool	If there is an update, returns the license information.
		 *                      Otherwise returns false.
		 */
		public function is_update_available() {
			$license_info = $this->get_license_info();
			if ( $this->is_api_error( $license_info ) ) {
				return false;
			}
			if ( version_compare( $license_info->version, WPEX_PLUGIN_VERSION, '>' ) ) {
				return $license_info;
			}
			return false;
		}

		/**
		 * The filter that checks if there are updates to the plugin
		 *
		 * @param $transient    mixed   The transient used for WordPress updates
		 * @return mixed        The transient with our (possible) additions.
		 */
		public function check_for_update( $transient ) {
			if ( empty( $transient->checked ) ) {
				return $transient;
			}
			$info = $this->is_update_available();
			if ( $info !== false ) {
				$obj = new stdClass();
				$obj->slug = $this->plugin_slug .'.php';
				$obj->new_version = $info->version;
				$obj->url = $info->url;
				$obj->package = $info->package;
				$transient->response[WPEX_PLUGIN_BASENAME] = $obj;
			}
			return $transient;
		}

	}
}
new WPEX_Plugin_Auto_Updates( $api_url, $plugin_slug, $license_key );