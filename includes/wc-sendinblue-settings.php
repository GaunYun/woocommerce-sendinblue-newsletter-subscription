<?php
/**
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Settings
 *
 * Adds UX for adding/modifying customizations
 *
 * @since 2.0.0
 */
class WC_Sendinblue_Settings extends WC_Settings_Page {

    public static $wc_emails;

    public static $wc_emails_enabled;

	/**
	 * Add various admin hooks/filters
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->id    = 'sendinblue';
		$this->label = __( 'Sendinblue', 'wc_sendinblue' );

        self::$wc_emails_enabled = array(
            'WC_Email_New_Order' => 'new_order',
            'WC_Email_Cancelled_Order' => 'cancelled_order',
            'WC_Email_Customer_Processing_Order' => 'customer_processing_order',
            'WC_Email_Customer_Completed_Order' => 'customer_completed_order',
            'WC_Email_Customer_Refunded_Order' => 'customer_refunded_order',
            'WC_Email_Customer_New_Account' => 'customer_new_account',
        );


		// add tab
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );

		// show sections
		add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );

		// show settings
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );

		// save settings
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );

		$this->customizations = get_option( 'wc_sendinblue_settings', array() );

        // Custom action for statistics section
        add_action('woocommerce_settings_ws_statistics_after',  array($this, 'ws_statistics'));
        // Custom action for sms options section
        add_action('woocommerce_settings_ws_sms_notification_end',  array($this, 'ws_sms_notification'));
        // Custom action for email section
        add_action('woocommerce_settings_ws_notification_activation_after',  array($this, 'ws_notification_activation'));
        add_action('woocommerce_settings_ws_sendinblue_templates_after',  array($this, 'ws_sendinblue_templates'));
        // Custom action for campaign section
        //add_action('woocommerce_settings_ws_email_campaign_follow_contacts_end',  array($this, 'ws_email_campaign_follow_contacts'));
        //add_action('woocommerce_settings_ws_email_campaign_send_after',  array($this, 'ws_email_campaign_send'));
        add_action('woocommerce_settings_ws_sms_campaign_send_end',  array($this, 'ws_sms_campaign_send'));


	}

	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections() {
        if( isset(WC_Sendinblue::$access_key) && WC_Sendinblue::$access_key != '') {
            return array(
                '' => __('General', 'wc_sendinblue'),
                'subscribe' => __('Subscription Options', 'wc_sendinblue'),
                'email_options' => __('Email Options', 'wc_sendinblue'),
                'sms_options' => __('SMS options', 'wc_sendinblue'),
                'campaigns' => __('SMS Campaign', 'wc_sendinblue'),
            );
        }else{
            return null;
        }
	}

    /*
     * new_order, cancelled_order, customer_processing_order, customer_completed_order, customer_refunded_order

       customer_invoice, customer_note, customer_reset_password, customer_new_account
     * */

	/**
	 * Render the settings for the current section
	 *
	 * @since 2.0.0
	 */
	public function output() {

		$settings = $this->get_settings();

		// inject the actual setting value before outputting the fields
		// ::output_fields() uses get_option() but customizations are stored
		// in a single option so this dynamically returns the correct value
        if(isset($_GET['section']) && $_GET['section'] == 'email_options') {
            // email options
            $wc_emails = (array)WC_Emails::instance()->emails;

            foreach(self::$wc_emails_enabled as $filed=>$id) {
                $email_enabled = (array)($wc_emails[$filed]);
                if ($email_enabled['enabled'] == 'no') {
                    add_filter("pre_option_{$filed}", function () {  return 'no';  });
                } else {
                    add_filter("pre_option_{$filed}", function () {  return 'yes'; });
                }
            }
        }
        if(isset($_GET['section']) && $_GET['section'] == 'sms_options') {

        }

        foreach ( $this->customizations as $filter => $value ) {
            add_filter("pre_option_{$filter}", array($this, 'get_customization'));
        }

		WC_Admin_Settings::output_fields( $settings );
	}


