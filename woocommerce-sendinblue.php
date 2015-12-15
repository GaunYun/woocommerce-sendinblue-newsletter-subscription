<?php
/**
 * Plugin Name: WooCommerce Sendinblue Newsletter Subscription
 * Description: Allow users to subscribe to your newsletter via the checkout page and a client to send SMS campaign.
 * Author: SendinBlue
 * Author URI: https://www.sendinblue.com/?r=wporg
 * Version: 1.1.0
 * Requires at least: 3.5
 * Tested up to: 4.3
 * License: GPLv2 or later
 */
/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
	return;

// WC version check
if ( version_compare( get_option( 'woocommerce_db_version' ), '2.1', '<' ) ) {

	function woocommerce_sendinblue_outdated_version_notice() {

		$message = sprintf(
			__( '%sWooCommerce SendinBlue is inactive.%s This version requires WooCommerce 2.1 or newer. Please %supdate WooCommerce to version 2.1 or newer%s', 'wc_sendinblue' ),
			'<strong>',
			'</strong>',
			'<a href="' . admin_url( 'plugins.php' ) . '">',
			'&nbsp;&raquo;</a>'
		);

		echo sprintf( '<div class="error"><p>%s</p></div>', $message );
	}

	add_action( 'admin_notices', 'woocommerce_sendinblue_outdated_version_notice' );

	return;
}

