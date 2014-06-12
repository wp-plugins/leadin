<?php
//=============================================
// LI_Emailer Class
//=============================================
class LI_Emailer {
    
    /**
     * Class constructor
     */
    function __construct () 
    {

    }

    /**
     * Sends the leads history email
     *
     * @param   string
     * @return  bool $email_sent    Whether the email contents were sent successfully. A true return value does not automatically mean that the user received the email successfully. It just only means that the method used was able to process the request without any errors.
     */
    function send_new_lead_email ( $hashkey ) 
    {
        $li_contact = new LI_Contact();
        $li_contact->hashkey = $hashkey;
        $li_contact->get_contact_history();
        $history = $li_contact->history;

        $body = "";

        $body = $this->build_body($li_contact);

        // Each line in an email can only be 998 characters long, so lines need to be broken with a wordwrap
        $body = wordwrap($body, 900, "\r\n");

        $from = $history->lead->lead_email;
        $headers = "From: LeadIn <team@leadin.com>\r\n";
        $headers.= "Reply-To: LeadIn <" . $from . ">\r\n";
        $headers.= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers.= "MIME-Version: 1.0\r\n";
        $headers.= "Content-type: text/html; charset=utf-8\r\n";

        // Get email from plugin settings, if none set, use admin email
        $options = get_option('leadin_options');
        $to = ( $options['li_email'] ? $options['li_email'] : get_bloginfo('admin_email') ); // Get email from plugin settings, if none set, use admin email

        if ( $history->lead->last_submission_type == "comment" ) {   
            $subject = "Comment posted on " . $history->submission->form_page_title;
            $subject .= " by " . $history->lead->lead_email;
        } 
        elseif ( $history->lead->last_submission_type == "subscribe" ) {   
            $subject = "LeadIn Subscribe submission on " . $history->submission->form_page_title;
            $subject .= " by " . $history->lead->lead_email;
        }
        else {   
            $subject = "Form submission on " . get_bloginfo('name') . " - " . $history->lead->lead_email;
        }

        $email_sent = wp_mail($to, $subject, $body, $headers);

        return $email_sent;
    }

    function build_body ( $li_contact ) 
    {
        $format = '<html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html;charset=utf-8"/><meta name="viewport" content="width=device-width"/></head><body style="width: 100%%;min-width: 100%%;-webkit-text-size-adjust: 100%%;-ms-text-size-adjust: 100%%;margin: 0;padding: 0;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;text-align: left;line-height: 19px;font-size: 14px;background-color: #f1f1f1;"><table class="body" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;height: 100%%;width: 100%%;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;background-color: #f1f1f1;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="center" align="center" valign="top" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: center;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><center style="width: 100%%;min-width: 580px;"><table class="container" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: inherit;width: 580px;margin: 0 auto;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;">%s%s%s%s</td></tr></table></center></td></tr></table></body></html>';
        $built_body = sprintf($format, $this->build_submission_details(get_bloginfo('url')), $this->build_contact_identity($li_contact->history->lead->lead_email), $this->build_sessions($li_contact->history), $this->build_footer($li_contact));

        return $built_body;
    }

    function build_submission_details ( $url ) {
        $format = '<table class="row submission-detail" style="border-spacing: 0;border-collapse: collapse;padding: 0px;vertical-align: top;text-align: left;width: 100%%;position: relative;display: block;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="wrapper last" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;padding-right: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="twelve columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 580px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><h3 style="color: #666;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;margin: 0;text-align: left;line-height: 1.3;word-break: normal;font-size: 18px;">New submission on <a href="%s" style="color: #2ba6cb;text-decoration: none;">%s</a></h3></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td></tr></table>';
        $built_submission_details = sprintf($format, $url, get_bloginfo('name'));

        return $built_submission_details;
    }