	/**
	 * Return the customization value for the given filter
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_customization() {

		$filter = str_replace( 'pre_option_', '', current_filter() );

        return isset($this->customizations[$filter]) ? $this->customizations[$filter] : '';


	}


	/**
	 * Save the customizations
	 *
	 * @since 2.0.0
	 */
	public function save() {

        $wc_plugin_id = 'woocommerce_';

        // Email options
        if($_GET['section'] == 'email_options') {
            $notification_activation = array();
            foreach (self::$wc_emails_enabled as $filed => $id) {

                $email_settings = get_option($wc_plugin_id . $id . '_settings', null);
                $email_settings['enabled'] = 'no';
                if (!empty($_POST[$filed])) {
                    $email_settings['enabled'] = 'yes';
                    array_push($notification_activation, str_replace('_', ' ', str_replace('Customer_', '', str_replace('WC_Email_', '', $filed))));
                }
                update_option($wc_plugin_id . $id . '_settings', $email_settings);
                //
                update_option('ws_notification_activation',$notification_activation);
            }

        }
        //////////// Default options ////////////
		foreach ( $this->get_settings() as $field ) {

			// skip titles, etc
			if ( ! isset( $field['id'] ) ) {
				continue;
			}

			if ( ! empty( $_POST[ $field['id'] ] ) ) {

                // active notification of sms credits
                if($field['id'] == 'ws_sms_credits_notify' && !isset($this->customizations[ $field['id'] ])){
                    // Create a schedule to send notify email for SMS credits limit
                    wp_schedule_event(time(), 'hourly', 'ws_hourly_event');// or 'daily'
                }

				$this->customizations[ $field['id'] ] = wp_kses_post( stripslashes( $_POST[ $field['id'] ] ) );

                if($field['type'] == 'checkbox' || $field['type'] == 'fake_checkbox'){

                    $this->customizations[ $field['id'] ] = 'yes';

                }


			} else {

                if($field['type'] == 'checkbox' || $field['type'] == 'fake_checkbox'){

                    $this->customizations[ $field['id'] ] = 'no';

                } elseif( isset( $this->customizations[ $field['id'] ] )) {

                    unset($this->customizations[$field['id']]);

                }
                // inactive notification of sms credits
                if($field['id'] == 'ws_sms_credits_notify'){
                    wp_clear_scheduled_hook('ws_hourly_event');
                }
			}
		}

		update_option( 'wc_sendinblue_settings', $this->customizations );

	}


