<?php

class WC_Sendinblue {

    public static $customizations;
    /**
     * Access key
     */
    public static $access_key;
    /**
     * Sendinblue lists
     */
    public static $lists;
    /**
     * Sendinblue templates
     */
    public static $templates;
    /**
     * Sendinblue Double opt-in templates
     */
    public static $dopt_templates;
    /**
     * Sendinblue statistics
     */
    public static $statistics;
    /**
     * Sendinblue account info
     */
    public static $account_info;
    /**
     * Error type
     */
    public static $ws_error_type;
    /**
     * Wordpress senders
     */
    public static $senders;
    /**
     * Request url of sendinblue api
     */
    const sendinblue_api_url = 'https://api.sendinblue.com/v2.0';

    public function __construct()
    {

    }

    /**
     * Initialize of setting values for admin user
     */
    public static function init(){

        self::$customizations = get_option('wc_sendinblue_settings', array());

        $general_settings = get_option('ws_main_option');
        self::$access_key = isset($general_settings['access_key']) ? $general_settings['access_key'] : '';

        $error_settings = get_option('ws_error_type', array());
        self::$ws_error_type = isset($error_settings['error_type']) ? $error_settings['error_type'] : '';
        delete_option('ws_error_type');

        if (!class_exists('Mailin'))
            require_once 'mailin.php';

        //to connect and get account details and lists
        if (self::$access_key != '') {

            try {
                $account = new Mailin(self::sendinblue_api_url, self::$access_key);
            } catch (Exception $e) {
                $account = null;
                self::$access_key = null;
                update_option('ws_main_option', self::$access_key);
            }

            // get lists
            self::$lists = get_transient( 'wswcsblist_' . md5( self::$access_key ) );
            if ( !self::$lists ) {

                $data = array();
                self::$lists = $account->get_lists($data);
                $list_data = array();

                foreach (self::$lists['data'] as $list) {
                    $list_data[$list['id']] = $list['name'];
                }
                self::$lists = $list_data;

                if ( sizeof( self::$lists ) > 0 )
                    set_transient( 'wswcsblist_' . md5( self::$access_key ), self::$lists, 60 * 60 * 1 );

            }

            // get templates
            self::$templates = get_transient( 'wstemplate_' . md5( self::$access_key ) );

            if ( !self::$templates ) {

                $data = array(
                    'type' => 'template',
                    'status' => 'temp_active'
                );
                $templates = $account->get_campaigns_v2($data);
                $template_data = array();

                if($templates['code'] == 'success') {

                    foreach ($templates['data']['campaign_records'] as $template) {
                        $template_data[$template['id']] = array(
                            'name' => $template['campaign_name'],
                            'content' => $template['html_content'],
                        );

                    }
                }
                self::$templates = $template_data;

                if ( sizeof( self::$templates ) > 0 ) {
                    set_transient('wstemplate_' . md5(self::$access_key), self::$templates, 60 * 60 * 1);
                }

                update_option('ws_templates',$template_data);

            }

            self::$dopt_templates = get_transient( 'wsdopttemplate_' . md5( self::$access_key ) );
            if( !self::$dopt_templates ){

                $dopt_template = array('0'=>'Default');
                // for double opt-in
                foreach(self::$templates as $id=>$template) {
                    if (strpos($template['content'], '[DOUBLEOPTIN]') !== false)
                        $dopt_template[$id] = $template['name'];
                }
                self::$dopt_templates = $dopt_template;
                if ( sizeof( self::$dopt_templates ) > 0 ) {
                    set_transient('wsdopttemplate_' . md5(self::$access_key), self::$dopt_templates, 60 * 60 * 1);
                }
            }


            // get account's info
            self::$account_info = get_transient( 'wswcsbcredit_' . md5( self::$access_key ) );
            if ( !self::$account_info ) {

                self::$account_info = array();

                $account_info = $account->get_account();
                $count = count($account_info['data']);
                $account_data = array();
                foreach ($account_info['data'] as $key=>$info) {
                    if (isset($info['plan_type']) && isset($info['credits'])) {
                        $account_data[$key]['plan_type'] = $info['plan_type'];
                        $account_data[$key]['credits'] = $info['credits'];
                    }
                }

                self::$account_info['SMS_credits'] = $account_data[1];
                self::$account_info['email_credits'] = $account_data[0];
                self::$account_info['user_name'] = $account_info['data'][$count - 1]['first_name'] . ' ' . $account_info['data'][$count - 1]['last_name'];
                self::$account_info['email'] = $account_info['data'][$count - 1]['email'];

                $settings = array(
                    'access_key' => self::$access_key,
                    'account_email' => self::$account_info['email']
                );
                update_option('ws_main_option', $settings);

                if ( sizeof( self::$lists ) > 0 )
                    set_transient( 'wswcsbcredit_' . md5( self::$access_key ), self::$account_info, 60 * 60 * 1 );
            }

            // get statistics
            self::get_templates(); // option - ws_email_templates
            self::$statistics = array();
            $startDate = $endDate = date("Y-m-d");  // format: "Y-m-d";

            if((isset($_GET['section']) && $_GET['section'] == '') || !isset($_GET['section'])) {
                self::get_statistics($account, $startDate, $endDate);
            }

            // get senders from wp
            $blogusers = get_users( 'role=Administrator' );
            $senders = array('-1'=>'- Select a sender -');
            foreach($blogusers as $user){
                $senders[$user->user_nicename] = $user->user_email;
            }
            self::$senders = $senders;

        }
    }

