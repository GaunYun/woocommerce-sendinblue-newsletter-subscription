<?php
/*
Plugin Name: WooCommerce Sendinblue Newsletter Subscription
Description: Allow customers to subscribe to your Sendinblue newsletters via the checkout page and trough Web Forms from a widget.
Version: 1.0.1
Author: Codeinwp
Author URI: http://codeinwp.com
Requires at least: 3.5
Tested up to: 3.9

    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

	/**
	 * Localisation
	 */
	load_plugin_textdomain( 'wc_sendinblue', false, dirname( plugin_basename( __FILE__ ) ) . '/' );

	/**
	 * woocommerce_sendinblue class
	 */
	if ( !class_exists( 'woocommerce_sendinblue' ) ) {
		
		class woocommerce_sendinblue {
			var $adminOptionsName = 'woo_sendinblue_settings';
			/**
			 * __construct function.
			 *
			 * @access public
			 * @return void
			 */
			public function __construct() {
				// Add tab to woocommerce settings
				add_action( 'woocommerce_settings_tabs', array( $this, 'settings_menu' ) );
				add_action( 'woocommerce_settings_tabs_woo_sendinblue', array( $this, 'settings' ) );
				add_action( 'woocommerce_update_options_woo_sendinblue', array( $this, 'settings_update' ) );

				// Add frontend field
				add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'sendinblue_field' ), 5 );
				add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_sendinblue_field' ), 5, 2 );

				// Dashboard Subscriber Info
				add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
			} // End __construct()

			/**
			 * settings_menu function.
			 *
			 * @access public
			 * @return void
			 */
			function settings_menu() {
				$current_tab = ( isset( $_GET['tab'] ) ) ? $_GET['tab'] : 'general';
				$name = 'woo_sendinblue';
				$label = __( 'SendinBlue', 'wc_sendinblue' );

				echo '<a href="' . admin_url( 'admin.php?page=woocommerce&tab=' . $name ) . '" class="nav-tab ';
				if( $current_tab==$name ) echo 'nav-tab-active';
				echo '">' . $label . '</a>';
			} // End settings_menu()

			/**
			 * settings function.
			 *
			 * @access public
			 * @return void
			 */
			function settings() {
				$admin_options = get_option( $this->adminOptionsName );

				if ( ! class_exists( 'Mailin' ) )
					require_once plugin_dir_path( $this->base->file ) . 'sendinblue_api/sendinblue.class.php';

				if ( isset( $_GET['wc_sb_access_key'] ) && ! isset( $_GET['saved'] ) ) {		

					$admin_options['access_token'] = $_GET['wc_sb_access_key'];
					$admin_options['access_secret'] = $_GET['wc_sb_secret_key'];
					update_option( $this->adminOptionsName, $admin_options );
				}


				//Try to connect and get account details and lists
				try {
					$account= new Mailin( 'https://api.sendinblue.com/v1.0',$admin_options['access_token'], $admin_options['access_secret'] );
					//$lists = $api->get_lists();
				} catch ( sendinblueException $e ) {
					$account = null;
					$admin_options['access_token'] = null;
					$admin_options['access_secret'] = null;
					update_option( $this->adminOptionsName, $admin_options );
				}
				
				$lists = $account->get_lists();
				

				if ( ! isset( $admin_options['subscribe_checkout'] ) )
					$admin_options['subscribe_checkout'] = 0;
				if ( ! isset( $admin_options['subscribe_label'] ) )
					$admin_options['subscribe_label'] = '';
				if ( ! isset( $admin_options['subscribe_checked'] ) )
					$admin_options['subscribe_checked'] = 0;
				if ( ! isset( $admin_options['subscribe_id'] ) )
					$admin_options['subscribe_id'] = -1;

				include( 'templates/settings.php' );
			} // End settings()

			/**
			 * settings_update function.
			 *
			 * @access public
			 * @return void
			 */
			function settings_update () {
				$admin_options = get_option( $this->adminOptionsName );
			
				if ( isset( $_POST['wc_sb_subscribe_checkout'] ) )
					$admin_options['subscribe_checkout'] = '1';
				else $admin_options['subscribe_checkout'] = '0';
				if ( isset( $_POST['wc_sb_subscribe_label'] ) )
					$admin_options['subscribe_label'] = $_POST['wc_sb_subscribe_label'];
				if ( isset( $_POST['wc_sb_access_key'] ) )
					$admin_options['access_token'] = $_POST['wc_sb_access_key'];
				if ( isset( $_POST['wc_sb_access_key'] ) )
					$admin_options['access_secret'] = $_POST['wc_sb_secret_key'];
				if ( isset( $_POST['wc_sb_subscribe_checked'] ) )
					$admin_options['subscribe_checked'] = '1';
				else $admin_options['subscribe_checked'] = '0';
				if ( isset( $_POST['wc_sb_subscribe_id'] ) )
					$admin_options['subscribe_id'] = $_POST['wc_sb_subscribe_id'];
				update_option( $this->adminOptionsName, $admin_options );
			} // End settings_update()

			/**
			 * sendinblue_field function.
			 *
			 * @access public
			 * @param object $woocommerce_checkout
			 * @return void
			 */
			function sendinblue_field( $woocommerce_checkout ) {
				$admin_options = get_option( $this->adminOptionsName );
				// Only display subscribe checkbox when sendinblue authorised to access account
				if ( $admin_options['access_token'] ) {
					if ( $admin_options['subscribe_checkout'] == '1' )
						$woocommerce_checkout->posted['subscribe_to_sendinblue'] = 1;

					woocommerce_form_field( 'subscribe_to_sendinblue', array(
							'type'  => 'checkbox',
							'class' => array( 'form-row-wide' ),
							'label' => $admin_options['subscribe_label']
						), $admin_options['subscribe_checked'] );

					echo '<div class="clear"></div>';
				}
			} // End sendinblue_field()

			/**
			 * process_sendinblue_field function.
			 *
			 * @access public
			 * @param int $order_id
			 * @param array $posted
			 * @return void
			 */
			function process_sendinblue_field( $order_id, $posted ) {
				if ( !isset( $_POST['subscribe_to_sendinblue'] ) )
					return; //No Subscription

				$admin_options = get_option( $this->adminOptionsName );

				$sendinblue_results = $this->create_subscriber( $posted['billing_email'], $admin_options['subscribe_id'], $posted['billing_first_name'] );
				print_r($sendinblue_results);
			} // End process_sendinblue_field()

			/**
			 * add_dashboard_widgets function.
			 *
			 * @access public
			 * @return void
			 */
			function add_dashboard_widgets() {
				wp_add_dashboard_widget( 'woocommerce_sendinblue_subscriber_dashboard', __( 'Sendinblue Newsletter Subscribers', 'wc_sendinblue' ), array( $this, 'subscriber_stats' ) );
			} // End add_dashboard_widgets()

			/**
			 * subscriber_stats function.
			 *
			 * @access public
			 * @return void
			 */
			function subscriber_stats() {
				$admin_options = get_option( $this->adminOptionsName );
				if ( ! class_exists( 'Mailin' ) )
                    require_once plugin_dir_path( $this->base->file ) . 'sendinblue_api/sendinblue.class.php';
				if ( $admin_options['access_token'] ) {
					if ( !$html = get_transient( 'woo_sendinblue_stats', 60*60 ) ) {
						try {
							
							$account= new Mailin( 'https://api.sendinblue.com/v1.0',$admin_options['access_token'], $admin_options['access_secret'] );
							$list = $account->get_list($admin_options['subscribe_id']);
							
							$list = $list['data'];
							if ($list["total_blacklisted"]=="") $list["total_blacklisted"] = 0;
							$html  = '<ul class="woocommerce_stats">';
							$html .= '<li><strong>'. $list["total_subscribers"] .'</strong> ' . __( 'Total subscribers', 'wc_sendinblue' ) . '</li>';
							$html .= '<li><strong>'. $list["total_blacklisted"] .'</strong> ' . __( 'Total blacklisted', 'wc_sendinblue' ) . '</li>';
							$html .= '<li><strong>'. $list["click_rate_percentage"] .'</strong> ' . __( 'Click rate percentage', 'wc_sendinblue' ) . '</li>';
							$html .= '<li><strong>'. $list["open_rate_percentage"] .'</strong> ' . __( 'Open rate percentage', 'wc_sendinblue' ) . '</li>';

							$html .= '</ul>';
							set_transient( 'woo_sendinblue_stats', $html, 60*60 );
						} catch ( Exception $e ) {
							$admin_options['access_token'] =  null;
							delete_transient( 'woo_sendinblue_stats' );
							echo '<div class="error inline"><p>' . __( 'Please authorise WooCommerce to access your sendinblue account.', 'wc_sendinblue' ) . '</p></div>';
						}
					}
					echo $html;
				} else {
					echo '<div class="error inline"><p>' . __( 'Please authorise WooCommerce to access your SendinBlue account.', 'wc_sendinblue' ) . '</p></div>';
				}
			} // End subscriber_stats()


			/**
			 * create_subscriber function.
			 *
			 * @access public
			 * @param string $email
			 * @param string $ip
			 * @param string $list_id
			 * @param string $name
			 * @return void
			 */
			function create_subscriber( $email, $list_id, $name ) {
				$admin_options = get_option( $this->adminOptionsName );
				if ( ! class_exists( 'Mailin' ) )
                    require_once plugin_dir_path( $this->base->file ) . 'sendinblue_api/sendinblue.class.php';
				try {
					  
                    $account = new Mailin( 'https://api.sendinblue.com/v1.0',$admin_options['access_token'], $admin_options['access_secret'] );
                    $blacklisted = 0;
                    $listid_unlink = array();
                    $info['NAME'] = $name;
                    $ret = $account->create_update_user( $email, $info, $blacklisted, (array)$list_id, $listid_unlink );

					return $ret;
				} catch ( Exception $e ) {
					//List ID was not in this account
					if ( $e->type === 'NotFoundError' ) {
						//$options = get_option( $this->widgetOptionsName );
						//$options['list_id_create_subscriber'] = null;
						//update_option($this->widgetOptionsName, $options);
					}
					//Authorization is invalid
					if ( $e->type === 'UnauthorizedError' )
						$this->deauthorize();
				}
			} // End create_subscriber()
		} // End Class

		// Instantiate the class
		global $woocommerce_sendinblue;
		$woocommerce_sendinblue = new woocommerce_sendinblue();
	}
