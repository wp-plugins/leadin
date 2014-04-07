<?php
//=============================================
// LI_Emailer Class
//=============================================
class LI_Emailer {
	
	/**
	 * Class constructor
	 */
	function LI_Emailer () 
	{

	}

	/**
	 * Sends the leads history email
	 *
	 * @param	string
	 * @return	bool $email_sent 	Whether the email contents were sent successfully. A true return value does not automatically mean that the user received the email successfully. It just only means that the method used was able to process the request without any errors.
	 */
	function send_new_lead_email ( $hashkey ) 
	{
		$history = $this->get_lead_history($hashkey);

		$avatar_img = "https://app.getsignals.com/avatar/image/?emails=" . $history->lead->lead_email;
		
		// @EMAIL - Use this variable to concatenate your HTML
		$body = "";

		// Email Base open
		$body .= "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Strict//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'><html xmlns='http://www.w3.org/1999/xhtml' xmlns='http://www.w3.org/1999/xhtml'><head><meta http-equiv='Content-Type' content='text/html;charset=utf-8'/><meta name='viewport' content='width=device-width'/></head><body style='width: 100% !important;-webkit-text-size-adjust: 100%;-ms-text-size-adjust: 100%;color: #222222;display: block;font-family: Helvetica, Arial, sans-serif;font-weight: normal;text-align: left;line-height: 19px;font-size: 14px;margin: 0;padding: 0;'><style type='text/css'>a:hover{color: #2795b6 !important;}a:active{color: #2795b6 !important;}a:visited{color: #2ba6cb !important;}h1 a:active{color: #2ba6cb !important;}h2 a:active{color: #2ba6cb !important;}h3 a:active{color: #2ba6cb !important;}h4 a:active{color: #2ba6cb !important;}h5 a:active{color: #2ba6cb !important;}h6 a:active{color: #2ba6cb !important;}h1 a:visited{color: #2ba6cb !important;}h2 a:visited{color: #2ba6cb !important;}h3 a:visited{color: #2ba6cb !important;}h4 a:visited{color: #2ba6cb !important;}h5 a:visited{color: #2ba6cb !important;}h6 a:visited{color: #2ba6cb !important;}.button:hover table td{background: #2795b6 !important;}.tiny-button:hover table td{background: #2795b6 !important;}.small-button:hover table td{background: #2795b6 !important;}.medium-button:hover table td{background: #2795b6 !important;}.large-button:hover table td{background: #2795b6 !important;}.button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.tiny-button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.tiny-button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.tiny-button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.small-button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.small-button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.small-button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.medium-button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.medium-button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.medium-button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.large-button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.large-button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.large-button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.secondary:hover table td{background: #d0d0d0 !important;}.success:hover table td{background: #457a1a !important;}.alert:hover table td{background: #970b0e !important;}@media only screen and (max-width: 600px){table[class='body'] img{width: auto !important;height: auto !important;}table[class='body'] .container{width: 95% !important;}table[class='body'] .row{width: 100% !important;display: block !important;}table[class='body'] .wrapper{display: block !important;padding-right: 0 !important;}table[class='body'] .columns{table-layout: fixed !important;float: none !important;width: 100% !important;padding-right: 0px !important;padding-left: 0px !important;display: block !important;}table[class='body'] .column{table-layout: fixed !important;float: none !important;width: 100% !important;padding-right: 0px !important;padding-left: 0px !important;display: block !important;}table[class='body'] .wrapper.first .columns{display: table !important;}table[class='body'] .wrapper.first .column{display: table !important;}table[class='body'] table.columns td{width: 100%;}table[class='body'] table.column td{width: 100%;}table[class='body'] td.offset-by-one{padding-left: 0 !important;}table[class='body'] td.offset-by-two{padding-left: 0 !important;}table[class='body'] td.offset-by-three{padding-left: 0 !important;}table[class='body'] td.offset-by-four{padding-left: 0 !important;}table[class='body'] td.offset-by-five{padding-left: 0 !important;}table[class='body'] td.offset-by-six{padding-left: 0 !important;}table[class='body'] td.offset-by-seven{padding-left: 0 !important;}table[class='body'] td.offset-by-eight{padding-left: 0 !important;}table[class='body'] td.offset-by-nine{padding-left: 0 !important;}table[class='body'] td.offset-by-ten{padding-left: 0 !important;}table[class='body'] td.offset-by-eleven{padding-left: 0 !important;}table[class='body'] .expander{width: 9999px !important;}table[class='body'] .hide-for-small{display: none !important;}table[class='body'] .show-for-desktop{display: none !important;}table[class='body'] .show-for-small{display: inherit !important;}table[class='body'] .hide-for-desktop{display: inherit !important;}table[class='body'] .container.main{width: 100% !important;}}</style><table class='body' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;height: 100%;width: 100%;padding: 0;'><tr align='left' style='vertical-align: top; text-align: left; padding: 0;'><td class='center' align='center' valign='top' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: center;padding: 0 0 20px;'><center style='width: 100%;'>";
		
		// Email Header open
		$body .= "<table class='row header' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;position: relative;padding: 0px;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='center' align='center' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: center;padding: 0;' valign='top'><center style='width: 100%;'><table class='container' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: inherit;width: 580px;margin:0 auto 10px auto; padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;position: relative;padding: 10px 0px 0px;' align='left' valign='top'><table class='twelve columns' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 580px;margin: 0 auto;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='two sub-columns' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;width: 16.66667% !important;padding: 0px 3.44828% 10px 0px;' align='left' valign='top'><img width='77' height='77' src='" . $avatar_img . "' style='outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;width: auto;max-width: 100%;float: left;clear: both;display: block;' align='left'/></td><td class='ten sub-columns last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: middle;text-align: left;width: 83.33333% !important;padding: 0px 0px 10px;' align='left' valign='middle'>";

		$body .= "<h1 class='lead-name' style='color: #222222; display: block; font-family: Helvetica, Arial, sans-serif; font-weight: bold; text-align: left; line-height: 1.3; word-break: normal; font-size: 20px; margin: 0; padding: 0;' align='left'>" . $history->lead->lead_email . "</h1>";
		
		// Email Header close
		$body .= "</td><td class='expander' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;visibility: hidden;width: 0px;padding: 0;' align='left' valign='top'></td></tr></table></td></tr></table></center></td></tr></table>";

		// Main container open
		$body .= "<table class='container main' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: inherit;width: 580px;margin: 0 auto;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;padding: 0;' align='left' valign='top'>";
		
		// Form Submission section open
		$body .= "<table class='row section form-submission' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;position: relative;display: block;background: #deedf8;padding: 0px;' bgcolor='#deedf8'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;position: relative;padding: 0 0px 0 0;' align='left' valign='top'>";

		// Form Submission section header
		$body .= $this->build_submission_header($history->submission, FALSE);

		// Form Submission Rows
		$fields = json_decode(stripslashes($history->submission->form_fields), true);
		
		foreach ( $fields as $field )
		{
			$body .= $this->build_submission_row($field);
		}

		// Form Submission Section Close
		$body .= "</td></tr></table>";
		
		// @EMAIL - end form section

		// History section title
		$body .= "<h2 class='section-title' style='color: #222222; display: block; font-family: Helvetica, Arial, sans-serif; font-weight: bold; text-align: left; line-height: 1.3; word-break: normal; font-size: 18px; margin: 0; padding: 30px 0px 0px 20px;' align='left'>Lead History</h2>";

		foreach ( array_reverse($history->pageviews_by_session) as $session )
		{
			// History section open
			$body .= "<table class='row section lead-history' style='border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 100%; position: relative; display: block; background: #ffefe6; padding: 0px; margin-top: 20px;' bgcolor='#ffefe6'><tr style='vertical-align: top; text-align: left; padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; position: relative; padding: 0 0px 0 0;' align='left' valign='top'>";

			$body .= $this->build_history_header($session);

			// History section rows
			foreach ( $session as $pageview )
			{
				$body .= $this->build_history_row($pageview);
			}
			
			// History section close
			$body .= '</td></tr></table>';

			// @EMAIL - end session
		}
		// @EMAIL - end visit history

		// Button row
		$body .= $this->build_button_row($history->lead->lead_id);

		// Main container close
		$body .= '</td></tr></table>';

		// Email Base close
		$body .= '</center></td></tr></table></body></html>';

		// Each line in an email can only be 998 characters long, so lines need to be broken with a wordwrap
		$body = wordwrap($body, 900, "\r\n");

		$headers = "From: LeadIn <team@leadin.com>\r\n";
		$headers.= "Reply-To: LeadIn <team@leadin.com>\r\n";
		$headers.= "X-Mailer: PHP/" . phpversion()."\r\n";
		$headers.= "MIME-Version: 1.0\r\n";
		$headers.= "Content-type: text/html; charset=utf-8\r\n";

		// Get email from plugin settings, if none set, use admin email
		$options = get_option('leadin_options');
		$to = ( $options['li_email'] ? $options['li_email'] : get_bloginfo('admin_email') ); // Get email from plugin settings, if none set, use admin email

		if ( $history->submission->form_type == "comment" )
		{	
			$subject = "New comment posted on " . $history->submission->form_page_title;
			leadin_track_plugin_activity("New comment");
			$subject .= " by " . $history->lead->lead_email;
		}
		else if ( $history->submission->form_type == "subscribe" )
		{
			$first_name = leadin_get_value_by_key('First name', $fields);
			$subject = $first_name . " loved " . $history->submission->form_page_title . " and subscribed to your mailing list";
			leadin_track_plugin_activity("New subscriber");
			$this->send_subscriber_confirmation_email($history);
		}
		else
		{	
			if ( $history->new_contact )
			{
				$subject = "New lead from " . get_bloginfo('name') . " - Say hello to " . $history->lead->lead_email;
				leadin_track_plugin_activity("New lead");
			}
			else
			{
				$subject = "Lead from " . get_bloginfo('name') . " - Say hello again to " . $history->lead->lead_email;
				leadin_track_plugin_activity("Returning lead");
			}
		}

		$email_sent = wp_mail($to, $subject, $body, $headers);
		return $email_sent;
	}