    /**
     * Get current SMS credits
     * @return credits
    */
    public static function ws_get_credits(){

        $general_settings = get_option('ws_main_option');
        $account = new Mailin(self::sendinblue_api_url, $general_settings['access_key']);

        $account_info = $account->get_account();
        $account_data = array();
        foreach ($account_info['data'] as $key=>$info) {
            if (isset($info['plan_type']) && isset($info['credits'])) {
                $account_data[$key]['plan_type'] = $info['plan_type'];
                $account_data[$key]['credits'] = $info['credits'];
            }
        }
        $sms_info = $account_data[1];
        $email_info = $account_data[0];

        return $sms_info['credits'];
    }

    /**
     * Get SendinBlue email templates regarding to settings
     */
    static function get_templates(){
        $customizations = get_option('wc_sendinblue_settings', array());
        $ws_email_templates = array(
            'New Order' => isset($customizations['ws_new_order_template']) ? $customizations['ws_new_order_template'] : '0', // template id
            'Processing Order' => isset($customizations['ws_processing_order_template']) ? $customizations['ws_processing_order_template'] : '0',
            'Refunded Order' => isset($customizations['ws_refunded_order_template']) ? $customizations['ws_refunded_order_template'] : '0',
            'Cancelled Order' => isset($customizations['ws_cancelled_order_template']) ? $customizations['ws_cancelled_order_template'] : '0',
            'Completed Order' => isset($customizations['ws_completed_order_template']) ? $customizations['ws_completed_order_template'] : '0',
            'New Account' => isset($customizations['ws_new_account_template']) ? $customizations['ws_new_account_template'] : '0',
        );
        update_option('ws_email_templates',$ws_email_templates);
    }
    /**
     * Get statistics regarding to order's status
     */
    public static function get_statistics($account, $startDate, $endDate){

        $ws_notification_activation = get_option('ws_notification_activation',array());
        $statistics = array();

        $customization = get_option('wc_sendinblue_settings', array());
        if(!isset($customization['ws_smtp_enable']) || $customization['ws_smtp_enable'] != 'yes'){
            return array();
        }

        foreach($ws_notification_activation as $template_name){
            /*if($template_id == '0')
                continue;*/

            $data = array(
                "aggregate" => 0,
                "tag" => $template_name,
                "start_date" => $startDate,
                "end_date" => $endDate,
            );

            $result = $account->get_statistics($data);
            $sent = $delivered = $open = $click = 0;
            foreach($result['data'] as $data){
                $sent += isset($data['requests']) ? $data['requests'] : 0;
                $delivered += isset($data['delivered']) ? $data['delivered'] : 0;
                $open += isset($data['unique_opens']) ? $data['unique_opens'] : 0;//opens
                $click += isset($data['unique_clicks']) ? $data['unique_clicks'] : 0;//clicks
            }
            $statistics[$template_name] = array(
                'name' => $template_name,
                'sent' => $sent,
                'delivered' => $sent != 0 ? round($delivered/$sent*100,2)."%" : "0%",
                'open_rate' => $sent != 0 ? round($open/$sent*100,2)."%" : "0%",
                'click_rate' => $sent != 0 ? round($click/$sent*100,2)."%" : "0%",
            );
        }
        self::$statistics = $statistics;

        return $statistics;
    }
    /**
     * create_subscriber function.
     *
     * @access public
     * @param string $email
     * @param string $list_id
     * @param string $name
     * @return void
     */
    public function create_subscriber($email, $list_id, $info)
    {
        $general_settings = get_option('ws_main_option', array());
        if (!class_exists('Mailin'))
            require_once 'mailin.php';
        try {

            $account = new Mailin(self::sendinblue_api_url, $general_settings['access_key']);

            $data = array();
            $attribute_key = implode('|', array_keys($info));
            $attribute_val = implode('|', array_values($info));
            $ip = self::get_the_user_ip();
            $data['attributes_name'] = $attribute_key;
            $data['attributes_value'] = $attribute_val;
            $data['category'] = '';
            $data['email'] = $email;
            $data['listid'] = $list_id;
            $data['blacklisted'] = 0;
            $data['blacklisted_sms'] = 0;
            $data['source'] = 'Wordpress Woocommerce';
            $data['ip'] = $ip;
            $ret = $account->updateUser($data);

            return $ret;
        } catch (Exception $e) {
            //Authorization is invalid
            //if ($e->type === 'UnauthorizedError')
                //$this->deauthorize();
        }
    } // End create_subscriber()
    /**
     * get user ip
     */
    static function get_the_user_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
    /**
     * Subscribe process for submit on confirmation email
     */
    public static function subscribe()
    {
        $site_domain = str_replace('https://', '', home_url());
        $site_domain = str_replace('http://', '', $site_domain);
        $general_settings = get_option('ws_main_option', array());
        if (!class_exists('Mailin'))
            require_once 'mailin.php';

        $mailin = new Mailin(self::sendinblue_api_url, $general_settings['access_key']);
        $code = esc_attr($_GET['code']);
        $list_id = intval($_GET['li']);

        $contact_info = SIB_Model_Contact::get_data_by_code($code);

        if ($contact_info != false) {
            $email = $contact_info['email'];
            $data = array(
                'email' => $email
            );
            $response = $mailin->get_user($data);

            if ($response['code'] == 'success') {
                $listid = $response['data']['listid'];
                if (!isset($listid) || !is_array($listid)) {
                    $listid = array();
                }
            } else {
                $listid = array();
            }

            array_push($listid, $list_id);
            $attributes = maybe_unserialize($contact_info['info']);

            $data = array();
            $attribute_key = implode('|', array_keys($attributes));
            $attribute_val = implode('|', array_values($attributes));
            $ip = self::get_the_user_ip();
            $data['attributes_name'] = $attribute_key;
            $data['attributes_value'] = $attribute_val;
            $data['category'] = '';
            $data['email'] = $email;
            $data['listid'] = $list_id;
            $data['blacklisted'] = 0;
            $data['blacklisted_sms'] = 0;
            $data['source'] = 'Wordpress Woocommerce';
            $data['ip'] = $ip;
            $ret = $mailin->updateUser($data);
        }
        ?>
        <body style="margin:0; padding:0;">
        <table style="background-color:#ffffff" cellpadding="0" cellspacing="0" border="0" width="100%">
            <tbody>
            <tr style="border-collapse:collapse;">
                <td style="border-collapse:collapse;" align="center">
                    <table cellpadding="0" cellspacing="0" border="0" width="540">
                        <tbody>
                        <tr>
                            <td style="line-height:0; font-size:0;" height="20"></td>
                        </tr>
                        </tbody>
                    </table>
                    <table cellpadding="0" cellspacing="0" border="0" width="540">
                        <tbody>
                        <tr>
                            <td style="line-height:0; font-size:0;" height="20">
                                <div
                                    style="font-family:arial,sans-serif; color:#61a6f3; font-size:20px; font-weight:bold; line-height:28px;">
                                    <?php _e('Thank you for subscribing', 'wc_sendinblue'); ?></div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <table cellpadding="0" cellspacing="0" border="0" width="540">
                        <tbody>
                        <tr>
                            <td style="line-height:0; font-size:0;" height="20"></td>
                        </tr>
                        </tbody>
                    </table>
                    <table cellpadding="0" cellspacing="0" border="0" width="540">
                        <tbody>
                        <tr>
                            <td align="left">

                                <div
                                    style="font-family:arial,sans-serif; font-size:14px; margin:0; line-height:24px; color:#555555;">
                                    <br>
                                    <?php echo __('You have just subscribed to the newsletter of ', 'wc_sendinblue') . $site_domain . ' .'; ?>
                                    <br><br>
                                    <?php _e('-SendinBlue', 'wc_sendinblue'); ?></div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <table cellpadding="0" cellspacing="0" border="0" width="540">
                        <tbody>
                        <tr>
                            <td style="line-height:0; font-size:0;" height="20">
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            </tbody>
        </table>
        </body>
        <?php
        exit;
    }