    function build_contact_identity ( $email ) {
        $avatar_img = "https://app.getsignals.com/avatar/image/?emails=" . $email;
        
        $format = '<table class="row lead-identity" style="border-spacing: 0;border-collapse: collapse;padding: 0px;vertical-align: top;text-align: left;width: 100%%;position: relative;display: block;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="wrapper" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="two columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 80px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><img height="60" width="60" src="%s" style="background-color:#F6601D;outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;width: auto;max-width: 100%%;float: left;clear: both;display: block;"/></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td><td class="wrapper last" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;padding-right: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="ten columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 480px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><h1 style="color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;margin: 0;text-align: left;line-height: 60px;word-break: normal;font-size: 26px;">%s</h1></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td></tr></table>';
        $built_identity = sprintf($format, $avatar_img, $email);

        return $built_identity;
    }

    function build_sessions ( $history ) {
        $built_sessions = "";

        $sessions = $history->sessions;

        foreach ( $sessions as &$session ) {
            $first_event = end($session['events']);
            $first_event_date = $first_event['activities'][0]['event_date'];
            $session_date = date('F j, Y, g:i a', strtotime($first_event_date)); 

            $last_event = array_values($session['events']);
            $last_event = $last_event[0];
            $last_activity = end($last_event['activities']);
            $session_end_time = date('g:i a', strtotime($last_activity['event_date']));

            $format = '<table class="row lead-timeline__date" style="border-spacing: 0;border-collapse: collapse;padding: 0px;vertical-align: top;text-align: left;width: 100%%;position: relative;display: block;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="wrapper last" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;padding-right: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="twelve columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 580px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><h4 style="color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: bold;padding: 0;margin: 0;text-align: left;line-height: 1.3;word-break: normal;font-size: 14px;">%s - %s</h4></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td></tr></table>';
            $built_sessions .= sprintf($format, $session_date, $session_end_time);

            $events = $session['events'];

            foreach ( $events as &$event ) {
                if ( $event['event_type'] == 'pageview' ) {
                    $pageview = $event['activities'][0];
                    $pageview_time = date('g:ia', strtotime($pageview['event_date']));
                    $pageview_url = $pageview['pageview_url'];
                    $pageview_title = $pageview['pageview_title'];
                    $pageview_source = $pageview['pageview_source'];

                    $format = '<table class="row lead-timeline__event pageview" style="border-spacing: 0;border-collapse: collapse;padding: 0px;vertical-align: top;text-align: left;width: 100%%;position: relative;display: block;background-color: #fff;border-top: 1px solid #dedede;border-right: 1px solid #dedede;border-left: 4px solid #28c;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="wrapper" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="two columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 80px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><p class="lead-timeline__event-time" style="margin: 0;color: #1f6696;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;text-align: left;line-height: 19px;font-size: 14px;margin-bottom: 10px;">%s</p></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td><td class="wrapper last" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;padding-right: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="ten columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 480px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><p class="lead-timeline__event-title" style="margin: 0;color: #1f6696;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;text-align: left;line-height: 19px;font-size: 14px;margin-bottom: 10px;">%s</p><p class="lead-timeline__pageview-url" style="margin: 0;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;text-align: left;line-height: 19px;font-size: 14px;margin-bottom: 10px;"><a href="%s" style="color: #999;text-decoration: none;">%s</a></p></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td></tr></table>';
                    $built_sessions .= sprintf($format, $pageview_time, $pageview_title, $pageview_url, $pageview_url);

                    if ( $pageview['event_date'] == $first_event_date ) {
                        $format = '<table class="row lead-timeline__event traffic-source" style="margin-bottom: 20px;border-spacing: 0;border-collapse: collapse;padding: 0px;vertical-align: top;text-align: left;width: 100%%;position: relative;display: block;background-color: #fff;border-top: 1px solid #dedede;border-right: 1px solid #dedede;border-left: 4px solid #99aa1f;border-bottom: 1px solid #dedede;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="wrapper" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="two columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 80px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><p class="lead-timeline__event-time" style="margin: 0;color: #727e14;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;text-align: left;line-height: 19px;font-size: 14px;margin-bottom: 10px;">%s</p></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td><td class="wrapper last" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;padding-right: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="ten columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 480px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><p class="lead-timeline__event-title" style="margin: 0;color: #727e14;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;text-align: left;line-height: 19px;font-size: 14px;margin-bottom: 10px;">Traffic Source: %s</p></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td></tr></table>';
                        $built_sessions .= sprintf($format, $pageview_time, ( $pageview_source ? '<a href="' . $pageview_source . '">' . $pageview_source . '</a>' : 'Direct' ));
                    }
                }
                else if ( $event['event_type'] == 'form' ) {
                    $submission = $event['activities'][0];
                    $submission_Time = date('g:ia', strtotime($submission['event_date']));
                    $submission_url = $submission['form_page_url'];
                    $submission_page_title = $submission['form_page_title'];
                    $submission_form_fields = json_decode(stripslashes($submission['form_fields']));
                    
                    $format = '<table class="row lead-timeline__event submission" style="border-spacing: 0;border-collapse: collapse;padding: 0px;vertical-align: top;text-align: left;width: 100%%;position: relative;display: block;background-color: #fff;border-top: 1px solid #dedede;border-right: 1px solid #dedede;border-left: 4px solid #f6601d;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="wrapper" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="two columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 80px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><p class="lead-timeline__event-time" style="margin: 0;color: #b34a12;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;text-align: left;line-height: 19px;font-size: 14px;margin-bottom: 10px;">%s</p></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td><td class="wrapper last" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;padding-right: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="ten columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 480px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="text-pad" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;padding-left: 10px;padding-right: 10px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><p class="lead-timeline__event-title" style="margin: 0;color: #b34a12;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;text-align: left;line-height: 19px;font-size: 14px;margin-bottom: 10px;">Filled out form on page <a href="%s" style="color: #2ba6cb;text-decoration: none;">%s</a></p> %s </td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td></tr></table>';
                    $built_sessions .= sprintf($format, $submission_Time, $submission_url, $submission_page_title, $this->build_form_fields($submission_form_fields));
                }
            }
        }

        return $built_sessions;
    }

