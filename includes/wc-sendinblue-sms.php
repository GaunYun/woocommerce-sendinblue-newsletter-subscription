<?php

class WC_Sendinblue_SMS {

    /**
     * Send confirmation SMS
     */
    public function ws_send_confirmation_sms($order, $from, $text){

        $first_name = $order->billing_first_name;
        $last_name = $order->billing_last_name;
        $total_pay = $order->order_total;
        $ord_date = $order->order_date;
        $iso_code = SIB_Model_Country::get_prefix($order->billing_country);

        $mobile_number = $this->checkMobileNumber($order->billing_phone, $iso_code);

        $text = str_replace('{first_name}', $first_name, $text);
        $text = str_replace('{last_name}', $last_name, $text);
        $text = str_replace('{order_price}', $total_pay, $text);
        $text = str_replace('{order_date}', $ord_date, $text);
        $data = array(
            "to" => $mobile_number,
            "from" => $from,
            "text" => $text,
        );
        $result = self::ws_sms_send($data);
    }

    /**
     * Send SMS
     */
    static function ws_sms_send($data)
    {
        $general_settings = get_option('ws_main_option', array());
        if (!class_exists('Mailin'))
            require_once 'mailin.php';

        $mailin = new Mailin(WC_Sendinblue::sendinblue_api_url, $general_settings['access_key']);

        $result = $mailin->send_sms($data);

        delete_transient('wswcsbcredit_' . md5( $general_settings['access_key'] ));

        return $result['code'];

    }
    /**
     * This method is called when the user sets the Campaign single Choice Campaign and hits the submit button.
     */
    public static function singleChoiceCampaign($info)
    {
        $sender_campaign_number = $info['to'];
        $sender_campaign = $info['from'];
        $sender_campaign_message = $info['text'];

        $personal_info = self::getCustomers($sender_campaign_number);
        if( $personal_info != "false" ){
            $sender_campaign_message = str_replace('{first_name}', $personal_info['firstname'], $sender_campaign_message);
            $sender_campaign_message = str_replace('{last_name}', $personal_info['lastname'], $sender_campaign_message);
        }

        $data = array(
            'to' => $sender_campaign_number,
            'from' => $sender_campaign,
            'text' => $sender_campaign_message,
        );

        $result = self::ws_sms_send($data);

        return $result;
    }

    /**
     * This method is called when the user send the campaign to all WordPress customers
     */

    public static function multipleChoiceCampaign($info)
    {
        $sender_campaign = $info['from'];
        $sender_campaign_message = $info['text'];

        $data = $final_result = array();
        $data['from'] = $sender_campaign;

        $response = self::getCustomers();
        foreach ($response as $userId=>$value)
        {
            if (isset($value['phone_mobile']) && !empty($value['phone_mobile']))
            {
                $number = self::checkMobileNumber($value['phone_mobile'], (!empty($value['iso_code'])?$value['iso_code']:''));

                $first_name   = (isset($value['firstname'])) ? $value['firstname'] : '';
                $last_name    = (isset($value['lastname'])) ? $value['lastname'] : '';

                $fname = str_replace('{first_name}', $first_name, $sender_campaign_message);
                $lname = str_replace('{last_name}', $last_name, $fname);

                $data['text'] = $lname;
                $data['to'] = $number;

                $result = self::ws_sms_send($data);

                if($result == 'success')
                    $final_result['code'] = 'success';
            }
        }
        return $final_result;
    }

