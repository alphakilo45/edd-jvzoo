<?php
/**
 * License handler for Caffeine Press Media
 *
 * This class should simplify the process of adding license information
 * to new EDD extensions.
 *
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'CPM_License' ) ) :

	/**
	 * CPM_License Class
	 */
	class CPM_License {
		private $file;
		private $license;
		private $item_name;
		private $item_id;
		private $item_shortname;
		private $version;
		private $author;
		private $api_url = 'https://caffeinepressmedia.com/edd-sl-api/';

		/**
		 * Class constructor
		 *
		 * @param string  $_file
		 * @param string  $_item
		 * @param string  $_version
		 * @param string  $_author
		 * @param string  $_optname
		 * @param string  $_api_url
		 */
		function __construct( $_file, $_item, $_version, $_author, $_optname = null, $_api_url = null ) {

			$this->file           = $_file;

			if( is_numeric( $_item ) ) {
				$this->item_id    = absint( $_item );
			} else {
				$this->item_name  = $_item;
			}

			$this->item_shortname = 'edd_' . preg_replace( '/[^a-zA-Z0-9_\s]/', '', str_replace( ' ', '_', strtolower( $this->item_name ) ) );
			$this->version        = $_version;
			$this->license        = trim( edd_get_option( $this->item_shortname . '_license_key', '' ) );
			$this->author         = $_author;
			$this->api_url        = is_null( $_api_url ) ? $this->api_url : $_api_url;

			/**
			 * Allows for backwards compatibility with old license options,
			 * i.e. if the plugins had license key fields previously, the license
			 * handler will automatically pick these up and use those in lieu of the
			 * user having to reactive their license.
			 */
			if ( ! empty( $_optname ) ) {
				$opt = edd_get_option( $_optname, false );

				if( isset( $opt ) && empty( $this->license ) ) {
					$this->license = trim( $opt );
				}
			}

			// Setup hooks
			$this->includes();
			$this->hooks();

		}

		/**
		 * Include the updater class
		 *
		 * @access  private
		 * @return  void
		 */
		private function includes() {
			if ( ! class_exists( 'CPM_Plugin_Updater' ) )  {
				require_once 'class-cpm-plugin-updater.php';
			}
		}

		/**
		 * Setup hooks
		 *
		 * @access  private
		 * @return  void
		 */
		private function hooks() {

			// Register settings
			add_filter( 'edd_settings_licenses', array( $this, 'settings' ), 1 );

			// Activate license key on settings save
			add_action( 'admin_init', array( $this, 'activate_license' ) );

			// Deactivate license key
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );

			// Check that license is valid once per week
			add_action( 'edd_weekly_scheduled_events', array( $this, 'weekly_license_check' ) );

			// For testing license notices, uncomment this line to force checks on every page load
			//add_action( 'admin_init', array( $this, 'weekly_license_check' ) );

			// Updater
			add_action( 'admin_init', array( $this, 'auto_updater' ), 0 );

			// Display notices to admins
			add_action( 'admin_notices', array( $this, 'notices' ) );

			add_action( 'in_plugin_update_message-' . plugin_basename( $this->file ), array( $this, 'plugin_row_license_missing' ), 10, 2 );

			add_action('edd_' . $this->item_shortname . '_license_key', array($this, 'licenseKeyCallback'));
		}

		/**
		 * Auto updater
		 *
		 * @access  private
		 * @return  void
		 */
		public function auto_updater() {

			$args = array(
				'version'   => $this->version,
				'license'   => $this->license,
				'author'    => $this->author
			);

			if( ! empty( $this->item_id ) ) {
				$args['item_id']   = $this->item_id;
			} else {
				$args['item_name'] = $this->item_name;
			}

			// Setup the updater
			new CPM_Plugin_Updater(
				$this->api_url,
				$this->file,
				$args
			);
		}


		/**
		 * Add license field to settings
		 *
		 * @access  public
		 * @param array   $settings
		 * @return  array
		 */
		public function settings( $settings ) {
			$edd_license_settings = array(
				array(
					'id'      => $this->item_shortname . '_license_key',
					'name'    => sprintf( __( '%1$s License Key', 'caffeine-press-media' ), $this->item_name ),
					'desc'    => '',
					'type'    => 'hook',
					'options' => array( 'is_valid_license_option' => $this->item_shortname . '_license_active' ),
					'size'    => 'regular'
				)
			);

			return array_merge( $settings, $edd_license_settings );
		}


		function licenseKeyCallback( $args ) {
			global $edd_options;

			$messages = array();
			$license  = get_option( $args['options']['is_valid_license_option'] );

			if ( isset( $edd_options[ $args['id'] ] ) ) {
				$value = $edd_options[ $args['id'] ];
			} else {
				$value = isset( $args['std'] ) ? $args['std'] : '';
			}

			if( ! empty( $license ) && is_object( $license ) ) {

				// activate_license 'invalid' on anything other than valid, so if there was an error capture it
				if ( false === $license->success ) {

					switch( $license->error ) {

						case 'expired' :

							$class = 'error';
							$messages[] = sprintf(
								__( 'Your license key expired on %s. Please <a href="%s" target="_blank" title="Renew your license key">renew your license key</a>.', 'caffeine-press-media' ),
								date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) ),
								'https://caffeinepressmedia.com/checkout/?edd_license_key=' . $value
							);

							$license_status = 'license-' . $class . '-notice';

							break;

						case 'missing' :

							$class = 'error';
							$messages[] = sprintf(
								__( 'Invalid license. Please <a href="%s" target="_blank" title="Visit account page">visit your account page</a> and verify it.', 'caffeine-press-media' ),
								'https://caffeinepressmedia.com/account/'
							);

							$license_status = 'license-' . $class . '-notice';

							break;

						case 'invalid' :
						case 'site_inactive' :

							$class = 'error';
							$messages[] = sprintf(
								__( 'Your %s is not active for this URL. Please <a href="%s" target="_blank" title="Visit account page">visit your account page</a> to manage your license key URLs.', 'caffeine-press-media' ),
								$args['name'],
								'https://caffeinepressmedia.com/account/'
							);

							$license_status = 'license-' . $class . '-notice';

							break;

						case 'item_name_mismatch' :

							$class = 'error';
							$messages[] = sprintf( __( 'This is not a %s.', 'caffeine-press-media' ), $args['name'] );

							$license_status = 'license-' . $class . '-notice';

							break;

						case 'no_activations_left':

							$class = 'error';
							$messages[] = sprintf( __( 'Your license key has reached its activation limit. <a href="%s">View possible upgrades</a> now.', 'caffeine-press-media' ),
								'https://caffeinepressmedia.com/account/' );

							$license_status = 'license-' . $class . '-notice';

							break;

					}

				} else {

					switch( $license->license ) {

						case 'valid' :
						default:

							$class = 'valid';

							$now        = current_time( 'timestamp' );
							$expiration = strtotime( $license->expires, current_time( 'timestamp' ) );

							if( 'lifetime' === $license->expires ) {

								$messages[] = __( 'License key never expires.', 'caffeine-press-media' );

								$license_status = 'license-lifetime-notice';

							} elseif( $expiration > $now && $expiration - $now < ( DAY_IN_SECONDS * 30 ) ) {

								$messages[] = sprintf(
									__( 'Your license key expires soon! It expires on %s. <a href="%s" target="_blank" title="Renew license">Renew your license key</a>.', 'caffeine-press-media' ),
									date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) ),
									'https://caffeinepressmedia.com/checkout/?edd_license_key=' . $value
								);

								$license_status = 'license-expires-soon-notice';

							} else {

								$messages[] = sprintf(
									__( 'Your license key expires on %s.', 'caffeine-press-media' ),
									date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) )
								);

								$license_status = 'license-expiration-date-notice';

							}

							break;

					}

				}

			} else {
				$license_status = null;
			}

			$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
			$html = '<input type="text" class="' . sanitize_html_class( $size ) . '-text" id="edd_settings[' . edd_sanitize_key( $args['id'] ) . ']" name="edd_settings[' . edd_sanitize_key( $args['id'] ) . ']" value="' . esc_attr( $value ) . '"/>';

			if ( ( is_object( $license ) && 'valid' == $license->license ) || 'valid' == $license ) {
				$html .= '<input type="submit" class="button-secondary" name="' . $args['id'] . '_deactivate" value="' . __( 'Deactivate License',  'caffeine-press-media' ) . '"/>';
			}

			$html .= '<label for="edd_settings[' . edd_sanitize_key( $args['id'] ) . ']"> '  . wp_kses_post( $args['desc'] ) . '</label>';

			if ( ! empty( $messages ) ) {
				foreach( $messages as $message ) {

					$html .= '<div class="edd-license-data edd-license-' . $class . '">';
					$html .= '<p>' . $message . '</p>';
					$html .= '</div>';

				}
			}

			wp_nonce_field( edd_sanitize_key( $args['id'] ) . '-nonce', edd_sanitize_key( $args['id'] ) . '-nonce' );

			if ( isset( $license_status ) ) {
				echo '<div class="' . $license_status . '">' . $html . '</div>';
			} else {
				echo '<div class="license-null">' . $html . '</div>';
			}
		}

		/**
		 * Activate the license key
		 *
		 * @access  public
		 * @return  void
		 */
		public function activate_license() {

			if ( ! isset( $_POST['edd_settings'] ) ) {
				return;
			}

			if ( ! isset( $_REQUEST[ $this->item_shortname . '_license_key-nonce'] ) || ! wp_verify_nonce( $_REQUEST[ $this->item_shortname . '_license_key-nonce'], $this->item_shortname . '_license_key-nonce' ) ) {

				return;

			}

			if ( ! current_user_can( 'manage_shop_settings' ) ) {
				return;
			}

			if ( empty( $_POST['edd_settings'][ $this->item_shortname . '_license_key'] ) ) {

				delete_option( $this->item_shortname . '_license_active' );

				return;

			}

			foreach ( $_POST as $key => $value ) {
				if( false !== strpos( $key, 'license_key_deactivate' ) ) {
					// Don't activate a key when deactivating a different key
					return;
				}
			}

			$details = get_option( $this->item_shortname . '_license_active' );

			if ( is_object( $details ) && 'valid' === $details->license ) {
				return;
			}

			$license = sanitize_text_field( $_POST['edd_settings'][ $this->item_shortname . '_license_key'] );

			if( empty( $license ) ) {
				return;
			}

			// Data to send to the API
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => urlencode( $this->item_name ),
				'url'        => home_url()
			);

			// Call the API
			$response = wp_remote_post(
				$this->api_url,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params
				)
			);

			// Make sure there are no errors
			if ( is_wp_error( $response ) ) {
				return;
			}

			// Tell WordPress to look for updates
			set_site_transient( 'update_plugins', null );

			// Decode license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			update_option( $this->item_shortname . '_license_active', $license_data );

		}


		/**
		 * Deactivate the license key
		 *
		 * @access  public
		 * @return  void
		 */
		public function deactivate_license() {

			if ( ! isset( $_POST['edd_settings'] ) )
				return;

			if ( ! isset( $_POST['edd_settings'][ $this->item_shortname . '_license_key'] ) )
				return;

			if( ! wp_verify_nonce( $_REQUEST[ $this->item_shortname . '_license_key-nonce'], $this->item_shortname . '_license_key-nonce' ) ) {

				wp_die( __( 'Nonce verification failed', 'caffeine-press-media' ), __( 'Error', 'caffeine-press-media' ), array( 'response' => 403 ) );

			}

			if( ! current_user_can( 'manage_shop_settings' ) ) {
				return;
			}

			// Run on deactivate button press
			if ( isset( $_POST[ $this->item_shortname . '_license_key_deactivate'] ) ) {

				// Data to send to the API
				$api_params = array(
					'edd_action' => 'deactivate_license',
					'license'    => $this->license,
					'item_name'  => urlencode( $this->item_name ),
					'url'        => home_url()
				);

				// Call the API
				$response = wp_remote_post(
					$this->api_url,
					array(
						'timeout'   => 15,
						'sslverify' => false,
						'body'      => $api_params
					)
				);

				// Make sure there are no errors
				if ( is_wp_error( $response ) ) {
					return;
				}

				// Decode the license data
				json_decode( wp_remote_retrieve_body( $response ) );

				delete_option( $this->item_shortname . '_license_active' );

			}
		}


		/**
		 * Check if license key is valid once per week
		 *
		 * @access  public
		 * @since   2.5
		 * @return  void
		 */
		public function weekly_license_check() {

			if( ! empty( $_POST['edd_settings'] ) ) {
				return; // Don't fire when saving settings
			}

			if( empty( $this->license ) ) {
				return;
			}

			// data to send in our API request
			$api_params = array(
				'edd_action'=> 'check_license',
				'license' 	=> $this->license,
				'item_name' => urlencode( $this->item_name ),
				'url'       => home_url()
			);

			// Call the API
			$response = wp_remote_post(
				$this->api_url,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params
				)
			);

			// make sure the response came back okay
			if ( is_wp_error( $response ) ) {
				return;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			update_option( $this->item_shortname . '_license_active', $license_data );

		}


		/**
		 * Admin notices for errors
		 *
		 * @access  public
		 * @return  void
		 */
		public function notices() {

			static $showed_invalid_message;

			if( empty( $this->license ) ) {
				return;
			}

			$messages = array();

			$license = get_option( $this->item_shortname . '_license_active' );

			if( is_object( $license ) && 'valid' !== $license->license && empty( $showed_invalid_message ) ) {

				if( empty( $_GET['tab'] ) || 'licenses' !== $_GET['tab'] ) {

					$messages[] = sprintf(
						__( 'You have invalid or expired license keys for Easy Digital Downloads. Please go to the <a href="%s" title="Go to Licenses page">Licenses page</a> to correct this issue.', 'caffeine-press-media' ),
						admin_url( 'edit.php?post_type=download&page=edd-settings&tab=licenses' )
					);

					$showed_invalid_message = true;

				}

			}

			if( ! empty( $messages ) ) {

				foreach( $messages as $message ) {

					echo '<div class="error">';
					echo '<p>' . $message . '</p>';
					echo '</div>';

				}

			}

		}

		/**
		 * Displays message inline on plugin row that the license key is missing
		 *
		 * @access  public
		 * @since   2.5
		 * @return  void
		 */
		public function plugin_row_license_missing( $plugin_data, $version_info ) {

			static $showed_imissing_key_message;

			$license = get_option( $this->item_shortname . '_license_active' );

			if( ( ! is_object( $license ) || 'valid' !== $license->license ) && empty( $showed_imissing_key_message[ $this->item_shortname ] ) ) {

				echo '&nbsp;<strong><a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-settings&tab=licenses' ) ) . '">' . __( 'Enter valid license key for automatic updates.', 'caffeine-press-media' ) . '</a></strong>';
				$showed_imissing_key_message[ $this->item_shortname ] = true;
			}

		}
	}

endif; // end class_exists check