	/**
	 * Return admin fields in proper format for outputting / saving
	 *
	 * @since 1.1
	 * @return array
	 */
	public function get_settings() {

        $account_user_name = WC_Sendinblue::$account_info['user_name'];
        $account_email = WC_Sendinblue::$account_info['email'];
        $account_email_credits = WC_Sendinblue::$account_info['email_credits'];
        $account_sms_credits = WC_Sendinblue::$account_info['SMS_credits'];

        $settings_init = array(
            'general' =>

                array(

                    array(
                        'title' => __( 'WooCommerce Sendinblue Integration', 'wc_sendinblue' ),
                        'type'  => 'title',
                        'desc'  => __('Activate your account with your API key', 'wc_sendinblue'),
                    ),

                    array(
                        'id'       => 'ws_api_key',
                        'title'    => __( 'API access Key', 'wc_sendinblue' ),
                        'class'    => 'input-text regular-input',
                        'desc_tip' => '',
                        'type'     => 'text',
                        'desc'     => __('<br><p>Enter your Sendinblue API key from <a href="https://my.sendinblue.com/advanced/apikey/?utm_source=wordpress_plugin&utm_medium=plugin&utm_campaign=module_link" target="_blank">here</a></p>', 'wc_sendinblue' ),
                    ),

                    array( 'type' => 'sectionend' ),

                ),


        );
		$settings = array(
            'general' =>

                array(

                    array(
                        'title' => __( 'WooCommerce Sendinblue Integration', 'wc_sendinblue' ),
                        'type'  => 'title',
                        'desc'  => '
                    <div  style="/*background-color: #fff;border: 1px solid #ddd;padding: 12px 30px;*/">
                        <h4>'.__('You are currently logged in as :','wc_sendinblue').'</h4>
                        <div id="ws_info_wrap" style="padding-left: 24px;">
                        <p style="font-size: 14px;font-weight: 600;color: #0073aa;">'.$account_user_name.' -- '.$account_email.'</p>
                        <p style="font-size: 14px;font-weight: 600;color: #0073aa;">'.$account_email_credits['plan_type'].' -- '.$account_email_credits['credits'].' credits</p>
                        <p style="font-size: 14px;font-weight: 600;color: #0073aa;">'.$account_sms_credits['plan_type'].' -- '.$account_sms_credits['credits'].' credits</p>'.
                            sprintf(__('<i>To buy more credits, please click %s. </i>', 'wc_sendinblue'),
                                "<a target='_blank' href='https://www.sendinblue.com/pricing?utm_source=wordpress_plugin&utm_medium=plugin&utm_campaign=module_link' class='ws_refresh'> here</a>")
                        .'</div>
                        </div>',
                    ),

                    array(
                        'id'   => 'ws_statistics',
                        'type' => 'sectionend'
                    ),

                ),
            'subscribe' =>

                array(

                    array(
                        'title' => __( 'Sendinblue Newsletter Subscription Options', 'wc_sendinblue' ),
                        'type'  => 'title',
                    ),

                    array(
                        'id'       => 'ws_subscription_enabled',
                        'title'    => __( 'Enable/Disable', 'wc_sendinblue' ),
                        'desc'     => __( 'Enable Subscribe', 'wc_sendinblue' ),
                        'desc_tip' => __( 'If enabled, all customers will be added to a list after subscribe event occurs.', 'wc_sendinblue' ),
                        'default'  => 'yes',
                        'type'     => 'checkbox',
                    ),

                    array(
                        'id'       => 'ws_order_event',
                        'title'    => __( 'Subscribe Event', 'wc_sendinblue' ),
                        'desc_tip' => '',
                        'desc' => '',
                        'css'      => 'min-width:300px;',
                        'default'  => 'on-hold',
                        'type'     => 'select',
                        'options'  => array(
                            'on-hold'  => __( 'Order Created', 'wc_sendinblue' ),
                            /*'processing'   => __( 'Order Processing', 'wc_sendinblue' ),*/
                            'completed' => __( 'Order Completed', 'wc_sendinblue' ),
                        ),

                    ),

                    array(
                        'id'       => 'ws_sendinblue_list',
                        'title'    => __( 'Lists', 'wc_sendinblue' ),
                        'desc_tip' => '',
                        'css'      => 'min-width:300px;',
                        'type'     => 'select',
                        'default'  => '',
                        'options'     => WC_Sendinblue::$lists,
                        'desc'     => __('<p>All customers will be added to this list</p>', 'wc_sendinblue')
                    ),

                    array(
                        'id'       => 'ws_dopt_enabled',
                        'title'    => __( 'Double Opt-In', 'wc_sendinblue' ),
                        'desc'     => __( 'Enable Double Opt-In', 'wc_sendinblue' ),
                        'desc_tip' => __( 'If enabled, customers will receive an email prompting them to confirm their subscription to the list above.', 'wc_sendinblue' ),
                        'type'     => 'checkbox',
                        'default'  => 'no',
                    ),
                    array(
                        'id'       => 'ws_dopt_templates',
                        'title'    => __( 'Double Opt-In Template', 'wc_sendinblue' ),
                        'desc_tip' => '',
                        'css'      => 'min-width:300px;',
                        'type'     => 'select',
                        'default'  => '',
                        'options'     => WC_Sendinblue::$dopt_templates,
                        'desc'     => ''
                    ),
                    array(
                        'id'       => 'ws_opt_field',
                        'title'    => __( 'Display Opt-In Field', 'wc_sendinblue' ),
                        'desc' => __( 'Display an Opt-In Field on Checkout', 'wc_sendinblue' ),
                        'type'     => 'checkbox',
                        'default'  => 'no',
                        'desc_tip' => __( 'If enabled, customers will be presented with a "Opt-in" checkbox during checkout and will only be added to the list above if they opt-in.', 'wc_sendinblue' ),
                    ),
                    array(
                        'type' => 'sectionend',
                        /*'id' => 'ws_emails_custom'*/
                    ),
                    array(
                        'title' => '',
                        'type'  => 'title',
                    ),

                    array(
                        'id'       => 'ws_opt_field_label',
                        'title'    => __( 'Opt-In Field Label', 'wc_sendinblue' ),
                        'desc'    => __( '<p>Optional: customize the label displayed next to the opt-in checkbox</p>', 'wc_sendinblue' ),
                        'css'      => 'min-width:300px;',
                        'default'  => 'Add me to the newsletter',
                        'type'     => 'text',
                    ),

                    array(
                        'id'       => 'ws_opt_default_status',
                        'title'    => __( 'Opt-In Checkbox Default Status', 'wc_sendinblue' ),
                        'desc'    => __( '<p>The default state of the opt-in checkbox</p>', 'wc_sendinblue' ),
                        'css'      => 'min-width:300px;',
                        'default'  => 'Checked',
                        'type'     => 'select',
                        'options'  => array(
                            'checked'  => __( 'Checked', 'wc_sendinblue' ),
                            'unchecked'   => __( 'Unchecked', 'wc_sendinblue' ),
                        ),
                    ),
                    array(
                        'id'       => 'ws_opt_checkbox_location',
                        'title'    => __( 'Opt-In Checkbox Display Location', 'wc_sendinblue' ),
                        'desc'    => __( '<p>Where to display the opt-in checkbox on checkout page(under Billing info or Order info)</p>', 'wc_sendinblue' ),
                        'css'      => 'min-width:300px;',
                        'default'  => 'Billing',
                        'type'     => 'select',
                        'options'  => array(
                            'billing'  => __( 'Billing', 'wc_sendinblue' ),
                            'order'   => __( 'Order', 'wc_sendinblue' ),
                        ),
                    ),

                    array( 'type' => 'sectionend' ),

                ),
            'email_options' =>

                array(

                    array(
                        'title' => __( 'SendinBlue Email sending Options', 'wc_sendinblue' ),
                        'type'  => 'title',
                        'id' => 'ws_group'
                    ),
                    array(
                        'title'    => __( 'Enable/Disable', 'wc_sendinblue' ),
                        'id'       => 'ws_smtp_enable',
                        'default'  => 'yes',
                        'type'     => 'checkbox',
                        'desc' =>  'Enable SendinBlue to send Woocommerce emails',
                        'desc_tip' => 'Check this box if you want your automatic emails to be sent with SendinBlue SMTP (improved deliverability & statistics)'
                    ),

                    array(
                        'type' => 'sectionend',
                        'id' => 'ws_notification_activation'
                    ),
                    array(
                        'title' => '',
                        'type'  => 'title',
                    ),

                    array(
                        'title'    => __( 'Templates', 'wc_sendinblue' ),
                        'id'       => 'ws_email_templates_enable',
                        'default'  => 'no',
                        'class'			=> 'ws_sms_send',
                        'type'     => 'radio',
                        'options'  => array(
                            'no'  => __( 'Woocommerce', 'wc_sendinblue' ),
                            'yes' => __( 'SendinBlue', 'wc_sendinblue' )
                        ),
                        'desc_tip' =>  __('Choose SendinBlue if you want to replace default Woocommerce emails by custom emails saved in SendinBlue.', 'wc_sendinblue'),
                        'autoload' => false
                    ),

                    array(
                        'id' => 'ws_new_order_template',
                        'type' => 'fake_select',
                    ),
                    array(
                        'id' => 'ws_processing_order_template',
                        'type' => 'fake_select',
                    ),
                    array(
                        'id' => 'ws_refunded_order_template',
                        'type' => 'fake_select',
                    ),
                    array(
                        'id' => 'ws_cancelled_order_template',
                        'type' => 'fake_select',
                    ),
                    array(
                        'id' => 'ws_completed_order_template',
                        'type' => 'fake_select',
                    ),
                    array(
                        'id' => 'ws_new_account_template',
                        'type' => 'fake_select',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'ws_sendinblue_templates'
                    ),

                ),

			'sms_options' =>

				array(

                    array(
                        'title' => __( 'SendinBlue SMS Options', 'wc_sendinblue' ),
                        'type'  => 'title',
                        'id' => 'ws_group'
                    ),
                    array(
                        'id'       => 'ws_sms_enable',
                        'title'    => __( 'Enable/Disable', 'wc_sendinblue' ),
                        'type'     => 'checkbox',
                        'desc'     => __( 'Enable SendinBlue to send confirmation SMS', 'wc_sendinblue' ),
                        'default'  => 'no',
                        'desc_tip' => __( 'Check this box if you want to send SMS notification through SendinBlue', 'wc_sendinblue' ),
                    ),
                    array(
                        'id' => 'ws_sms_notification',
                        'type' => 'sectionend'
                    ),
                    //
                    array(
                        'id' => 'ws_sms_send_after',
                        'type' => 'fake_checkbox',
                    ),
                    array(
                        'id' => 'ws_sms_send_shipment',
                        'type' => 'fake_checkbox',
                    ),
                    array(
                        'id' => 'ws_sms_credits_notify',
                        'type' => 'fake_checkbox',
                    ),
                    //
					array(
						'title' => __( 'Send SMS after order confirmation', 'wc_sendinblue' ),
						'type'  => 'title',
					),
					array(
						'id'       => 'ws_sms_sender_after',
                        'class'			=> 'input-text regular-input ws_sms_sender',
						'title'    => __( 'Sender', 'wc_sendinblue' ),
						'desc_tip' => __( 'This field allows you to customize the SMS sender. The number of characters is limited to 11 alphanumeric characters. You can\'t configure your Sender with a phone number.', 'wc_sendinblue' ),
						'type'     => 'text',
                        'desc'     => __('Number of characters left:&nbsp;','wc_sendinblue'),
                        'custom_attributes'=> array('maxlength'=> '11', 'required'=>'true'),
					),
					array(
						'id'       => 'ws_sms_send_msg_desc_after',
                        'class' => 'wide-input ws_sms_send_msg_desc',
						'title'    => __( 'Message', 'wc_sendinblue' ),
						'desc_tip' => __( ' Create the content of your SMS with the limit of 160-character.Beyond 160 characters, it will be counted as a second SMS. Thus, if you write  SMS of 240 characters, it will be recorded using two SMS.', 'wc_sendinblue' ),
						'type'     => 'textarea',
                        'desc' => __( '<i>Number of SMS used: % &nbsp;&nbsp;&nbsp;Number of characters left: % &nbsp;&nbsp;&nbsp;Attention line break is counted as a single character. </i>', 'wc_sendinblue'),
                        'custom_attributes'=> array('required'=>'true'),
					),
                    array(
                        'id'       => '',
                        'class'       => 'ws_sms_send_test',
                        'title'    => __( 'Send a test SMS', 'wc_sendinblue' ),
                        'desc_tip' => __( 'The phone number should be in this form: 0033663309741 for this France mobile 06 63 30 97 41 (0033 is France prefix)', 'wc_sendinblue' ),
                        'type'     => 'text',
                        'desc' => __( '<br><p>Sending a test SMS will be deducted from your SMS credits.</p>', 'wc_sendinblue' ),
                    ),
					array( 'type' => 'sectionend' ),

                    //// Send a SMS after order shipment
                    array(
                        'title' => __( 'Send a SMS after order shipment', 'wc_sendinblue' ),
                        'type'  => 'title'
                    ),
                    array(
                        'id'       => 'ws_sms_sender_shipment',
                        'class'			=> 'input-text regular-input ws_sms_sender',
                        'title'    => __( 'Sender', 'wc_sendinblue' ),
                        'desc_tip' => __( 'This field allows you to customize the SMS sender. The number of characters is limited to 11 alphanumeric characters. You can\'t configure your Sender with a phone number.', 'wc_sendinblue' ),
                        'type'     => 'text',
                        'desc'     => __('Number of characters left:&nbsp;','wc_sendinblue'),
                        'custom_attributes'=> array('maxlength'=> '11','required'=>'true'),
                    ),
                    array(
                        'id'       => 'ws_sms_send_msg_desc_shipment',
                        'class' => 'wide-input ws_sms_send_msg_desc',
                        'title'    => __( 'Message', 'wc_sendinblue' ),
                        'desc_tip' => __( ' Create the content of your SMS with the limit of 160-character.Beyond 160 characters, it will be counted as a second SMS. Thus, if you write  SMS of 240 characters, it will be recorded using two SMS.', 'wc_sendinblue' ),
                        'type'     => 'textarea',
                        'desc' => __( '<i>Number of SMS used: % &nbsp;&nbsp;&nbsp;Number of characters left: % &nbsp;&nbsp;&nbsp;Attention line break is counted as a single character. </i>', 'wc_sendinblue'),
                        'custom_attributes'=> array('required'=>'true'),
                    ),
                    array(
                        'id'       => '',
                        'class'       => 'ws_sms_send_test',
                        'title'    => __( 'Send a test SMS', 'wc_sendinblue' ),
                        'desc_tip' => __( 'The phone number should be in this form: 0033663309741 for this France mobile 06 63 30 97 41 (0033 is France prefix)', 'wc_sendinblue' ),
                        'type'     => 'text',
                        'desc' => __( '<br><p>Sending a test SMS will be deducted from your SMS credits.</p>', 'wc_sendinblue' ),
                    ),
                    array( 'type' => 'sectionend' ),
                    // Notification
                    array(
                        'title' => __( 'You want to be notified by e-mail when you do not have enough SMS credits?', 'wc_sendinblue' ),
                        'type'  => 'title'
                    ),
                    array(
                        'id'       => 'ws_sms_credits_notify_email',
                        'class'			=> 'input-text regular-input ws_sms_credits',
                        'title'    => __( 'Email', 'wc_sendinblue' ),
                        'type'     => 'email',
                        'custom_attributes'=> array('required'=>'true'),
                    ),
                    array(
                        'id'       => 'ws_sms_credits_limit',
                        'class'			=> 'input-text regular-input ws_sms_credits',
                        'title'    => __( 'Limit', 'wc_sendinblue' ),
                        'desc_tip' => __( 'Alert threshold for remaining credits', 'wc_sendinblue' ),
                        'type'     => 'number',
                        'custom_attributes'=> array('required'=>'true'),
                    ),
                    array( 'type' => 'sectionend' ),

				),

            'campaigns' =>

                array(
                    ////  Send a SMS campaign

                    array(
                        'title' => __( 'SMS campaign', 'wc_sendinblue' ),
                        'type'  => 'title',
                    ),
                    array(
                        'title'    => __( 'To', 'wc_sendinblue' ),
                        'id'       => 'ws_sms_send_to',
                        'default'  => 'single',
                        'class'			=> 'ws_sms_send_to',
                        'type'     => 'radio',
                        'options'  => array(
                            'single'       => __( 'A single contact', 'wc_sendinblue' ),
                            'all' => __( 'All my WordPress customers', 'wc_sendinblue' ),
                            'only'       => __( 'Only subscribed customers', 'wc_sendinblue' ),
                        ),
                        'desc_tip' =>  true,
                        'autoload' => false
                    ),

                    array( 'type' => 'sectionend' ),
                    array(
                        'type'     => 'title',
                        'desc' => '',
                    ),
                    array(
                        'id'       => 'ws_sms_single_campaign',
                        'class'			=> 'input-text regular-input ws_sms_single',
                        'title'    => __( 'Phone number of contact', 'wc_sendinblue' ),
                        'desc_tip' => __( ' The phone number should be in this form: 0033663309741 for this France mobile 06 63 30 97 41 (0033 is France prefix)', 'wc_sendinblue' ),
                        'type'     => 'text',
                        'custom_attributes'=> array('maxlength'=> '17',/*'required'=>'true'*/),
                    ),
                    array(
                        'id'       => 'ws_sms_sender_campaign',
                        'class'			=> 'input-text regular-input ws_sms_sender',
                        'title'    => __( 'Sender', 'wc_sendinblue' ),
                        'desc_tip' => __( 'This field allows you to customize the SMS sender. The number of characters is limited to 11 alphanumeric characters. You can\'t configure your Sender with a phone number.', 'wc_sendinblue' ),
                        'type'     => 'text',
                        'desc'     => __('Number of characters left:&nbsp;','wc_sendinblue'),
                        'custom_attributes'=> array('maxlength'=> '11',/*'required'=>'true'*/),
                    ),
                    array(
                        'id'       => 'ws_sms_campaign_message',
                        'class' => 'wide-input ws_sms_send_msg_desc',
                        'title'    => __( 'Message', 'wc_sendinblue' ),
                        'desc_tip' => __( ' Create the content of your SMS with the limit of 160-character.Beyond 160 characters, it will be counted as a second SMS. Thus, if you write  SMS of 240 characters, it will be recorded using two SMS.', 'wc_sendinblue' ),
                        'type'     => 'textarea',
                        'desc' => __( '<i>Number of SMS used: % &nbsp;&nbsp;&nbsp;Number of characters left: % &nbsp;&nbsp;&nbsp;Attention line break is counted as a single character. </i>', 'wc_sendinblue'),
                        'custom_attributes'=> array('required'=>'true'),
                    ),
                    array(
                        'id'       => '',
                        'class'       => 'ws_sms_send_test',
                        'title'    => __( 'Send a test SMS', 'wc_sendinblue' ),
                        'desc_tip' => __( 'The phone number should be in this form: 0033663309741 for this France mobile 06 63 30 97 41 (0033 is France prefix)', 'wc_sendinblue' ),
                        'type'     => 'text',
                        'desc' => __( '<br><p>Sending a test SMS will be deducted from your SMS credits.</p>', 'wc_sendinblue' ),
                    ),
                    array(
                        'id' => 'ws_sms_campaign_send',
                        'type' => 'sectionend'
                    ),
                ),

		);

        if( isset(WC_Sendinblue::$access_key) && WC_Sendinblue::$access_key != '') {
            $logBtn = '<div style="padding-left: 24px;"><a href="' . esc_url(add_query_arg('ws_action', 'logout')) . '" class = "button-primary" style="padding: 0 20px; ">' . __('Logout', 'wc_sendinblue') . '</a></div>';// sprintf( __('If you want logout, please click %s. ', 'wc_sendinblue'),'<a href="'. esc_url(add_query_arg('sib_action', 'logout')).'">'. __('Logout','wc_sendinblue') .'</a>');
        }else {
            //login
            $logBtn = '<div><img id="ws_login_gif" src="'.WC()->plugin_url()."/assets/images/select2-spinner.gif".
                '" style="margin-right: 12px;vertical-align: middle;display:none;"><a href="javascript:void(0);" class = "ws_api_key_active button-primary" style="padding: 0 20px; margin-top: 24px;">' . __('Login', 'wc_sendinblue') . '</a></div>';
        }
        $this->wc_enqueue_js("

                var LOG_BTN ='". $logBtn ."';

                jQuery('#ws_api_key').next('span').after(LOG_BTN);
                jQuery('#ws_info_wrap').after( LOG_BTN );

                jQuery('#ws_dopt_enabled').change(function(){
					jQuery( this ).closest( 'tr' ).next( 'tr').hide('fast');

					if ( jQuery(this).prop('checked') == true ) {
						jQuery( this ).closest( 'tr' ).next( 'tr').show('fast');
					}
					else {
						jQuery( this ).closest( 'tr' ).next( 'tr').hide('fast');
					}

				}).change();
                jQuery('#ws_opt_field').change(function(){
					jQuery( this ).closest( 'table' ).nextAll( 'table').hide('fast');

					if ( jQuery(this).prop('checked') == true ) {
						jQuery( this ).closest( 'table' ).nextAll( 'table').show('fast');
					}
					else {
						jQuery( this ).closest( 'table' ).nextAll( 'table').hide('fast');
					}

				}).change();
			");

		$current_section = isset( $GLOBALS['current_section'] ) ? $GLOBALS['current_section'] : 'general';


        if( isset(WC_Sendinblue::$access_key) && WC_Sendinblue::$access_key != '') {
            if($current_section == ''){
                $GLOBALS['hide_save_button'] = true;
            }
            return isset( $settings[ $current_section ] ) ?  $settings[ $current_section ] : $settings['general'];
        }else{
            $GLOBALS['hide_save_button'] = true;
            return $settings_init['general'];
        }
	}

    private function wc_enqueue_js( $code ) {
        if ( function_exists( 'wc_enqueue_js' ) ) {
            wc_enqueue_js( $code );
        }
    }
    /**
     * Statistics on general options
     */
    public function ws_statistics(){
        ?>
        <h3><?php _e('Statistics', 'wc_sendinblue'); ?></h3>
        <div class="ws_date" ><input id="ws_date_picker" name="ws_date_picker">
            <img id="ws_date_gif" src="<?php echo WC()->plugin_url()."/assets/images/select2-spinner.gif"; ?>" style="margin-right: 12px;vertical-align: middle;display: none;">
        </div>
        <table id="ws_statistics_table" class="wc_shipping widefat wp-list-table" cellspacing="0">
            <thead>
            <tr>
                <th class="sort">&nbsp;</th>
                <th class=""><?php _e('Email templates','wc_sendinblue');?></th>
                <th class=""><?php _e('Sent','wc_sendinblue');?></th>
                <th class=""><?php _e('Deliverability','wc_sendinblue');?></th>
                <th class=""><?php _e('Open rate','wc_sendinblue');?></th>
                <th class=""><?php _e('Click rate','wc_sendinblue');?></th>

            </tr>
            </thead>
            <tbody class="ui-sortable">
            <?php
            //error_log(print_r(WC_Sendinblue::$statistics,true));
            foreach(WC_Sendinblue::$statistics as $statistic){ ?>
            <tr id="<?php echo str_replace(' ', '-', $statistic['name']);?>">
                <td width="1%" class="sort ui-sortable-handle">
                    <input type="hidden" name="method_order[flat_rate]" value="">
                </td>
                <td class=""><?php echo $statistic['name'];?></td>
                <td class=""><?php echo $statistic['sent'];?></td>
                <td class=""><?php echo $statistic['delivered'];?></td>
                <td class=""><?php echo $statistic['open_rate'];?></td>
                <td class=""><?php echo $statistic['click_rate'];?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php
    }
    /**
     * Notification settings on SMS options section
     */
    public function ws_sms_notification(){
        ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for=""><?php _e('Notification Activation', 'wc_sendinblue'); ?></label>
                </th>
                <td class="forminp">
                    <table class="form-ws-table">
                        <tr>
                            <td><label for="ws_sms_send_after"><input type="checkbox" name="ws_sms_send_after" id="ws_sms_send_after" value="1" <?php checked( WC_Admin_Settings::get_option('ws_sms_send_after','no'), 'yes'); ?>><?php _e('Order confirmation', 'wc_sendinblue'); ?></label></td>
                            <td><label for="ws_sms_send_shipment"><input type="checkbox" name="ws_sms_send_shipment" id="ws_sms_send_shipment" value="1" <?php checked( WC_Admin_Settings::get_option('ws_sms_send_shipment','no'), 'yes'); ?>><?php _e('Order shipment', 'wc_sendinblue'); ?></label></td>
                            <td><label for="ws_sms_credits_notify"><input type="checkbox" name="ws_sms_credits_notify" id="ws_sms_credits_notify" value="1" <?php checked( WC_Admin_Settings::get_option('ws_sms_credits_notify','no'), 'yes'); ?>><?php _e('Not enough SMS credits', 'wc_sendinblue'); ?></label></td>
                        </tr>
                    </table>
                </td>
            </tr>
    <?php
    }
    /**
     * Notification settings on Email options section
     */
    public function ws_notification_activation(){
        ?>
        <table class="form-table">
            <tbody><tr valign="top">
                <th scope="row" class="titledesc">
                    <label for=""><?php _e('Notification Activation', 'wc_sendinblue'); ?></label>
                </th>
                <td class="forminp">
                    <table class="form-ws-table">
                        <tr>
                            <td><label for="WC_Email_New_Order"><input type="checkbox" name="WC_Email_New_Order" id="WC_Email_New_Order" value="1" <?php checked( WC_Admin_Settings::get_option('WC_Email_New_Order','no'), 'yes'); ?>><?php _e('New order', 'wc_sendinblue'); ?></label></td>
                            <td><label for="WC_Email_Customer_Processing_Order"><input type="checkbox" name="WC_Email_Customer_Processing_Order" id="WC_Email_Customer_Processing_Order" value="1" <?php checked( WC_Admin_Settings::get_option('WC_Email_Customer_Processing_Order','no'), 'yes'); ?>><?php _e('Processing order', 'wc_sendinblue'); ?></label></td>
                            <td><label for="WC_Email_Customer_Refunded_Order"><input type="checkbox" name="WC_Email_Customer_Refunded_Order" id="WC_Email_Customer_Refunded_Order" value="1" <?php checked( WC_Admin_Settings::get_option('WC_Email_Customer_Refunded_Order','no'), 'yes'); ?>><?php _e('Refunded order', 'wc_sendinblue'); ?></label></td>
                        </tr>
                        <tr>
                            <td><label for="WC_Email_Cancelled_Order"><input type="checkbox" name="WC_Email_Cancelled_Order" id="WC_Email_Cancelled_Order" value="1" <?php checked( WC_Admin_Settings::get_option('WC_Email_Cancelled_Order','no'), 'yes'); ?>><?php _e('Canceled order', 'wc_sendinblue'); ?></label></td>
                            <td><label for="WC_Email_Customer_Completed_Order"><input type="checkbox" name="WC_Email_Customer_Completed_Order" id="WC_Email_Customer_Completed_Order" value="1" <?php checked( WC_Admin_Settings::get_option('WC_Email_Customer_Completed_Order','no'), 'yes'); ?>><?php _e('Completed order', 'wc_sendinblue'); ?></label></td>
                            <td><label for="WC_Email_Customer_New_Account"><input type="checkbox" name="WC_Email_Customer_New_Account" id="WC_Email_Customer_New_Account" value="1" <?php checked( WC_Admin_Settings::get_option('WC_Email_Customer_New_Account','no'), 'yes'); ?>><?php _e('New account', 'wc_sendinblue'); ?></label></td>
                        </tr>
                    </table>
                </td>
            </tr>
            </tbody>
        </table>
    <?php
    }

    /**
     * SendinBlue templates on Email options section
     */
    public function ws_sendinblue_templates(){
        $email_templates = array(
            'ws_new_order_template' => '',
            'ws_processing_order_template' => '',
            'ws_refunded_order_template' => '',
            'ws_cancelled_order_template' => '',
            'ws_completed_order_template' => '',
            'ws_new_account_template' => '',
        );
        $templates = WC_Sendinblue::$templates;
        foreach($email_templates as $key=>$content){
            $option_value = WC_Admin_Settings::get_option($key,'0');
            $email_templates[$key] = '<select name="'.$key.'" id="'.$key.'">';
            $email_templates[$key] .= '<option value="0" > - Choose Template - </option>';
            foreach($templates as $id=>$val){
                $email_templates[$key] .= '<option value="'.$id.'" '.selected($option_value, $id ,false).'>'.$val['name'].'</option>';
            }
            $email_templates[$key] .='</select>';
        }
        ?>

        <table class="form-table">
            <tbody><tr valign="top">
                <th scope="row" class="titledesc">
                    <label for=""><?php _e('SendinBlue Templates', 'wc_sendinblue'); ?></label>
                </th>
                <td class="forminp">
                    <table class="form-ws-table">
                        <tr>
                            <td><label for="ws_new_order_template"><?php _e('New order', 'wc_sendinblue'); ?></label></td><td><?php echo $email_templates['ws_new_order_template']; ?></td>
                            <td><label for="ws_processing_order_template"><?php _e('Processing order', 'wc_sendinblue'); ?></label></td><td><?php echo $email_templates['ws_processing_order_template']; ?></td>
                        </tr>
                        <tr>
                            <td><label for="ws_refunded_order_template"><?php _e('Refunded order', 'wc_sendinblue'); ?></label></td><td><?php echo $email_templates['ws_refunded_order_template']; ?></td>
                            <td><label for="ws_cancelled_order_template"><?php _e('Cancelled order', 'wc_sendinblue'); ?></label></td><td><?php echo $email_templates['ws_cancelled_order_template']; ?></td>
                        </tr>
                        <tr>
                            <td><label for="ws_completed_order_template"><?php _e('Completed order', 'wc_sendinblue'); ?></label></td><td><?php echo $email_templates['ws_completed_order_template']; ?></td>
                            <td><label for="ws_new_account_template"><?php _e('New account', 'wc_sendinblue'); ?></label></td><td><?php echo $email_templates['ws_new_account_template']; ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
            </tbody>
        </table>
    <?php
    }

    /**
     * Add following contacts part on campaign section
     */
    public function ws_email_campaign_follow_contacts(){
        ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                </th>
                <td class="forminp" style="padding-top: 0px;">
                    <textarea name="ws_email_campaign_following_contacts" id="ws_email_campaign_following_contacts" style="" class="ws_contact_textarea wide-input" placeholder="example1@email.com,example2@email.com, etc."></textarea>
                    <p class="description"><?php echo sprintf(__('Please copy/paste comma-separated email addresses.<br>
                        If you want to send a campaign to more than 1000 email addresses, we advise you to send your campaign directly from %s.', 'wc_sendinblue'),
                            '<a href="">SendinBlue</a>');?></p>
                </td>
            </tr>

    <?php
    }
    /**
     * Add email send part on campaign section
     */
    public function ws_email_campaign_send(){
        ?>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for=""><?php _e('Message', 'wc_sendinblue'); ?></label>
                </th>
                <td class="forminp">
                    <textarea name="ws_email_campaign_message" id="ws_email_campaign_message" style="" class="ws_textarea wide-input" placeholder=""></textarea>
                    <p class="description"><?php _e('> You can insert HTML or simple text in the text input.<br>
                        > You must have UNSUBSCRIBE tag wihin your html or a variable {} in your footer to create a valid campaign.<br>
                        It\'s important that the user is able to unsubscribe from your list.', 'wc_sendinblue');?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for=""><?php _e('Send a test email', 'wc_sendinblue'); ?></label>
                </th>
                <td class="forminp">
                    <table class="form-ws-table">
                        <tr>
                            <td><input type="email" name="" id="" class="input-text regular-input"/><a href="javascript:void(0);" class="button"><?php _e('Send', 'wc_sendinblue'); ?></a></td>
                        </tr>
                        <tr>
                            <td>
                                <img id="ws_login_gif_email" src="<?php echo WC()->plugin_url().'/assets/images/select2-spinner.gif';?>" style="margin-right: 12px;vertical-align: middle;display: none;">
                                <input name="" id="ws_email_send_campaign_btn" class="button-secondary" type="button" value="<?php _e('Send my campaign', 'wc_sendinblue'); ?>">
                                <p class="description"><?php printf( __('You can follow sending progress and statistics from your %s account', 'wc_sendinblue'),'<a href="">SendinBlue</a>'); ?></p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            </tbody>
        </table>
    <?php
    }
    /**
     * Add SMS send part on campaign section
     */
    public function ws_sms_campaign_send(){

        ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                </th>
                <td class="forminp">
                    <table class="form-ws-table">
                        <tr>
                            <td>
                                <img id="ws_login_gif_sms" src="<?php echo WC()->plugin_url().'/assets/images/select2-spinner.gif';?>" style="margin-right: 12px;vertical-align: middle;display: none;">
                                <input name="" id="ws_sms_send_campaign_btn" class="button-secondary" type="button" value="<?php _e('Send my campaign', 'wc_sendinblue'); ?>">
                                <p class="description"><?php printf( __('You can follow sending progress and statistics from your %s account', 'wc_sendinblue'),'<a href="">SendinBlue</a>'); ?></p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

    <?php
    }
}

// setup settings
return new WC_Sendinblue_Settings();