    /**
     * This method is called when the user send the campaign to only subscribed customers
     */
    public static function multipleChoiceSubCampaign($info)
    {
        $general_settings = get_option('ws_main_option', array());
        $sender_campaign = $info['from'];
        $sender_campaign_message = $info['text'];

        //Create a campaign
        $camp_name = 'SMS_'.date('Ymd');

        $first_name = '{NAME}';
        $last_name = '{SURNAME}';

        $fname = str_replace('{first_name}', $first_name, $sender_campaign_message);
        $content = str_replace('{last_name}', $last_name, $fname);

        $listid = array_keys(self::$lists);

        $data = array(
            "name" => $camp_name,
            "sender" => $sender_campaign,
            "content" =>$content,
            "listid" => $listid,
            "scheduled_date" => date('Y-m-d H:i:s', current_time('timestamp') + 60),
        );

        if (!class_exists('Mailin'))
            require_once 'mailin.php';

        $mailin = new Mailin(WC_Sendinblue::sendinblue_api_url, $general_settings['access_key']);

        $result = $mailin->create_sms_campaign($data);

        delete_transient('wswcsbcredit_' . md5( $general_settings['access_key'] ));

        return $result;

    }
    /**
     *  This method is used to fetch all users from the default customer table to list
     * them in the SendinBlue PS plugin.
     */
    public static function getCustomers($phone_number = null)
    {
        $customer_data = get_users(array( 'role' => 'customer' ));
        $address_mobilephone = array();
        foreach ($customer_data as $customer_detail)
        {
            $iso_code = SIB_Model_Country::get_prefix($customer_detail -> billing_country);
            if (count($customer_detail) > 0)
            {
                $address_mobilephone[$customer_detail->ID] = array(
                    'firstname' => $customer_detail -> billing_first_name,
                    'lastname' => $customer_detail -> billing_last_name,
                    'phone_mobile' => $customer_detail -> billing_phone,
                    'iso_code' => $iso_code,
                );
            }
            if( $phone_number != null ){
                $number = self::checkMobileNumber($customer_detail -> billing_phone, $iso_code);
                if($phone_number == $number )
                    return $address_mobilephone[$customer_detail->ID];
            }

        }

        if($phone_number != null)
            return "false";

        return $address_mobilephone;
    }

    static function checkMobileNumber($number, $call_prefix)
    {
        $number = preg_replace('/\s+/', '', $number);
        $charone = substr($number, 0, 1);
        $chartwo = substr($number, 0, 2);

        if (preg_match('/^'.$call_prefix.'/', $number))
            return '00'.$number;

        else if ($charone == '0' && $chartwo != '00')
        {
            if (preg_match('/^0'.$call_prefix.'/', $number))
                return '00'.substr($number, 1);
            else
                return '00'.$call_prefix.substr($number, 1);
        }
        elseif ($chartwo == '00')
        {
            if (preg_match('/^00'.$call_prefix.'/', $number))
                return $number;
            else
                return '00'.$call_prefix.substr($number, 2);
        }
        elseif ($charone == '+')
        {
            if (preg_match('/^\+'.$call_prefix.'/', $number))
                return '00'.substr($number, 1);
            else
                return '00'.$call_prefix.substr($number, 1);
        }
        elseif ($charone != '0')
            return '00'.$call_prefix.$number;
    }


    /** ajax module for send test sms */
    public function ajax_sms_send()
    {
        $to = $_POST['sms'];

        $data = array(
            "to" => $to,
            "from" => "Sendinblue",
            "text" => __('Hello! This message has been sent using SendinBlue', 'wc_sendinblue'),
        );

        $result = self::ws_sms_send($data);

        echo json_encode($result);
        die();
    }

    /** ajax module for send campaign sms */
    public function ajax_sms_campaign_send()
    {
        $campaign_type = isset($_POST['campaign_type']) ? $_POST['campaign_type'] : 'all';
        $info = array(
            'to' => $_POST['sms'],
            'from' => $_POST['sender'],
            'text' => $_POST['msg'],
        );

        if($campaign_type == 'single'){
            $result = self::singleChoiceCampaign($info);
        }elseif($campaign_type == 'all') {
            $result = self::multipleChoiceCampaign($info);
        }else{
            $result = self::multipleChoiceSubCampaign($info);
        }

        echo json_encode($result); // $result['code'] = 'success'
        die();
    }

    /* ajax module for refresh sms credits */
    public function ajax_sms_refresh(){
        delete_transient('wswcsbcredit_' . md5( self::$access_key ));
        echo 'success';
        die();
    }
} 