	/**
	 * Gets lead history from the database (pageviews, form submission, and lead details)
	 *
	 * @param	string
	 * @return	object 	$history (pageviews_by_session, submission, lead)
	 */
	function get_lead_history ( $hashkey )
	{
		global $wpdb;

		$q = $wpdb->prepare(
			"SELECT 
				pageview_id,
				DATE_FORMAT(pageview_date, %s) AS pageview_day, 
				DATE_FORMAT(pageview_date, %s) AS pageview_date, 
				lead_hashkey, pageview_title, pageview_url, pageview_source, pageview_session_start 
			FROM 
				li_pageviews 
			WHERE 
				lead_hashkey LIKE %s ORDER BY pageview_date ASC", '%b %e', '%b %e %l:%i%p', $hashkey);
		
		$pageviews = $wpdb->get_results($q);

		$pageviews_by_session = array();
		$cur_array = '0';
		$count = 0;
		foreach ( $pageviews as $pageview )
		{
			if ( $pageview->pageview_session_start )
			{
				$cur_array = $count;
				$pageviews_by_session['session_' . $cur_array] = array();
			}

			$pageviews_by_session['session_' . $cur_array][] = $pageview;
			$count++;
		}

		$q = $wpdb->prepare("SELECT form_date AS form_datetime, DATE_FORMAT(form_date, %s) AS form_date, form_page_title, form_page_url, form_fields, form_type FROM li_submissions WHERE lead_hashkey = '%s' ORDER BY form_datetime DESC", '%b %e %l:%i%p', $hashkey);
		$submissions = $wpdb->get_results($q);

		$q = $wpdb->prepare("SELECT lead_id, lead_date, lead_ip, lead_source, lead_email, lead_status FROM li_leads WHERE hashkey LIKE %s AND lead_email != ''", $hashkey);
		$lead = $wpdb->get_row($q);

		$history = (object)NULL;
		$history->pageviews_by_session = $pageviews_by_session;
		$history->submission = $submissions[0];
		$history->new_contact = ( count($submissions) == 1 ? true : false );
		$history->lead = $lead;

		return $history;
	}

	/**
	 * Builds the blue form submission header for the lead email
	 * @param 	object $submission
	 * @return 	string
	 */		
	function build_submission_header ( $submission, $confirmation_email = FALSE )
	{
		// @EMAIL Use these variables to construct the heading for the form section
		$form_page_title = "<a href='" . $submission->form_page_url . "'>" . $submission->form_page_title . "</a>";
		$form_submission_day = date('M j' , strtotime($submission->form_date));
		$form_submission_time = date('g:i a', strtotime($submission->form_date));
		$form_submission_type = $submission->form_type;

		$submissionHeader = "";

		// Form Submission header open
		$submissionHeader .= "<table class='twelve columns' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 580px;margin: 0 auto;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='section-header' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;border-bottom-width: 1px;border-bottom-color: #c9e1f3;border-bottom-style: solid;background: #c9e1f3;padding: 15px 20px;' align='left' bgcolor='#c9e1f3' valign='top'>";

		// Form Submission header content
		$submissionHeader .= "<h3 style='color: #153d60;display: block;font-family: Helvetica, Arial, sans-serif;font-weight: bold;text-align: left;line-height: 1.3;word-break: normal;font-size: 16px;margin: 0;padding: 0;' align='left'>";
		
		if ( $form_submission_type == "comment" )
		{
			$submissionHeader .= "Commented on " . $form_page_title . " on " . $form_submission_day . ' at ' . $form_submission_time;
		}
		else
		{
			$submissionHeader .= ( $confirmation_email ? "You filled out the subscription form on " : "Filled out a form on " ) . $form_page_title . " on " . $form_submission_day . ' at ' . $form_submission_time;
		}
		
		$submissionHeader .= "</h3>";

		// Form Submission header close
		$submissionHeader .= "</td><td class='expander' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;visibility: hidden;width: 0px;border-bottom-color: #c9e1f3;border-bottom-style: solid;padding: 0;border-width: 0 0 1px;' align='left' valign='top'></td></tr></table>";

		return $submissionHeader;
	}

	/**
	 * Builds a row containing a form field in the submission section for the lead email
	 * @param 	object $field
	 * @return 	string
	 */	
	function build_submission_row ( $field )
	{
		$submissionRow = "\r\n";

		// Form Submission Row open
		$submissionRow .= "<table class='twelve columns' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 580px;margin: 0 auto;padding: 0;'><tr align='left' style='vertical-align: top; text-align: left; padding: 0;'><td align='left' valign='top' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;border-bottom-width: 1px;border-bottom-color: #c9e1f3;border-bottom-style: solid;padding: 10px 20px;'>";

		// Form Submission Label
		$submissionRow .= "<p class='form-field-label' style='color: #222222; display: block; font-family: Helvetica, Arial, sans-serif; font-weight: bold; text-align: left; line-height: 19px; font-size: 12px; margin: 0; padding: 0;' align='left'>" . $field['label'] . "</p>";

		// Form Submission Value
		$submissionRow .= "<p class='form-field' style='color: #222222; display: block; font-family: Helvetica, Arial, sans-serif; font-weight: normal; text-align: left; line-height: 19px; font-size: 14px; margin: 0; padding: 3px 0 0;' align='left'>" . ( $field['value'] ? $field['value'] : 'not provided' ) . "</p>";

		// Form Submission Row close
		$submissionRow .= "<td class='expander' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;visibility: hidden;width: 0px;border-bottom-color: #c9e1f3;border-bottom-style: solid;padding: 0;border-width: 0 0 1px;' align='left' valign='top'></td></tr></table>";

		return $submissionRow;
	}

	/**
	 * Builds the orange session heading for the lead email
	 * @param 	object $session
	 * @return 	string
	 */	
	function build_history_header ( $session )
	{
		// @EMAIL - Use these variables to construct the heading for a specfic session
		$session_header = $session[0]->pageview_day;
		$session_start_time = str_replace(array('PM', 'AM'), array('pm', 'am'), str_replace($session[0]->pageview_day . " ", "", $session[0]->pageview_date));
		$session_end_time = str_replace(array('PM', 'AM'), array('pm', 'am'), str_replace($session[0]->pageview_day . " ", "", $session[count($session)-1]->pageview_date));
		$session_num_pages = ( count($session) != 1 ? count($session) . ' pages' : '1 page' ); // Fix plural on page/pages
		$session_time_string = $session_start_time;
		$session_source = $session[0]->pageview_source;

		// Append the end time if it doesn't match the start time
		if ( $session_start_time != $session_end_time )
		{
			$session_time_string .= " - " . $session_end_time;
		}

		$historyHeader = "\r\n";

		// History section header open
		$historyHeader .= "<table class='twelve columns' style='border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 580px; margin: 0 auto; padding: 0;'><tr style='vertical-align: top; text-align: left; padding: 0;' align='left'><td class='section-header' style='word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; border-bottom-width: 1px; border-bottom-style: solid; background: #fdd8c0; border-bottom-color: #fdd8c0; padding: 15px 20px;' align='left' bgcolor='#fdd8c0' valign='top'>";

		// History section header content
		$historyHeader .= "<h3 style='color: #a43700; display: block; font-family: Helvetica, Arial, sans-serif; font-weight: bold; text-align: left; line-height: 1.3; word-break: normal; font-size: 16px; margin: 0; padding: 0;' align='left'>Viewed " . $session_num_pages . " - " . $session_header . " " . $session_time_string . "</h3>";

		// History section header close
		$historyHeader .= "</td><td class='expander' style='word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; visibility: hidden; width: 0px; padding:0;' align='left' valign='top'></td></tr></table>";

		// Source section open
		$historyHeader .= "<table class='twelve columns' style='border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 580px; margin: 0 auto; padding: 0;'><tr style='vertical-align: top; text-align: left; padding: 0;' align='left'><td style='word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; border-bottom-width: 1px; border-bottom-color: #fdd8c0; border-bottom-style: solid; padding: 10px 20px;' align='left' valign='top'>";

		// Source section content
		// If source isn't set, set it to direct
		if ( $session_source )
		{
			$session_source_text .= "<a href='" . $session_source . "' style='color: #2ba6cb; text-decoration: none !important;'>" . $session_source . "</a>";
		}
		else
		{
			$session_source_text = "Direct";
			
		}

		$historyHeader .= "<p class='form-field' style='color: #222222; display: block; font-family: Helvetica, Arial, sans-serif; font-weight: normal; text-align: left; line-height: 19px; font-size: 14px; margin: 0; padding: 3px 0 0;' align='left'><span class='traffic-source' style='font-weight: bold; color: #a43700;'>Traffic Source: </span>" . $session_source_text . "</p>";

		// Source section close
		$historyHeader .= "</td><td class='expander' style='word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; visibility: hidden; width: 0px; border-bottom-color: #fdd8c0; border-bottom-style: solid; padding: 0; border: 0;' align='left' valign='top'></td></tr></table>";

		return $historyHeader;
	}

	/**
	 * Builds a row containing a page view for the lead email
	 * @param 	object $pageview
	 * @return 	string
	 */	
	function build_history_row ( $pageview )
	{
		$historyRow = "\r\n";

		$historyRow .= "<table class='twelve columns' style='border-spacing: 0; border-collapse: collapse; vertical-align: top;text-align: left; width: 580px; margin: 0 auto; padding: 0;'>";
			$historyRow .= "<tr style='vertical-align: top;text-align: left;padding: 0;' align='left'>";
				$historyRow .= "<td align='left' valign='top' style='border-bottom-color: #fdd8c0; word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; border-bottom-width: 1px; border-bottom-style: solid; padding: 10px 20px;'>";
					$historyRow .= "<a href='" . $pageview->pageview_url . "'>" . ( str_replace('&nbsp;)','', trim($pageview->pageview_title)) ? $pageview->pageview_title : '(blank page title tag)' ) . "</a>";
				$historyRow .= "</td>";
			$historyRow .= "<td class='expander' style='word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; visibility: hidden; width: 0px; padding:0;' align='left' valign='top'></td>";
			$historyRow .= "</tr>";
		$historyRow .= "</table>";

		return $historyRow;
	}

	/**
	 * Builds the View All Contacts button for the lead email
	 * @return 	string
	 */	
	function build_button_row ( $lead_id )
	{
		$buttonRow = "\r\n";
		$contactViewUrl = get_bloginfo('wpurl') . "/wp-admin/admin.php?page=leadin_contacts&action=view&lead=" . $lead_id;

		$buttonRow .= "<table class='row section' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;position: relative;display: block;margin-top: 20px;padding: 0px;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;position: relative;padding: 0 0px 0 0;' align='left' valign='top'><table class='twelve columns' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 580px;margin: 0 auto;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;padding: 10px 20px;' align='left' valign='top'><table class='button round' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;overflow: hidden;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: center;display: block;width: auto !important;font-weight: bold;text-decoration: none;font-family: Helvetica, Arial, sans-serif;color: white;font-size: 16px;-webkit-border-radius: 500px;-moz-border-radius: 500px;border-radius: 500px;background: #f88e4b;padding: 10px 20px;border: 1px solid #f6601d;' align='center' bgcolor='#f88e4b' valign='top'>";
			$buttonRow .="<a href='" . $contactViewUrl . "' style='color: white !important; text-decoration: none !important; font-family: Helvetica, Arial, sans-serif;'>View Contact History</a>";
		$buttonRow .= "</td></tr></table></td><td class='expander' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;visibility: hidden;width: 0px;padding: 0;border: 0;' align='left' valign='top'></td></tr></table></td></tr></table>";

		return $buttonRow;
	}

	/**
	 * Sends the subscription confirmation email
	 *
	 * @param	object 		history from get_lead_history()
	 * @return	bool $email_sent 	Whether the email contents were sent successfully. A true return value does not automatically mean that the user received the email successfully. It just only means that the method used was able to process the request without any errors.
	 */
	function send_subscriber_confirmation_email ( $history ) 
	{	
		// Get email from plugin settings, if none set, use admin email
		$options = get_option('leadin_options');
		$leadin_email = ( $options['li_email'] ? $options['li_email'] : get_bloginfo('admin_email') ); // Get email from plugin settings, if none set, use admin email
		$site_name = get_bloginfo('name');
		$site_url = get_bloginfo('wpurl');

		// @EMAIL - Use this variable to concatenate your HTML
		$body = "";

		// Email Base open
		$body .= "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Strict//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'><html xmlns='http://www.w3.org/1999/xhtml' xmlns='http://www.w3.org/1999/xhtml'><head><meta http-equiv='Content-Type' content='text/html;charset=utf-8'/><meta name='viewport' content='width=device-width'/></head><body style='width: 100% !important;-webkit-text-size-adjust: 100%;-ms-text-size-adjust: 100%;color: #222222;display: block;font-family: Helvetica, Arial, sans-serif;font-weight: normal;text-align: left;line-height: 19px;font-size: 14px;margin: 0;padding: 0;'><style type='text/css'>a:hover{color: #2795b6 !important;}a:active{color: #2795b6 !important;}a:visited{color: #2ba6cb !important;}h1 a:active{color: #2ba6cb !important;}h2 a:active{color: #2ba6cb !important;}h3 a:active{color: #2ba6cb !important;}h4 a:active{color: #2ba6cb !important;}h5 a:active{color: #2ba6cb !important;}h6 a:active{color: #2ba6cb !important;}h1 a:visited{color: #2ba6cb !important;}h2 a:visited{color: #2ba6cb !important;}h3 a:visited{color: #2ba6cb !important;}h4 a:visited{color: #2ba6cb !important;}h5 a:visited{color: #2ba6cb !important;}h6 a:visited{color: #2ba6cb !important;}.button:hover table td{background: #2795b6 !important;}.tiny-button:hover table td{background: #2795b6 !important;}.small-button:hover table td{background: #2795b6 !important;}.medium-button:hover table td{background: #2795b6 !important;}.large-button:hover table td{background: #2795b6 !important;}.button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.tiny-button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.tiny-button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.tiny-button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.small-button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.small-button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.small-button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.medium-button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.medium-button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.medium-button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.large-button:hover{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.large-button:active{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.large-button:visited{color: white !important;font-family: Helvetica, Arial, sans-serif;text-decoration: none;}.secondary:hover table td{background: #d0d0d0 !important;}.success:hover table td{background: #457a1a !important;}.alert:hover table td{background: #970b0e !important;}@media only screen and (max-width: 600px){table[class='body'] img{width: auto !important;height: auto !important;}table[class='body'] .container{width: 95% !important;}table[class='body'] .row{width: 100% !important;display: block !important;}table[class='body'] .wrapper{display: block !important;padding-right: 0 !important;}table[class='body'] .columns{table-layout: fixed !important;float: none !important;width: 100% !important;padding-right: 0px !important;padding-left: 0px !important;display: block !important;}table[class='body'] .column{table-layout: fixed !important;float: none !important;width: 100% !important;padding-right: 0px !important;padding-left: 0px !important;display: block !important;}table[class='body'] .wrapper.first .columns{display: table !important;}table[class='body'] .wrapper.first .column{display: table !important;}table[class='body'] table.columns td{width: 100%;}table[class='body'] table.column td{width: 100%;}table[class='body'] td.offset-by-one{padding-left: 0 !important;}table[class='body'] td.offset-by-two{padding-left: 0 !important;}table[class='body'] td.offset-by-three{padding-left: 0 !important;}table[class='body'] td.offset-by-four{padding-left: 0 !important;}table[class='body'] td.offset-by-five{padding-left: 0 !important;}table[class='body'] td.offset-by-six{padding-left: 0 !important;}table[class='body'] td.offset-by-seven{padding-left: 0 !important;}table[class='body'] td.offset-by-eight{padding-left: 0 !important;}table[class='body'] td.offset-by-nine{padding-left: 0 !important;}table[class='body'] td.offset-by-ten{padding-left: 0 !important;}table[class='body'] td.offset-by-eleven{padding-left: 0 !important;}table[class='body'] .expander{width: 9999px !important;}table[class='body'] .hide-for-small{display: none !important;}table[class='body'] .show-for-desktop{display: none !important;}table[class='body'] .show-for-small{display: inherit !important;}table[class='body'] .hide-for-desktop{display: inherit !important;}table[class='body'] .container.main{width: 100% !important;}}</style><table class='body' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;height: 100%;width: 100%;padding: 0;'><tr align='left' style='vertical-align: top; text-align: left; padding: 0;'><td class='center' align='center' valign='top' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: center;padding: 0 0 20px;'><center style='width: 100%;'>";
		
		// Email Header open
		$body .= "<table class='row header' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;position: relative;padding: 0px;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='center' align='center' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: center;padding: 0;' valign='top'><center style='width: 100%;'><table class='container' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: inherit;width: 580px;margin:0 auto 10px auto; padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;position: relative;padding: 10px 0px 0px;' align='left' valign='top'><table class='twelve columns' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 580px;margin: 0 auto;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='two sub-columns' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;width: 100% !important;padding: 0px 0px 10px 0px;' align='left' valign='top'>";

		$body .= "<h1 class='lead-name' style='color: #222222; display: block; font-family: Helvetica, Arial, sans-serif; font-weight: bold; text-align: left; line-height: 1.3; word-break: normal; font-size: 20px; margin: 0; padding: 0;' align='left'>" . $site_name . "</h1>";

		// Email Header close
		$body .= "</td><td class='expander' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;visibility: hidden;width: 0px;padding: 0;' align='left' valign='top'></td></tr></table></td></tr></table></center></td></tr></table>";

		$body .= "<table class='row header' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;position: relative;padding: 0px;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='center' align='center' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: center;padding: 0;' valign='top'><center style='width: 100%;'><table class='container' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: inherit;width: 580px;margin:0 auto 10px auto; padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;position: relative;padding: 10px 0px 0px;' align='left' valign='top'><table class='twelve columns' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 580px;margin: 0 auto;padding: 0;'>";

			$body .= "<tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td>";
				$body .= "<td style='padding: 0px 0px 10px 0px;'>Your subscription to <i><a href='" . $site_url . "'>" . $site_name . "</a></i> has been confirmed.</td>";
			$body .= "</tr>";

			$body .= "<tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td>";
				$body .= "<td style='padding: 10px 0px 20px 0px;'>Just so you have it, here is a copy of the information you submitted to us...</td>";
			$body .= "</tr>";

		$body .= "</table>";

		// Main container open
		$body .= "<table class='container main' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: inherit;width: 580px;margin: 0 auto;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;padding: 0;' align='left' valign='top'>";
		
		// Form Submission section open
		$body .= "<table class='row section form-submission' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;position: relative;display: block;background: #deedf8;padding: 0px;' bgcolor='#deedf8'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;position: relative;padding: 0 0px 0 0;' align='left' valign='top'>";

		// Form Submission section header
		$body .= $this->build_submission_header($history->submission, TRUE);

		// Form Submission Rows
		$fields = json_decode(stripslashes($history->submission->form_fields), true);
		
		foreach ( $fields as $field )
		{
			$body .= $this->build_submission_row($field);
		}

		// Form Submission Section Close
		$body .= "</td></tr></table>";

		// Build [you may contact us at:] row
		$body .= "<table class='row section' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;position: relative;display: block;margin-top: 20px;padding: 0px;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;position: relative;padding: 0 0px 0 0;' align='left' valign='top'><table class='twelve columns' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 580px;margin: 0 auto;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;padding: 0px;' align='left' valign='top'><table class='button round' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;overflow: hidden;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td>";
			$body .="You may also contact us at:<br/><a href='mailto:" . $leadin_email . "'>" . $leadin_email . "</a>";
		$body .= "</td></tr></table></td><td class='expander' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;visibility: hidden;width: 0px;padding: 0;border: 0;' align='left' valign='top'></td></tr></table></td></tr></table>";
		
		// Build Powered by LeadIn row
		$body .= "<table class='row section' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;position: relative;display: block;margin-top: 20px;padding: 0px;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td class='wrapper last' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;position: relative;padding: 0 0px 0 0;' align='left' valign='top'><table class='twelve columns' style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 580px;margin: 0 auto;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td style='padding: 10px 20px;' align='left' valign='top'><table style='border-spacing: 0;border-collapse: collapse;vertical-align: top;text-align: left;width: 100%;overflow: hidden;padding: 0;'><tr style='vertical-align: top;text-align: left;padding: 0;' align='left'><td style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: center;display: block;width: auto !important;font-size: 16px;padding: 10px 20px;' align='center' valign='top'>";
			$body .="<div style='font-size: 11px; color: #888; padding: 0 0 5px 0;'>Powered by</div><a href='http://leadin.com/pop-subscribe-form-plugin-wordpress/'><img alt='LeadIn' height='20px' width='99px' src='http://leadin.com/wp-content/themes/LeadIn-WP-Theme/library/images/logos/Leadin_logo@2x.png' alt='leadin.com'/></a>";
		$body .= "</td></tr></table></td><td class='expander' style='word-break: break-word;-webkit-hyphens: auto;-moz-hyphens: auto;hyphens: auto;border-collapse: collapse !important;vertical-align: top;text-align: left;visibility: hidden;width: 0px;padding: 0;border: 0;' align='left' valign='top'></td></tr></table></td></tr></table>";

		// @EMAIL - end form section

		// Email Base close
		$body .= '</center></td></tr></table></body></html>';

		// Each line in an email can only be 998 characters long, so lines need to be broken with a wordwrap
		$body = wordwrap($body, 900, "\r\n");

		$headers = "From: LeadIn <team@leadin.com>\r\n";
		$headers.= "Reply-To: LeadIn <team@leadin.com>\r\n";
		$headers.= "X-Mailer: PHP/" . phpversion()."\r\n";
		$headers.= "MIME-Version: 1.0\r\n";
		$headers.= "Content-type: text/html; charset=utf-8\r\n";

		$subject = $site_name . ': Subscription Confirmed';

		$email_sent = wp_mail($history->lead->lead_email, $subject, $body, $headers);
		return $email_sent;
	}
}
?>