if(!class_exists('WC_Sendinblue_Integration')) {

    register_deactivation_hook(__FILE__, array('WC_Sendinblue_Integration', 'deactivate'));
    register_activation_hook(__FILE__, array('WC_Sendinblue_Integration', 'activate'));
    register_uninstall_hook(__FILE__, array('WC_Sendinblue_Integration', 'uninstall'));

    if(!class_exists('SIB_Model_Contact')) {
        require_once 'model/model-contacts.php';
    }
    require_once 'model/model-country.php';
    require_once 'includes/wc-sendinblue.php';
    require_once 'includes/wc-sendinblue-sms.php';
    require_once 'includes/wc-sendinblue-smtp.php';

    /**
     *
     */
    class WC_Sendinblue_Integration
    {
        /** plugin version number */
        const VERSION = '1.0.0';

        /** @var \WC_Sendinblue_Integration_Settings instance */
        public $settings;

        /** var array the active filters */
        public $filters;

        /**
         * Initializes the plugin
         */
        public function __construct()
        {
            // notify the sms limit
            add_action('ws_hourly_event', array($this,'do_sms_limit_notify'));
            // load translation
            add_action('init', array($this, 'init'));

            $this->customizations = get_option('wc_sendinblue_settings', array());

            // admin
            if (is_admin() && !defined('DOING_AJAX')) {

                // load settings page
                add_filter('woocommerce_get_settings_pages', array($this, 'add_settings_page'));

                // add a 'Configure' link to the plugin action links
                //add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));

                // run every time
                $this->install();
            }

            // Init variables
            $this->ws_subscribe_enable = isset($this->customizations['ws_subscription_enabled']) ? $this->customizations['ws_subscription_enabled'] : 'yes';
            $this->ws_smtp_enable      = isset($this->customizations['ws_smtp_enable']) ? $this->customizations['ws_smtp_enable'] : 'yes';
            $this->ws_template_enable  = isset($this->customizations['ws_email_templates_enable']) ? $this->customizations['ws_email_templates_enable'] : 'no';
            $this->ws_sms_enable       = isset($this->customizations['ws_sms_enable']) ? $this->customizations['ws_sms_enable'] : 'no';


            add_action('woocommerce_init', array($this, 'load_customizations'));

            // Register style sheet.
            add_action('admin_enqueue_scripts', array($this, 'register_plugin_scripts'));

            //
            add_action('admin_print_scripts', array($this, 'admin_inline_js'));

            // Maybe add an "opt-in" field to the checkout
            add_filter('woocommerce_checkout_fields', array($this, 'maybe_add_checkout_fields'));

            // front-end
            // We would use the 'woocommerce_new_order' action but first name, last name and email address (order meta) is not yet available,
            // so instead we use the 'woocommerce_checkout_update_order_meta' action hook which fires after the checkout process on the "thank you" page
            //add_action('woocommerce_checkout_update_order_meta', array($this, 'ws_checkout_update_order_meta'), 1000, 1);

            // hook into woocommerce order status changed hook to handle the desired subscription event trigger
            add_action('woocommerce_order_status_changed', array($this, 'ws_order_status_changed'), 10, 3);

            // Maybe save the "opt-in" field on the checkout
            add_action('woocommerce_checkout_update_order_meta', array($this, 'maybe_save_checkout_fields'));

            // Enable SendinBlue to send emails
            // Add an action on phpmailer_init
            add_action('phpmailer_init', array($this, 'phpmailer_sendinblue_smtp'),15);

            // Alert credit is not enough
            add_action( 'admin_notices', array($this, 'ws_admin_credits_notice'));

            // Ajax
            add_action('wp_ajax_ws_validation_process', array('WC_Sendinblue', 'ajax_validation_process'));
            add_action('wp_ajax_ws_sms_test_send', array('WC_Sendinblue_SMS', 'ajax_sms_send'));
            add_action('wp_ajax_ws_sms_refresh', array('WC_Sendinblue_SMS', 'ajax_sms_refresh'));
            add_action('wp_ajax_ws_sms_campaign_send', array('WC_Sendinblue_SMS', 'ajax_sms_campaign_send'));
            add_action('wp_ajax_ws_get_daterange', array('WC_Sendinblue_SMTP', 'ajax_get_daterange'));
            add_action('wp_ajax_ws_email_campaign_send', array('WC_Sendinblue_SMTP', 'ajax_email_campaign_send'));
            add_action('wp_ajax_ws_dismiss_alert', array($this, 'ajax_dismiss_alert'));
            add_action('wp_ajax_ws_transient_refresh', array($this, 'ajax_transient_refresh'));

            // hook to send woocommerce email
            add_filter('wc_get_template', array($this, 'ws_get_template_type'),10, 2);
            add_filter('woocommerce_email_headers', array($this, 'woocommerce_mail_header'),15);
            add_filter('woocommerce_mail_content', array($this, 'woocommerce_mail_content'),20);
            add_filter('woocommerce_email_styles', array($this, 'ws_get_email_style'));

        }

        /**
         * Inline scripts
         */
        public function admin_inline_js()
        {
            //variable in send SMS page

            echo "<script type='text/javascript'>\n";

            if ((isset($_GET['section']) && $_GET['section'] == 'sms_options') || (isset($_GET['section']) && $_GET['section'] == 'campaigns')) {
                echo 'var VAR_SMS_MSG_DESC = "' . __('If you want to personalize the SMS, you can use the variables below:<br>\
                        - For first name use {first_name}<br>\
                        - For last name use {last_name}<br>\
                        - For order price use {order_price}<br>\
                        - For order date use {order_date}', 'wc_sendinblue') . '";';
            }
            echo 'var ws_tab ="' . (isset($_GET['tab']) ? $_GET['tab'] : '') . '";';
            echo 'var ws_section ="' . (isset($_GET['section']) ? $_GET['section'] : '') . '";';
            echo 'var SEND_BTN ="'. __('Send','wc_sendinblue') .'";';
            echo 'var SEND_CAMP_BTN ="'. __('Send the campaign','wc_sendinblue') .'";';
            echo "\n</script>";

        }

        /**
         * Load scripts
         */
        public function register_plugin_scripts($hook)
        {
            if (!isset($_GET['tab'])) return;

            if ($hook == 'woocommerce_page_wc-settings' && $_GET['tab'] == 'sendinblue') {
                wp_enqueue_script('wc_sendinblue_js', plugin_dir_url(__FILE__) . 'assets/js/sendinblue_admin.js');
                wp_enqueue_script('ws-ui-js',  plugin_dir_url(__FILE__)  . '/assets/js/jquery-ui.js');
                wp_enqueue_script('ws-moment-js', plugin_dir_url(__FILE__) . '/assets/js/moment.js');
                wp_enqueue_script('wc-date-js', plugin_dir_url(__FILE__) . '/assets/js/jquery.comiseo.daterangepicker.js');
                wp_localize_script('wc_sendinblue_js', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
                //
                wp_enqueue_style('wc_sendinblue_css', plugin_dir_url(__FILE__) . '/assets/css/sendinblue_admin.css');
                wp_enqueue_style('ws-ui-css',  plugin_dir_url(__FILE__) . '/assets/css/jquery-ui.css');
                wp_enqueue_style('wc-date-css', plugin_dir_url(__FILE__) . '/assets/css/jquery.comiseo.daterangepicker.css');
            }
            return;
        }

        /**
         * Add settings page
         *
         * @param array $settings
         * @return array
         */
        public function add_settings_page($settings)
        {
            $settings[] = require_once('includes/wc-sendinblue-settings.php');
            return $settings;
        }

        /**
         * Load customizations after WC is loaded
         */
        public function load_customizations()
        {
            if(!isset($_GET['tab']) || $_GET['tab'] != 'sendinblue') return;
            //
            add_action('admin_notices', array($this, 'ws_api_check'));
            
            WC_Sendinblue::init();

        }

        /**
         * Initialize method.
         *
         */
        public function init()
        {
            // Redirect after activate plugin
            if (get_option('ws_do_activation_redirect', false)) {
                delete_option('ws_do_activation_redirect');
                if(!isset($_GET['activate-multi'])) {
                    wp_redirect(add_query_arg('page', 'wc-settings&tab=sendinblue', admin_url('admin.php')));
                }
            }

            // localization in the init action for WPML support
            load_plugin_textdomain('wc_sendinblue', false, dirname(plugin_basename(__FILE__)) . '/languages');

            // subscribe
            if (isset($_GET['ws_action']) && ($_GET['ws_action'] == 'subscribe')) {
                WC_Sendinblue::subscribe();
                exit;
            }
            if((isset($_GET['ws_action'])) && ($_GET['ws_action'] == 'logout')) {
                WC_Sendinblue::logout();
            }

        }

        /** Lifecycle methods ******************************************************/
        /**
         * Run every time.  Used since the activation hook is not executed when updating a plugin
         */
        private function install()
        {
            // get current version to check for upgrade
            $installed_version = get_option('WC_Sendinblue_Integration_version');
            // install
            if (!$installed_version) {
                // install default settings
            }
            // upgrade if installed version lower than plugin version
            if (-1 === version_compare($installed_version, self::VERSION)) {
                $this->upgrade($installed_version);
            }
        }

        /**
         * Perform any version-related changes.
         *
         * @param int $installed_version the currently installed version of the plugin
         */
        private function upgrade($installed_version)
        {
            // update the installed version option
            update_option('WC_Sendinblue_Integration_version', self::VERSION);
        }

        /**
         * Check if an api key is correct
         **/
        function ws_api_check() {
            // Check required fields
            if ( WC_Sendinblue::$access_key == '' && WC_Sendinblue::$ws_error_type != '') {
                // Show notice
                echo $this->get_message( __( 'SendinBlue error: ', 'wc_sendinblue' ) . WC_Sendinblue::$ws_error_type );
            }
        }

        /**
         * Get message
         * @return string Error
         */
        private function get_message( $message, $type = 'error' ) {
            ob_start();

            ?>
            <div class="<?php echo $type ?>">
                <p><?php echo $message ?></p>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Display alert when you don't have enough credits
         */
        function ws_admin_credits_notice(){
            if(WC_Sendinblue::$account_info['email_credits'] < 2 && get_option('ws_credits_notice') != 'closed' && get_option('ws_credits_notice') != null) {
                $class = "error notice is-dismissible ws_credits_notice";
                $message = __('You don\'t have enough credits to send email through <b>SendinBlue SMTP</b>. ', 'wc_sendinblue');
                $url = sprintf(__('<i>To buy more credits, please click %s. </i>', 'wc_sendinblue'),
                    "<a target='_blank' href='https://www.sendinblue.com/pricing?utm_source=wordpress_plugin&utm_medium=plugin&utm_campaign=module_link' class='ws_refresh'> here</a>");
                $button = '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
                echo "<div class=\"$class\"> <p>$message$url</p>$button</div>";
            }
        }

        /**
         * WooCommerce 2.2 support for wc_get_order
         * @access private
         * @param int $order_id
         * @return void
         */
        private function wc_get_order($order_id)
        {
            if (function_exists('wc_get_order')) {
                return wc_get_order($order_id);
            } else {
                return new WC_Order($order_id);
            }
        }

        /**
         * by Subscription and SMS Options
         * order_status_changed function.
         * @access public
         * @return void
         */
        public function ws_order_status_changed($id, $status = 'new', $new_status = 'pending')
        {
            // Get WC order
            $order = $this->wc_get_order($id);

            // Customer will be added to a list after subscribe event occur
            if ($this->ws_subscribe_enable == 'yes' && $new_status == $this->customizations['ws_order_event']) {

                $ws_dopt_enabled = isset($this->customizations['ws_dopt_enabled']) ? $this->customizations['ws_dopt_enabled'] : 'no';
                // get the ws_opt_in value from the post meta.
                $ws_opt_in = get_post_meta($id, 'ws_opt_in', true); // yes or no
                $info = array(
                    'NAME' => $order->billing_first_name,
                    'SURNAME' => $order->billing_last_name,
                    'SMS' => $order->billing_phone,
                    /* woocommerce attrs */
                    //'PRICE' => $order->order_total,
                    //'PAYMENT METHOD' => $order->payment_method_title,
                    'ORDER_ID' => $id,
                    'ORDER_DATE' => strtotime( $order->order_date ),
                    'ORDER_PRICE' => $order->order_total
                );

                if ( $ws_dopt_enabled == 'yes' && $ws_opt_in == 'yes' ) {
                    $wc_sib_smtp = new WC_Sendinblue_SMTP();
                    $dopt_template_id = $this->customizations['ws_dopt_templates'];
                    $info['DOUBLE_OPT-IN'] = '1';
                    $wc_sib_smtp->double_optin_signup($order->billing_email, $this->customizations['ws_sendinblue_list'], $info, $dopt_template_id);
                } else if( $ws_opt_in == 'yes' ){
                    $wc_sib = new WC_Sendinblue();
                    $wc_sib->create_subscriber($order->billing_email, $this->customizations['ws_sendinblue_list'], $info);
                }
            }

            // send confirmation SMS
            if($this->ws_sms_enable == 'yes') {

                $wc_sib_sms = new WC_Sendinblue_SMS();
                $ws_sms_send_after = isset($this->customizations['ws_sms_send_after']) ? $this->customizations['ws_sms_send_after'] : 'no';
                $ws_sms_send_shipment = isset($this->customizations['ws_sms_send_shipment']) ? $this->customizations['ws_sms_send_shipment'] : 'no';

                // send a SMS confirmation for order confirmation
                if ($ws_sms_send_after == 'yes' && $new_status == 'pending'){
                    $from = $this->customizations['ws_sms_sender_after'];
                    $text = $this->customizations['ws_sms_send_msg_desc_after'];
                    $wc_sib_sms->ws_send_confirmation_sms($order, $from, $text);
                }
                // send a SMS confirmation for the shipment of the order
                if ($ws_sms_send_shipment == 'yes' && $new_status == 'completed'){
                    $from = $this->customizations['ws_sms_sender_shipment'];
                    $text = $this->customizations['ws_sms_send_msg_desc_shipment'];
                    $wc_sib_sms->ws_send_confirmation_sms($order, $from, $text);
                }
            }
        }

        /**
         * Add the opt-in checkbox to the checkout fields (to be displayed on checkout).
         */
        function maybe_add_checkout_fields($checkout_fields)
        {
            $display_location = isset($this->customizations['ws_opt_checkbox_location']) ? $this->customizations['ws_opt_checkbox_location'] : '';

            if (empty($display_location)) {
                $display_location = 'billing';
            }
            $ws_opt_field = isset($this->customizations['ws_opt_field']) ? $this->customizations['ws_opt_field'] : 'no';
            if ('yes' == $ws_opt_field) {
                $checkout_fields[$display_location]['ws_opt_in'] = array(
                    'type' => 'checkbox',
                    'label' => esc_attr($this->customizations['ws_opt_field_label']),
                    'default' => $this->customizations['ws_opt_default_status'] == 'checked' ? 1 : 0,
                );
            }

            return $checkout_fields;
        }

        /**
         * When the checkout form is submitted, save opt-in value.
         */
        function maybe_save_checkout_fields($order_id)
        {
            $ws_opt_enable = isset($this->customizations['ws_opt_field']) ? $this->customizations['ws_opt_field'] : 'no';
            if ('yes' == $ws_opt_enable) {
                $opt_in = isset($_POST['ws_opt_in']) ? 'yes' : 'no';
                update_post_meta($order_id, 'ws_opt_in', $opt_in);
            }else {
                //customer will be added to a list
                update_post_meta($order_id, 'ws_opt_in', 'yes');
            }
        }

        /**
         * When SendinBlue is enabled to send Woocommerce emails
         * replace email template with one of SendinBlue instead of Woo template
         */
        function ws_get_template_type($path,$file){

            if($this->ws_smtp_enable == 'yes') {//ws_template_enable

                $files = explode('/', $file);
                $files = explode('.', $files[1]);
                $type = $files[0];
                // ex, admin-new-order.php to admin-new-order
                $email_type = array(
                    'admin-new-order' => 'New Order',
                    'admin-cancelled-order' => 'Cancelled Order',
                    'customer-completed-order' => 'Completed Order',
                    'customer-new-account' => 'New Account',
                    'customer-processing-order' => 'Processing Order',
                    'customer-refunded-order' => 'Refunded Order',
                );
                if(array_key_exists($type, $email_type)) {
                    $template_type = $email_type[$type]; // ex, New order
                    $template_ids = get_option('ws_email_templates', array());
                    $template_id = $template_ids[$template_type];
                    $user_id = get_current_user_id();
                    $template = array(
                        'id' => $template_id,
                        'type' => $template_type
                    );
                    update_user_meta($user_id, 'ws_template_type', $template);
                }

            }
            return $path;
        }
        // Add template tags in email header
        function woocommerce_mail_header($header){
            if($this->ws_smtp_enable == 'yes') {
                $user_id = get_current_user_id();
                $template = get_user_meta($user_id, 'ws_template_type', true);
                $header .= 'X-Mailin-Tag: ' . $template['type'] . "\r\n";
            }
            return $header;
        }
        // Replace email template with one of SendinBlue
        function woocommerce_mail_content($message){

            if($this->ws_smtp_enable == 'yes') {

                $user_id = get_current_user_id();
                $template = get_user_meta($user_id, 'ws_template_type', true);
                if($template['id'] == '0') return $message;
                $sib_templates = get_option('ws_templates',array());
                $sib_template = $sib_templates[$template['id']];
                delete_user_meta($user_id,'ws_template_type');

                return $sib_template['content'];
            }
            return $message;
        }
        // Replace css of email template
        function ws_get_email_style($css){

            $user_id = get_current_user_id();
            $template_id = get_user_meta($user_id, 'ws_template_type', true); // ex: SIB template id of New order
            if($template_id == '0')
                return $css;
            if($this->ws_template_enable == 'yes')
                return '';
            return $css;
        }
        /* end of replace email template */

        /**
         * send notify email for limit of sms credits
         */
        function do_sms_limit_notify() {
            // do something every day
            $sms_limit = isset($this->customizations['ws_sms_credits_limit']) ? $this->customizations['ws_sms_credits_limit'] : 0;
            $sms_limit_email = isset($this->customizations['ws_sms_credits_notify_email']) ? $this->customizations['ws_sms_credits_notify_email'] : '';
            $notify_status = isset($this->customizations['ws_sms_credits_notify']) ? $this->customizations['ws_sms_credits_notify'] : 'no';
            $current_sms_num = WC_Sendinblue::ws_get_credits();

            if( $notify_status == 'yes' && $sms_limit_email != '' && $sms_limit != 0 && $current_sms_num < $sms_limit ){
                $subject = __('Notification of your credits', 'wc_sendinblue');
                WC_Sendinblue_SMTP::send_email('notify', $sms_limit_email, $subject , $current_sms_num);
            }
        }
        /**
         * Enable sendiblue SMTP
         */
        public function phpmailer_sendinblue_smtp($phpmailer){

            $general_settings = get_option('ws_main_option', array());

            if($this->ws_smtp_enable == 'yes'){
                $phpmailer->isSMTP();
                $phpmailer->SMTPSecure = 'tls';
                $phpmailer->Host = 'smtp-relay.sendinblue.com';
                $phpmailer->Port = '587';
                $phpmailer->SMTPAuth = TRUE;
                $phpmailer->Username = $general_settings['account_email'];
                $phpmailer->Password = $general_settings['access_key'];
            }
        }
        /**
         * Uninstall method is called once uninstall this plugin
         * delete tables, options that used in plugin
         */
        static function uninstall()
        {
            $setting = array();
            update_option('ws_main_option', $setting);
            update_option('wc_sendinblue_settings', $setting);
            update_option('ws_email_templates',$setting);
            update_option('ws_templates',$setting);
        }

        /**
         * Deactivate method is called once deactivate this plugin
         */
        static function deactivate()
        {
            SIB_Model_Country::remove_table();

            wp_clear_scheduled_hook('ws_hourly_event');

            // remove transients
            $general_settings = get_option('ws_main_option');
            $access_key = isset($general_settings['access_key']) ? $general_settings['access_key'] : '';
            delete_transient('wswcsbcredit_' . md5( $access_key ));
            delete_transient('wstemplate_' . md5( $access_key ));
            delete_transient('wswcsblist_' . md5( $access_key ));
            delete_transient('wsdopttemplate_' . md5( $access_key ));
        }

        /**
         * Install method is called once install this plugin.
         * create tables, default option ...
         */
        static function activate()
        {
            SIB_Model_Contact::create_table();

            // Get the country code data
            SIB_Model_Country::create_table();

            $file = fopen(plugin_dir_path(__FILE__) . "/model/country_code.csv", "r");
            $country_code = array();
            while (!feof($file)) {
                $code = fgetcsv($file);
                $country_code[$code[0]] = $code[1];
            }
            fclose($file);

            SIB_Model_Country::Initialize($country_code);

            // redirect option
            update_option('ws_do_activation_redirect', true);
        }



        /* ajax module for dismiss alert */
        public function ajax_dismiss_alert(){

            $alert_type = isset($_POST['type']) ? $_POST['type'] : 'credit';
            update_option('ws_credits_notice', 'closed');
            echo 'success';
            die();
        }

        /* ajax module for initialize transients */
        public function ajax_transient_refresh(){
            // remove transients
            $general_settings = get_option('ws_main_option');
            $access_key = isset($general_settings['access_key']) ? $general_settings['access_key'] : '';
            delete_transient('wswcsbcredit_' . md5( $access_key ));
            delete_transient('wstemplate_' . md5( $access_key ));
            delete_transient('wswcsblist_' . md5( $access_key ));
            delete_transient('wsdopttemplate_' . md5( $access_key ));
            echo 'success';
            die();
        }
    }

    /**
     * The WC_Sendinblue_Integration global object
     * @name $WC_Sendinblue_Integration
     * @global WC_Sendinblue_Integration $GLOBALS ['WC_Sendinblue_Integration']
     */
    $GLOBALS['WC_Sendinblue_Integration'] = new WC_Sendinblue_Integration();
}