    /**
     * logout process
     * @return void
     */
    public static function logout()
    {
        $setting = array();
        update_option('ws_main_option', $setting);

        $home_settings = array(
            'activate_email' => 'no'
        );
        update_option('ws_home_option', $home_settings);

        delete_option('ws_credits_notice');
        update_option('wc_sendinblue_settings', $setting);
        update_option('ws_email_templates',$setting);
        update_option('ws_templates',$setting);

        // remove transients
        delete_transient('wswcsbcredit_' . md5( WC_Sendinblue::$access_key ));
        delete_transient('wstemplate_' . md5( WC_Sendinblue::$access_key ));
        delete_transient('wswcsblist_' . md5( WC_Sendinblue::$access_key ));

        wp_redirect(add_query_arg('page', 'wc-settings&tab=sendinblue', admin_url('admin.php')));
        exit;
    }
    /**
     * ajax module for validation of API access key
     *
     * @options :
     *  ws_main_option
     *  ws_token_store
     *  ws_error_type
     */
    public static function ajax_validation_process()
    {
        if (!class_exists('Mailin'))
            require_once 'mailin.php';

        $access_key = trim($_POST['access_key']);

        try {
            $mailin = new Mailin(self::sendinblue_api_url, $access_key);
        }catch( Exception $e ){
            if( $e->getMessage() == 'Mailin requires CURL module' ) {
                $ws_error_type = __('Please install curl on site to use sendinblue plugin.', 'wc_sendinblue');
            }else{
                $ws_error_type = __('Curl error.', 'wc_sendinblue');
            }
            $settings = array(
                'error_type' => $ws_error_type,
            );
            update_option('ws_error_type', $settings);
            die();
        }

        $response = $mailin->get_access_tokens();
        if(is_array($response)) {
            if($response['code'] == 'success') {

                // store api info
                $settings = array(
                    'access_key' => $access_key,
                );
                update_option('ws_main_option', $settings);
                // Create woocommerce attributes on SendinBlue
                $data = array(
                    "type" => "transactional",
                    "data" => array('ORDER_ID' => 'ID', 'ORDER_DATE' => 'DATE', 'ORDER_PRICE' => 'NUMBER')
                );
                $mailin->create_attribute($data);

                $mailin->partnerWordpress();

                echo 'success';
            }
            else {
                $settings = array(
                    'error_type' => __('Please input correct information.', 'wc_sendinblue'),
                );
                update_option('ws_error_type', $settings);
            }
        } else {
            echo 'fail';
        }
        die();
    }
} 