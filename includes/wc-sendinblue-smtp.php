<?php

class WC_Sendinblue_SMTP {

    function __construct(){

    }

    /**
     * Send email campaign
     */
    public function getContacts($type){
        //
    }
    public function sendEmailCampaign($info){
        $type = $info['type'];
        $contacts = $this->getContacts($type);
        foreach($contacts as $contact){
            // return bool
            if(!self::send_email('woo-campaign', $contact, $info['subject'], $info['from_name'], $info['sender']))
                return false;
        }
        return true;
    }
    /* end of send email campaign */

    /**
     * Send mail
     * @params (type, to_email, subject, to_info, list_id)
     */
    public static function send_email($type = 'double-optin', $to_email, $subject, $code = '', $list_id ='', $template_id = 0, $attributes = null)
    {
        $customizations = get_option('wc_sendinblue_settings', array());
        $general_settings = get_option('ws_main_option', array());
        if (!class_exists('Mailin'))
            require_once 'mailin.php';

        $mailin = new Mailin(WC_Sendinblue::sendinblue_api_url, $general_settings['access_key']);

        // get sender info
        $sender_email = trim(get_bloginfo('admin_email'));
        $sender_name = trim(get_bloginfo('name'));

        // send mail
        $to = array($to_email => '');
        $from = array($sender_email, $sender_name);
        $null_array = array();
        $site_domain = str_replace('https://', '', home_url());
        $site_domain = str_replace('http://', '', $site_domain);

        if( $type == 'woo-campaign' ){
            $html_content = isset($customizations['ws_email_campaign_message']) ? $customizations['ws_email_campaign_message'] : '';
            $text_content = strip_tags($html_content);
            //$from = '';
            //$subject = '';
        } else {
            if( $template_id == 0 ) {
                // default template
                $template_contents = self::get_email_template($type);
                $html_content = $template_contents['html_content'];
                $text_content = $template_contents['text_content'];
            }else{
                $templates = get_option('ws_templates',array());
                $template = $templates[$template_id];
                $html_content = $template['content'];
                $text_content = $template['content'];
                $html_content = str_replace('https://[DOUBLEOPTIN]', '{subscribe_url}', $html_content);
                $html_content = str_replace('http://[DOUBLEOPTIN]', '{subscribe_url}', $html_content);
                $html_content = str_replace('[DOUBLEOPTIN]', '{subscribe_url}', $html_content);
            }
        }
        $html_content = str_replace('{title}', $subject, $html_content);
        $html_content = str_replace('{site_domain}', $site_domain, $html_content);
        $html_content = str_replace('{subscribe_url}', add_query_arg(array('ws_action' => 'subscribe', 'code' => $code, 'li' => $list_id), home_url()), $html_content);
        if( $type == 'notify' ) {
            // $code is current number of sms credits
            $html_content = str_replace('{present_credit}', $code, $html_content);
        }
        $text_content = str_replace('{site_domain}', home_url(), $text_content);

        if ( isset($customizations['ws_smtp_enable']) && $customizations['ws_smtp_enable'] == 'yes' ) {

            $headers = array("Content-Type"=> "text/html; charset=iso-8859-1","X-Mailin-Tag" => "Woocommerce SendinBlue");
            $data = array(
                "to" => $to,
                "cc" => array(),
                "bcc" => array(),
                "from" => $from,
                "replyto" => array(),
                "subject" => $subject,
                "text" => $text_content,
                "html" => $html_content,
                "attachment" => array(),
                "headers" => $headers,
                "inline_image" => array()
            );
            $result = $mailin->send_email($data);
            $result = $result['code'] == 'success' ? true : false;
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = "From: $sender_name <$sender_email>";
            $result = @wp_mail($to_email, $subject, $html_content, $headers);
        }
        return $result;
    }
    /**
     * get email template by type (test, confirmation, double-optin)
     * return @values : array ( 'html_content' => '...', 'text_content' => '...' );
     */
    private static function get_email_template($type = 'test')
    {
        $lang = get_bloginfo('language');
        if ($lang == 'fr-FR')
            $file = 'temp_fr-FR';
        else
            $file = 'temp';


        $file_path = plugin_dir_url(__FILE__) . 'templates/' . $type . '/';

        // get html content
        $html_content = file_get_contents($file_path . $file . '.html');

        // get text content
        if($type != 'notify') {
            $text_content = file_get_contents($file_path . $file . '.txt');
        }else{
            $text_content = 'This is a notify message.';
        }

        $templates = array('html_content' => $html_content, 'text_content' => $text_content);

        return $templates;
    }
    /**
     * Send double optin email
     */
    public function double_optin_signup($email, $list_id, $info, $template_id = 0)
    {
        $response = $this->validation_email($email, $list_id);
        if ($response['code'] == 'already_exist')
            return $response['code'];

        // db store
        $data = SIB_Model_Contact::get_data_by_email($email);
        if ($data == false) {
            $uniqid = uniqid();
            $data = array(
                'email' => $email,
                'info' => maybe_serialize($info),
                'code' => $uniqid,
                'is_activate' => 0,
                'extra' => 0
            );
            SIB_Model_Contact::add_record($data);
        } else {
            $uniqid = $data['code'];
        }

        // send double optin email
        //if($template_id == 0)
        $subject = __('Please confirm subscription', 'wc_sendinblue');
        if(!self::send_email('double-optin', $email, $subject, $uniqid, $list_id, $template_id, $info))
            return 'fail';

        return 'success';
    }
    /**
     * Validation email
     */
    function validation_email($email, $list_id)
    {
        $general_settings = get_option('ws_main_option', array());
        if (!class_exists('Mailin'))
            require_once 'mailin.php';

        $mailin = new Mailin(WC_Sendinblue::sendinblue_api_url, $general_settings['access_key']);
        $data = array("email" => $email);
        $response = $mailin->get_user($data);
        if ($response['code'] == 'failure') {
            $ret = array(
                'code' => 'success',
                'listid' => array()
            );
            return $ret;
        }

        $listid = $response['data']['listid'];
        if (!isset($listid) || !is_array($listid)) {
            $listid = array();
        }
        if ($response['data']['blacklisted'] == 1) {
            $ret = array(
                'code' => 'update',
                'listid' => $listid
            );
        } else {
            if (!in_array($list_id, $listid)) {
                $ret = array(
                    'code' => 'success',
                    'listid' => $listid
                );
            } else {
                $ret = array(
                    'code' => 'already_exist',
                    'listid' => $listid
                );
            }
        }
        return $ret;
    }

