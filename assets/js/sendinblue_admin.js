/* sendinblue_admin */

jQuery( function ( $ ) {

    //refresh credit info - ws_sms_refresh
    $('.ws_refresh').live('click', function(){

        var data = {
            action: 'ws_sms_refresh'
        }
        $.post(ajax_object.ajax_url, data,function(respond) {
            if(respond == 'success') {
                // refresh for transient data
            }
        });
    });
    /*
    * SMS options
    */
    if( ws_section == 'sms_options' || ws_section == 'campaigns') {

        $('.ws_sms_send_msg_desc').after('<p><i>' + VAR_SMS_MSG_DESC + '</i></p>');
        $('.ws_sms_send_test').after('<a href="javascript:void(0);" class = "ws_sms_send_test_btn button">'+ SEND_BTN +'</a>');

        var desc = $('.ws_sms_send_msg_desc:eq(0)').closest('td').find('i:eq(0)').text();
        var desc_arr = desc.split('%');
        var i = 0;
        $('.ws_sms_send_msg_desc').each(function(){
            var num_chr = 160 - $('.ws_sms_send_msg_desc:eq('+i+')').val().length;
            var flag = num_chr != 160 ? '1' : '0';
            $('.ws_sms_send_msg_desc:eq('+i+')').closest('td').find('i:eq(0)').text(desc_arr[0] + flag + desc_arr[1] + num_chr + desc_arr[2]);
            desc = $('.ws_sms_sender:eq('+i+')').closest('td').find('span').text();
            num_chr = 11 - $('.ws_sms_sender:eq('+i+')').val().length;
            $('.ws_sms_sender:eq('+i+')').closest('td').find('span').text(desc + num_chr);
            i++;
        });

    }
    $('#ws_sms_send_after').click(function(){
        $(this).closest('form').find('table:eq(2)').toggle(500,function(){ validProp(); });
        $(this).closest('form').find('h3:eq(1)').toggle();
    });
    $('#ws_sms_send_shipment').click(function(){
        $(this).closest('form').find('table:eq(3)').toggle(500,function(){ validProp(); });
        $(this).closest('form').find('h3:eq(2)').toggle();
    });
    $('#ws_sms_credits_notify').click(function(){
        $(this).closest('form').find('table:eq(4)').toggle(500,function(){ validProp(); });
        $(this).closest('form').find('h3:eq(3)').toggle();
    });
    $('input[name="ws_sms_send_after"]').each(function(){
        if(!$(this).is(':checked')){
            $(this).closest('form').find('table:eq(2)').hide();
            $(this).closest('form').find('h3:eq(1)').hide();
        }
    });
    $('input[name="ws_sms_send_shipment"]').each(function(){
        if(!$(this).is(':checked')){
            $(this).closest('form').find('table:eq(3)').hide();
            $(this).closest('form').find('h3:eq(2)').hide();
        }
    });
    $('input[name="ws_sms_credits_notify"]').each(function(){
        if(!$(this).is(':checked')){
            $(this).closest('form').find('table:eq(4)').hide();
            $(this).closest('form').find('h3:eq(3)').hide();
        }
    });
    function validProp(){
        $('input[type=text],input[type=email],input[type=number],textarea').each(function(){
            $(this).prop('required',false);
            if($(this).is(":visible") && $(this).attr('class') != 'ws_sms_send_test'){
                $(this).prop('required',true);
            }
        });
    }
    // change message info
    $( '.ws_sms_send_msg_desc').bind('input propertychange', function(){
        var sms_num = Math.ceil( $(this).val().length == 0 ? 1 : $(this).val().length  / 160 );
        var num_chr = 160*sms_num - $(this).val().length;
        $(this).closest('td').find('i:eq(0)').text(desc_arr[0]+sms_num+desc_arr[1]+num_chr+desc_arr[2]);
    });
    // change sender info
    $( '.ws_sms_sender').bind('input propertychange', function(){
        var num_chr = 11 - $(this).val().length;
        $(this).closest('td').find('span').text(desc+num_chr);
        // validation
        var sms_sender_val = $(this).val();
        sms_sender_val = sms_sender_val.replace(/[^a-z0-9]/gi, '');
        $(this).val(sms_sender_val);
    });
    if( ws_section == 'sms_options' && ws_section != '') {
        validProp();
    }
    /*-- SMS options end --*/

    //$('th').css('width','220px').css('text-align','right');

    $('input[name="ws_sms_send_to"]').click(function(){
        if($(this).val() != 'single'){
            $(this).closest( 'table' ).next('table').find('tr:eq(0)').hide('fast');
            $('#ws_sms_single_campaign').prop('required',false);
        }else{
            $(this).closest( 'table' ).next('table').find('tr:eq(0)').show('fast');
            $('#ws_sms_single_campaign').prop('required', true);
        }
    });
    $('input[name="ws_sms_send_to"]').each(function(){
        if($(this).is(':checked') && $( this).val() != 'single'){
            $('#ws_sms_single_campaign').closest('tr').hide('fast');
            $('#ws_sms_single_campaign').prop('required',false);
        }else if($(this).is(':checked') && $( this).val() == 'single'){
            $('#ws_sms_single_campaign').closest('tr').show('fast');
            $('#ws_sms_single_campaign').prop('required',true);
        }
    });

    /*
     * Email options
     */
    $('input[name="ws_email_templates_enable"]').click(function(){
        $(this).closest( 'table' ).next('table').toggle(500);
    });
    $('input[name="ws_email_templates_enable"]').each(function(){
        if($(this).is(':checked') && $( this).val() != 'yes'){
            $(this).closest( 'table' ).next('table').hide('fast');
        }else if($(this).is(':checked') && $( this).val() == 'no'){
            $(this).closest( 'table' ).next('table').show('fast');
        }
    });

    /**
    * Send test SMS
    */
    $('.ws_sms_send_test_btn').live('click', function () {

        var sms_to = $('.ws_sms_send_test').val();
        if(sms_to == '' || isValidSMS(sms_to) != true) {
            $('.ws_sms_send_test').focus();
            alert('Message has not been sent successfully.');
            return false;
        }
        $(this).attr('disabled', 'true');

        var data = {
            action: 'ws_sms_test_send',
            sms   : sms_to
        }

        $('.sib-spin').show();
        $.post(ajax_object.ajax_url, data,function(respond) {
            $('.sib-spin').hide();
            $('.ws_sms_send_test_btn').removeAttr('disabled');
            respond = $.parseJSON(respond);
            if(respond != 'success') {
                alert( 'Message has not been sent successfully.' );
            } else {
                alert('Message has been sent successfully.');
            }
        });
    });

    /**
     * Send the SMS campaign
     */
    $('#ws_sms_send_campaign_btn').live('click', function (){

        var sms_single = '0033663309741';
        if($( 'input[name="ws_sms_send_to"]:checked' ).val() == 'single') {
            sms_single = $('#ws_sms_single_campaign').val();
        }
        var sms_sender = $('#ws_sms_sender_campaign').val();
        var sms_send_msg = $('#ws_sms_campaign_message').val();

        var campaign_type = $('input[name=ws_sms_send_to]:checked').val();

        if( sms_single == '' || isValidSMS(sms_single) != true ) {
            $('#ws_sms_single_campaign').focus();
            alert('Message has not been sent successfully.');
            return false;
        }
        $('#ws_sms_send_msg_desc_campaign,#ws_sms_sender_campaign').each(function(){

            if($(this).val() == ''){
                $(this).focus();
                alert('Message has not been sent successfully.');return false;
            }

        });
        //
        $(this).attr('disabled', 'true');

        var data = {
            action       : 'ws_sms_campaign_send',
            campaign_type: campaign_type,
            sms          : sms_single,
            sender       : sms_sender,
            msg          : sms_send_msg
        }

        $('#ws_login_gif_sms').show();
        $.post(ajax_object.ajax_url, data,function(respond) {
            $('#ws_login_gif_sms').hide();
            $('#ws_sms_send_campaign_btn').removeAttr('disabled');
            respond = $.parseJSON(respond);
            if(respond != 'success') {
                alert( 'Message has not been sent successfully.' );
            } else {
                alert('Message has been sent successfully.');
            }
        });
    });
    /**
     * Send the email campaign
     */
    $('#ws_email_campaign_sender').change(function(){
        $('#ws_email_campaign_from_name').val($('#ws_email_campaign_sender option:selected').val());
    });
    $('.ws_email_send_campaign_btn').live('click', function (){

        var follow_contacts = {};
        if($( 'input[name="ws_email_campaign_to"]:checked' ).val() == 'some') {
            follow_contacts = $('#ws_email_campaign_following_contacts').val().split(',');
        }
        var campaign_name = $('#ws_email_campaign_name').val();
        var campaign_from_name = $('#ws_email_campaign_from_name').val();
        var campaign_sender = $('#ws_email_campaign_sender option:selected').text();
        var campaign_subject = $('#ws_email_campaign_subject').val();
        var campaign_msg = $('#ws_email_campaign_message').val();

        var campaign_type = $('input[name=ws_email_campaign_to]:checked').val();

        if( campaign_type == 'some') {
            // send to following contacts
            if(!isValidContacts(follow_contacts)){
                $('#ws_email_campaign_following_contacts').focus();
                alert('Message has not been sent successfully. Please check format of contacts');
                return false;
            }
        }
        $('#ws_email_campaign_name,#ws_email_campaign_from_name,#ws_email_campaign_sender,#ws_email_campaign_subject,#ws_email_campaign_message').each(function(){

            if($(this).val() == '' || $(this).val() == '-1'){
                $(this).focus();
                alert('Message has not been sent successfully.');
                return false;
            }

        });
        //
        $(this).attr('disabled', 'true');

        var data = {
            action       : 'ws_email_campaign_send',
            campaign_type: campaign_type,
            contacts     : follow_contacts,
            title        : campaign_name,
            sender       : campaign_sender,
            subject      : campaign_subject,
            msg          : campaign_msg
        };

        $('#ws_login_gif_email').show();
        $.post(ajax_object.ajax_url, data,function(respond) {
            $('#ws_login_gif_email').hide();
            $('#ws_email_send_campaign_btn').removeAttr('disabled');
            respond = $.parseJSON(respond);
            if(respond != 'success') {
                alert( 'Message has not been sent successfully.' );
            } else {
                alert('Message has been sent successfully.');
            }
        });
    });

    function isValidSMS(sms){
        var charone = sms.substring(0, 1);
        var chartwo = sms.substring(0, 2);
        if ( charone == '0' && chartwo == '00' )
            return true;
        return false;
    }

    function isValidContacts(emails){
        var email_check = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,6}$/i;
        $.each(emails, function(key, email){
            if(!email_check.test(email)){
                return false;
            }
        });
        return true;
    }

    /**
     * Validate API key
     */
    $('.ws_api_key_active').live('click', function () {

        var key = $('#ws_api_key').val();
        if(key == '') {
            $('#ws_api_key').focus();
            return false;
        }
        $(this).attr('disabled', 'true');
        $('#ws_login_gif').show();
        var data = {
            action: 'ws_validation_process',
            access_key   : key
        }

        $.post(ajax_object.ajax_url, data,function(respond) {
            location.reload();
        });
    });

    /*
     * Dismiss alert
     */
    $('.ws_credits_notice .notice-dismiss').live('click', function () {
        var alert_type = 'credit';
        //console.log(alert_type);
        var data = {
            action: 'ws_dismiss_alert',
            type: alert_type
        }
        $.post(ajax_object.ajax_url, data,function(respond) {
            if(respond == 'success') {
                //
            }
        });
    });

    if($("#ws_date_picker").length) {
        $("#ws_date_picker").daterangepicker({
            initialText : 'Today...',
            presetRanges: [{
                text: 'Today',
                dateStart: function () {
                    return moment()
                },
                dateEnd: function () {
                    return moment()
                }
            }, {
                text: 'Yesterday',
                dateStart: function () {
                    return moment().subtract(1, 'days')
                },
                dateEnd: function () {
                    return moment().subtract(1, 'days')
                }
            }, {
                text: 'Current Week',
                dateStart: function () {
                    return moment().startOf('week')
                },
                dateEnd: function () {
                    return moment().endOf('week')
                }
            }, {
                text: 'Last Week',
                dateStart: function () {
                    return moment().add('weeks', -1).startOf('week')
                },
                dateEnd: function () {
                    return moment().add('weeks', -1).endOf('week')
                }
            }, {
                text: 'Last Month',
                dateStart: function () {
                    return moment().add('months', -1).startOf('month')
                },
                dateEnd: function () {
                    return moment().add('months', -1).endOf('month')
                }
            }],

            datepickerOptions: {
                numberOfMonths: 2
                //initialText: 'Select period...'
            },
            onChange: function () {
                var date_range = JSON.stringify($("#ws_date_picker").daterangepicker("getRange"));
                $('.ws_date_picker button').addClass('ui-selected');
                var data = {
                    action: 'ws_get_daterange',
                    begin: JSON.parse(date_range).start,
                    end: JSON.parse(date_range).end
                }
                $('#ws_date_gif').show();
                $('#ws_statistics_table').css('opacity',0.5);
                $.post(ajax_object.ajax_url, data,function(respond) {
                    $('#ws_date_gif').hide();
                    $('#ws_statistics_table').css('opacity',1);
                    respond = $.parseJSON(respond);
                    $.map(respond, function(val, key) {
                        key = key.replace(' ', '-');
                        $('#'+key).find('td:eq(2)').html(val.sent);
                        $('#'+key).find('td:eq(3)').html(val.delivered);
                        $('#'+key).find('td:eq(4)').html(val.open_rate);
                        $('#'+key).find('td:eq(5)').html(val.click_rate);
                    });

                });

            }
        });
    }
    // Initialize for transients when user return after visit other page
    $(window).focus(function() {
        console.log('fouce');
        var data = {
            action: 'ws_transient_refresh'
        }
        $.post(ajax_object.ajax_url, data,function(respond) {
            if(respond == 'success') {
                //
            }
        });
    });

});