    function build_form_fields ( $form_fields ) {
        $built_form_fields = "";

        foreach ( $form_fields as $field )
        {
            $format = '<p class="lead-timeline__submission-field" style="margin: 0;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;padding: 0;text-align: left;line-height: 19px;font-size: 14px;margin-bottom: 10px;"><label class="lead-timeline__submission-label" style="text-transform: uppercase;font-size: 12px;color: #999;letter-spacing: 0.05em;">%s</label><br/>%s </p>';
            $built_form_fields .= sprintf($format, $field->label, $field->value);
        }
        
        return $built_form_fields;
    }

    function build_footer ( $li_contact ) {
        $built_footer = "";
        $button_text = "View Contact Record";
        $contactViewUrl = get_bloginfo('wpurl') . "/wp-admin/admin.php?page=leadin_contacts&action=view&lead=" . $li_contact->history->lead->lead_id;
        
        $format = '<table class="row footer" style="margin-bottom: 20px;border-spacing: 0;border-collapse: collapse;padding: 0px;vertical-align: top;text-align: left;width: 100%%;position: relative;display: block;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td class="wrapper last" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 10px 20px 0px 0px;vertical-align: top;text-align: left;position: relative;padding-right: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><table class="eight columns" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;margin: 0 auto;width: 380px;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td align="center" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0px 0px 10px;vertical-align: top;text-align: left;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"><center style="width: 100%%;min-width: 380px;"><table class="button medium-button radius" style="border-spacing: 0;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;width: 100%%;overflow: hidden;"><tr style="padding: 0;vertical-align: top;text-align: left;"><td style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 12px 0 10px;vertical-align: top;text-align: center;color: #ffffff;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;display: block;width: auto;background: #2ba6cb;border: 1px solid #2284a1;-webkit-border-radius: 3px;-moz-border-radius: 3px;border-radius: 3px;"><a href="%s" style="color: #ffffff;text-decoration: none;font-weight: bold;font-family: Helvetica, Arial, sans-serif;font-size: 20px;display: block;height: 100%%;width: 100%%;">%s</a></td></tr></table></center></td><td class="expander" style="word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse;padding: 0;vertical-align: top;text-align: left;visibility: hidden;width: 0px;color: #222222;font-family: Helvetica, Arial, sans-serif;font-weight: normal;margin: 0;line-height: 19px;font-size: 14px;"></td></tr></table></td></tr></table>';
        $built_footer .= sprintf($format, $contactViewUrl, $button_text);

        return $built_footer;
    }

}