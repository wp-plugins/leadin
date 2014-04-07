<?php

if ( !defined('LEADIN_PLUGIN_VERSION') )
{
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

/**
 * Check if the cookied hashkey has been merged with another contact
 *
 * @echo	Hashkey from a merged_hashkeys row, FALSE if hashkey does not exist in a merged_hashkeys row
 */
function leadin_check_merged_contact ()
{
	global $wpdb;

	$stale_hash = $_POST['li_id'];

	$q = $wpdb->prepare("SELECT hashkey, merged_hashkeys FROM li_leads WHERE merged_hashkeys LIKE '%%%s%%'", like_escape($stale_hash));
	$row = $wpdb->get_row($q);

	if ( isset($row->hashkey) )
	{
		// One final update to set all the previous pageviews to the new hashkey
		$q = $wpdb->prepare("UPDATE li_pageviews SET lead_hashkey = %s WHERE lead_hashkey = %s", $row->hashkey, $stale_hash);
		$wpdb->query($q);

		// One final update to set all the previous submissions to the new hashkey
		$q = $wpdb->prepare("UPDATE li_submissions SET lead_hashkey = %s WHERE lead_hashkey = %s", $row->hashkey, $stale_hash);
		$wpdb->query($q);

		// Remove the passed hash from the merged hashkeys for the row
		$merged_hashkeys = explode(',', $row->merged_hashkeys);
		$merged_hashkeys = leadin_array_delete($merged_hashkeys, "'" . $stale_hash . "'");
		
		$q = $wpdb->prepare("UPDATE li_leads SET merged_hashkeys = %s WHERE hashkey = %s", implode(',', $merged_hashkeys), $row->hashkey);
		$wpdb->query($q);

		$q = $wpdb->prepare("DELETE FROM li_leads WHERE hashkey LIKE '%%%s%%'", like_escape($stale_hash));
		$wpdb->query($q);

		echo json_encode($row->hashkey);
		die();
	}
	else
	{
		echo json_encode(FALSE);
		die();
	}
}

add_action('wp_ajax_leadin_check_merged_contact', 'leadin_check_merged_contact'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_check_merged_contact', 'leadin_check_merged_contact'); // Call when user is not logged in

/**
 * Inserts a new page view for a lead in li_pageviews
 *
 * @return	int
 */
function leadin_log_pageview ()
{
	global $wpdb;

	$hash 		= $_POST['li_id'];
	$title 		= htmlentities($_POST['li_title']);
	$url 		= $_POST['li_url'];
	$source 	= ( isset($_POST['li_referrer']) ? $_POST['li_referrer'] : '' );
	$last_visit = ( isset($_POST['li_last_visit']) ? $_POST['li_last_visit'] : 0 );

	$result = $wpdb->insert(
	    'li_pageviews',
	    array(
	        'lead_hashkey' => $hash,
	        'pageview_title' => $title,
	      	'pageview_url' => $url,
	      	'pageview_source' => $source,
	      	'pageview_session_start' => ( !$last_visit ? 1 : 0 )
	    ),
	    array(
	        '%s', '%s', '%s', '%s'
	    )
	);

	return $result;
}

add_action('wp_ajax_leadin_log_pageview', 'leadin_log_pageview'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_log_pageview', 'leadin_log_pageview'); // Call when user is not logged in

/**
 * Inserts a new lead into li_leads on first visit
 *
 * @return	int
 */
function leadin_insert_lead ()
{
	global $wpdb;

	$hashkey 	= $_POST['li_id'];
	$ipaddress 	= $_SERVER['REMOTE_ADDR'];
	$source 	= ( isset($_POST['li_referrer']) ? $_POST['li_referrer'] : '' );
	
	$result = $wpdb->insert(
	    'li_leads',
	    array(
	        'hashkey' => $hashkey,
	        'lead_ip' => $ipaddress,
	      	'lead_source' => $source  
	    ),
	    array(
	        '%s', '%s', '%s'
	    )
	);

	return $result;
}

add_action('wp_ajax_leadin_insert_lead', 'leadin_insert_lead'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_insert_lead', 'leadin_insert_lead'); // Call when user is not logged in

/**
 * Inserts a new form submisison into the li_submissions table and ties to the submission to a row in li_leads
 *
 * @return	int
 */
function leadin_insert_form_submission ()
{
	global $wpdb;

	$submission_hash 	= $_POST['li_submission_id'];
	$hashkey 			= $_POST['li_id'];
	$page_title 		= htmlentities($_POST['li_title']);
	$page_url 			= $_POST['li_url'];
	$form_json 			= $_POST['li_fields'];
	$email 				= $_POST['li_email'];
	$submission_type 	= $_POST['li_submission_type'];
	$options 			= get_option('leadin_options');
	$li_admin_email 	= ( $options['li_email'] ) ? $options['li_email'] : get_bloginfo('admin_email');

	// Check to see if the form_hashkey exists, and if it does, don't run the insert or send the email
	$q = $wpdb->prepare("SELECT form_hashkey FROM li_submissions WHERE form_hashkey = %s", $submission_hash);
	$submission_hash_exists = $wpdb->get_var($q);

	if ( $submission_hash_exists )
	{
		return 1;
		exit;
	}

	// Don't send the lead email when an administrator is leaving a comment or when the commenter's email is the same as the leadin email
	if ( !(current_user_can('administrator') && $submission_type == 'comment') && !(strstr($li_admin_email, $email) && $submission_type == 'comment') )
	{
		$q = $wpdb->prepare("SELECT * FROM li_leads WHERE hashkey = %s", $hashkey);
		$contact = $wpdb->get_row($q);

		// Check for existing contacts based on whether the email is present in the contacts table
		$q = $wpdb->prepare("SELECT lead_email, hashkey, merged_hashkeys, lead_status FROM li_leads WHERE lead_email = %s AND hashkey != %s", $email, $hashkey);
		$existing_contacts = $wpdb->get_results($q);

		$existing_contact_status = 'lead';
		
		// Setup the string for the existing hashkeys
		$existing_contact_hashkeys = $contact->merged_hashkeys;
		if ( $contact->merged_hashkeys )
			$existing_contact_hashkeys .= ',';

		// Do some merging if the email exists already in the contact table
		if ( count($existing_contacts) )
		{
			for ( $i = 0; $i < count($existing_contacts); $i++ )
			{
				// Start with the existing contact's hashkeys and create a string containg comma-deliminated hashes
				$existing_contact_hashkeys .= "'" . $existing_contacts[$i]->hashkey . "'";

				if ( $existing_contacts[$i]->merged_hashkeys )
					$existing_contact_hashkeys .= "," . $existing_contacts[$i]->merged_hashkeys;

				if ( $i != count($existing_contacts)-1 )
					$existing_contact_hashkeys .= ",";

				// Check on each existing lead if the lead_status is comment. If it is, save the status to override the new lead's status
				if ( $existing_contacts[$i]->lead_status == 'comment' && $existing_contact_status == 'lead' )
					$existing_contact_status = 'comment';

				// Check on each existing lead if the lead_status is subscribe. If it is, save the status to override the new lead's status
				if ( $existing_contacts[$i]->lead_status == 'subscribe' && ($existing_contact_status == 'lead' || $existing_contact_status == 'comment') )
					$existing_contact_status = 'subscribe';
			}

			// Update all the previous pageviews to the new hashkey
			$q = $wpdb->prepare("UPDATE li_pageviews SET lead_hashkey = %s WHERE lead_hashkey IN ( $existing_contact_hashkeys )", $hashkey);
			$wpdb->query($q);

			// Update all the previous submissions to the new hashkey
			$q = $wpdb->prepare("UPDATE li_submissions SET lead_hashkey = %s WHERE lead_hashkey IN ( $existing_contact_hashkeys )", $hashkey);
			$wpdb->query($q);
		}

		// Prevent duplicates by deleting existing submission if it didn't finish the process before the web page refreshed
		$q = $wpdb->prepare("DELETE FROM li_submissions WHERE form_hashkey = %s", $submission_hash);
		$wpdb->query($q);

		// Insert the form fields and hash into the submissions table
		$result = $wpdb->insert(
		    'li_submissions',
		    array(
		        'form_hashkey' => $submission_hash,
		        'lead_hashkey' => $hashkey,
		        'form_page_title' => $page_title,
		        'form_page_url' => $page_url,
		        'form_fields' => $form_json,
		        'form_type' => $submission_type
		    ),
		    array(
		        '%s', '%s', '%s', '%s', '%s', '%s'
		    )
		);

		$contact_status = $submission_type;

		// Override the status because comment is further down the funnel than lead
		if ( $contact->lead_status == 'comment' && $submission_type == 'lead' )
			$contact_status = 'comment';
		// Override the status because subscribe is further down the funnel than lead and comment
		else if ( $contact->lead_status == 'subscribe' && ($submission_type == 'lead' || $submission_type == 'comment') )
			$contact_status = 'subscribe';

		// Override the status with the merged contacts status if the children have a status further down the funnel
		if ( $existing_contact_status == 'comment' && $submission_type == 'lead' )
			$contact_status = 'comment';
		else if ( $existing_contact_status == 'subscribe' && ($submission_type == 'lead' || $submission_type == 'comment') )
			$contact_status = 'subscribe';

		// Update the contact with the new email, status and merged hashkeys
		$q = $wpdb->prepare("UPDATE li_leads SET lead_email = %s, lead_status = %s, merged_hashkeys = %s WHERE hashkey = %s", $email, $contact_status, $existing_contact_hashkeys, $hashkey);
		$rows_updated = $wpdb->query($q);

		// Send the contact email
		$li_emailer = new LI_Emailer();
		$li_emailer->send_new_lead_email($hashkey);

		return $rows_updated;
	}
}

add_action('wp_ajax_leadin_insert_form_submission', 'leadin_insert_form_submission'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_insert_form_submission', 'leadin_insert_form_submission'); // Call when user is not logged in


/**
 * Checks the lead status of the current visitor
 *
 */
function leadin_check_visitor_status ()
{
	global $wpdb;

	$hash 	= $_POST['li_id'];

	$q = $wpdb->prepare("SELECT lead_status FROM li_leads WHERE hashkey = %s", $hash);
	$lead_status = $wpdb->get_var($q);

	if ( isset($lead_status) )
	{
		echo json_encode($lead_status);
		die();
	}
	else
	{
		echo json_encode(FALSE);
		die();
	}
}

add_action('wp_ajax_leadin_check_visitor_status', 'leadin_check_visitor_status'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_check_visitor_status', 'leadin_check_visitor_status'); // Call when user is not logged in


/**
 * Grabs the heading for the subscribe widget from the options
 *
 */
function leadin_subscribe_show ()
{
	leadin_track_plugin_activity('widget shown');
	die();

	/*global $wpdb;

	$hash 	= $_POST['li_id'];

	$q = $wpdb->prepare("SELECT lead_status FROM li_leads WHERE hashkey = %s", $hash);
	$lead_status = $wpdb->get_var($q);

	if ( isset($lead_status) )
	{
		echo json_encode($lead_status);
		die();
	}
	else
	{
		echo json_encode(FALSE);
		die();
	}*/


}

add_action('wp_ajax_leadin_subscribe_show', 'leadin_subscribe_show'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_subscribe_show', 'leadin_subscribe_show'); // Call when user is not logged in
?>