    /** ajax module for get statistics regarding date range  */
    public static function ajax_get_daterange(){
        $general_settings = get_option('ws_main_option', array());
        if (!class_exists('Mailin'))
            require_once 'includes/mailin.php';
        $mailin = new Mailin(WC_Sendinblue::sendinblue_api_url, $general_settings['access_key']);
        $begin = new DateTime($_POST['begin']); //Date
        $end = new DateTime($_POST['end']);
        if($begin == $end){
            $begin = $begin->modify('+1 day');
            $end = $end->modify('+2 day');
        }else {
            $begin = $begin->modify('+1 day');
            $end = $end->modify('+1 day');
        }
        $begin = $begin->format("Y-m-d");
        $end = $end->format("Y-m-d");

        $statistics = WC_Sendinblue::get_statistics($mailin,$begin,$end);

        echo json_encode($statistics);
        die();
    }
    /** ajax module for send email campaign */
    public function ajax_email_campaign_send()
    {
        $campaign_type = isset($_POST['campaign_type']) ? $_POST['campaign_type'] : 'all';

        if($campaign_type == 'some'){
            $to = $_POST['contacts'];
        }elseif($campaign_type == 'all'){
            // all
        }else{
            // only
        }

        $info = array(
            'to' => $to,
            'from' => $_POST['sender'],
            'text' => $_POST['msg'],
            'subject' => $_POST['subject'],
            'sender' => $_POST['sender'],
            'title' => $_POST['title'],
        );

        $result = $this->sendEmailCampaign($info);
        echo json_encode($result); // $result = true or false
        die();
    }
} 