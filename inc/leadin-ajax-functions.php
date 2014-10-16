<?php

if ( !defined('LEADIN_PLUGIN_VERSION') )
{
	header( 'HTTP/1.0 403 Forbidden' );
	die;
}

/**
 * Check if the cookied hashkey has been merged with another contact. If it is, set visitor's cookie to new hashkey
 *
 * @echo	Hashkey from a merged_hashkeys row, FALSE if hashkey does not exist in a merged_hashkeys row
 */
function leadin_check_merged_contact ()
{
	global $wpdb;

	$stale_hash = $_POST['li_id'];

	// Check if hashkey is in a merged contact
	$q = $wpdb->prepare("SELECT hashkey, merged_hashkeys FROM $wpdb->li_leads WHERE merged_hashkeys LIKE '%%%s%%'", like_escape($stale_hash));
	$row = $wpdb->get_row($q);

	if ( isset($row->hashkey) && $stale_hash )
	{
		// One final update to set all the previous pageviews to the new hashkey
		$q = $wpdb->prepare("UPDATE $wpdb->li_pageviews SET lead_hashkey = %s WHERE lead_hashkey = %s", $row->hashkey, $stale_hash);
		$wpdb->query($q);

		// One final update to set all the previous submissions to the new hashkey
		$q = $wpdb->prepare("UPDATE $wpdb->li_submissions SET lead_hashkey = %s WHERE lead_hashkey = %s", $row->hashkey, $stale_hash);
		$wpdb->query($q);

		// Remove the passed hash from the merged hashkeys for the row
		$merged_hashkeys = array_unique(array_filter(explode(',', $row->merged_hashkeys)));
		
		// Delete the stale hash from the merged hashkeys array
		$merged_hashkeys = leadin_array_delete($merged_hashkeys, "'" . $stale_hash . "'");

		$q = $wpdb->prepare("UPDATE $wpdb->li_leads SET merged_hashkeys = %s WHERE hashkey = %s", rtrim(implode(',', $merged_hashkeys), ','), $row->hashkey);
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

	if ( leadin_ignore_logged_in_user() )
		return FALSE;

	$hash 		= $_POST['li_id'];
	$title 		= $_POST['li_title'];
	$url 		= $_POST['li_url'];
	$source 	= ( isset($_POST['li_referrer']) ? $_POST['li_referrer'] : '' );
	$last_visit = ( isset($_POST['li_last_visit']) ? $_POST['li_last_visit'] : 0 );

	$result = $wpdb->insert(
	    $wpdb->li_pageviews,
	    array(
	        'lead_hashkey' 				=> $hash,
	        'pageview_title' 			=> $title,
	      	'pageview_url' 				=> $url,
	      	'pageview_source' 			=> $source,
	      	'pageview_session_start' 	=> ( !$last_visit ? 1 : 0 )
	    ),
	    array(
	        '%s', '%s', '%s', '%s', '%s'
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

	if ( leadin_ignore_logged_in_user() )
		return FALSE;

	$hashkey 	= $_POST['li_id'];
	$ipaddress 	= $_SERVER['REMOTE_ADDR'];
	$source 	= ( isset($_POST['li_referrer']) ? $_POST['li_referrer'] : '' );
	
	$result = $wpdb->insert(
	    $wpdb->li_leads,
	    array(
	        'hashkey' 		=> $hashkey,
	        'lead_ip' 		=> $ipaddress,
	      	'lead_source' 	=> $source
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

	if ( leadin_ignore_logged_in_user() )
		return FALSE;

	$submission_hash 		= $_POST['li_submission_id'];
	$hashkey 				= $_POST['li_id'];
	$page_title 			= $_POST['li_title'];
	$page_url 				= $_POST['li_url'];
	$form_json 				= $_POST['li_fields'];
	$email 					= $_POST['li_email'];
	$first_name 			= $_POST['li_first_name'];
	$last_name 				= $_POST['li_last_name'];
	$phone 					= $_POST['li_phone'];
	$form_selector_id 		= $_POST['li_form_selector_id'];
	$form_selector_classes 	= $_POST['li_form_selector_classes'];
	$options 				= get_option('leadin_options');
	$li_admin_email 		= ( isset($options['li_email']) ) ? $options['li_email'] : '';
	$contact_type 			= 'contact'; // used at bottom of function

	// Check to see if the form_hashkey exists, and if it does, don't run the insert or send the email
	$q = $wpdb->prepare("SELECT form_hashkey FROM $wpdb->li_submissions WHERE form_hashkey = %s AND form_deleted = 0", $submission_hash);
	$submission_hash_exists = $wpdb->get_var($q);

	if ( $submission_hash_exists )
	{
		// The form has been inserted successful so send back a trigger to clear the cached submission cookie on the front end
		return 1;
		exit;
	}

	// Get the contact row tied to hashkey
	$q = $wpdb->prepare("SELECT * FROM $wpdb->li_leads WHERE hashkey = %s AND lead_deleted = 0", $hashkey);
	$contact = $wpdb->get_row($q);

	// Check for existing contacts based on whether the email is present in the contacts table
	$q = $wpdb->prepare("SELECT lead_email, hashkey, merged_hashkeys FROM $wpdb->li_leads WHERE lead_email = %s AND hashkey != %s AND lead_deleted = 0", $email, $hashkey);
	$existing_contacts = $wpdb->get_results($q);
	
	// Setup the string for the existing hashkeys
	$existing_contact_hashkeys = $contact->merged_hashkeys;
	if ( $contact->merged_hashkeys && count($existing_contacts) )
		$existing_contact_hashkeys .= ',';

	// Do some merging if the email exists already in the contact table
	if ( count($existing_contacts) )
	{
		for ( $i = 0; $i < count($existing_contacts); $i++ )
		{
			// Start with the existing contact's hashkeys and create a string containg comma-deliminated hashes
			$existing_contact_hashkeys .= "'" . $existing_contacts[$i]->hashkey . "'";

			// Add any of those existing contact row's merged hashkeys
			if ( $existing_contacts[$i]->merged_hashkeys )
				$existing_contact_hashkeys .= "," . $existing_contacts[$i]->merged_hashkeys;

			// Add a comma delimiter 
			if ( $i != count($existing_contacts)-1 )
				$existing_contact_hashkeys .= ",";
		}

		// Remove duplicates from the array
		$existing_contact_hashkeys = implode(',', array_unique(explode(',', $existing_contact_hashkeys)));

		// Safety precaution - trim any trailing commas
		$existing_contact_hashkeys = rtrim($existing_contact_hashkeys, ',');

		// Update all the previous pageviews to the new hashkey
		$q = $wpdb->prepare("UPDATE $wpdb->li_pageviews SET lead_hashkey = %s WHERE lead_hashkey IN ( $existing_contact_hashkeys )", $hashkey);
		$wpdb->query($q);

		// Update all the previous submissions to the new hashkey
		$q = $wpdb->prepare("UPDATE $wpdb->li_submissions SET lead_hashkey = %s WHERE lead_hashkey IN ( $existing_contact_hashkeys )", $hashkey);
		$wpdb->query($q);

		// Update all the previous submissions to the new hashkey
		$q = $wpdb->prepare("UPDATE $wpdb->li_tag_relationships SET contact_hashkey = %s WHERE contact_hashkey IN ( $existing_contact_hashkeys )", $hashkey);
		$wpdb->query($q);

		// "Delete" all the old leads from the leads table
		$wpdb->query("UPDATE $wpdb->li_leads SET lead_deleted = 1 WHERE hashkey IN ( $existing_contact_hashkeys )");
	}

	// Prevent duplicate form submission entries by deleting existing submissions if it didn't finish the process before the web page refreshed
	$q = $wpdb->prepare("UPDATE $wpdb->li_submissions SET form_deleted = 1 WHERE form_hashkey = %s", $submission_hash);
	$wpdb->query($q);

	// Insert the form fields and hash into the submissions table
	$result = $wpdb->insert(
	    $wpdb->li_submissions,
	    array(
	        'form_hashkey' 			=> $submission_hash,
	        'lead_hashkey' 			=> $hashkey,
	        'form_page_title' 		=> $page_title,
	        'form_page_url' 		=> $page_url,
	        'form_fields' 			=> $form_json,
	        'form_selector_id' 		=> $form_selector_id,
	        'form_selector_classes' => $form_selector_classes
	    ),
	    array(
	        '%s', '%s', '%s', '%s', '%s', '%s', '%s'
	    )
	);

	// Update the contact with the new email, status and merged hashkeys
	$q = $wpdb->prepare("UPDATE $wpdb->li_leads SET lead_email = %s, merged_hashkeys = %s WHERE hashkey = %s", $email, $existing_contact_hashkeys, $hashkey);
	$rows_updated = $wpdb->query($q);

	// Apply the tag relationship to contacts for form id rules
	if ( $form_selector_id )
	{
		$q = $wpdb->prepare("SELECT tag_id, tag_synced_lists FROM $wpdb->li_tags WHERE tag_form_selectors LIKE '%%%s%%' AND tag_deleted = 0", '#' . $form_selector_id);
		$tagged_lists = $wpdb->get_results($q);

		if ( count($tagged_lists) )
		{
			foreach ( $tagged_lists as $list )
			{
				$tag_added = leadin_apply_tag_to_contact($list->tag_id, $contact->hashkey);

				$contact_type = 'tagged contact';
			
				if ( $tag_added && $list->tag_synced_lists )
				{
					foreach ( unserialize($list->tag_synced_lists) as $synced_list )
					{
						// e.g. leadin_constant_contact_connect_wp
						$leadin_esp_wp = 'leadin_' . $synced_list['esp'] . '_connect_wp';
						global ${$leadin_esp_wp};
						
						if ( ${$leadin_esp_wp}->activated )
							${$leadin_esp_wp}->push_contact_to_list($synced_list['list_id'], $email, $first_name, $last_name, $phone);
					}
				}
			}
		}
	}

	// Apply the tag relationship to contacts for class rules
	$form_classes = explode(',', $form_selector_classes);
	foreach ( $form_classes as $class )
	{
		$q = $wpdb->prepare("SELECT tag_id, tag_synced_lists FROM $wpdb->li_tags WHERE tag_form_selectors LIKE '%%%s%%' AND tag_deleted = 0", '.' . $class);
		$tagged_lists = $wpdb->get_results($q);

		if ( count($tagged_lists) )
		{
			foreach ( $tagged_lists as $list )
			{
				$tag_added = leadin_apply_tag_to_contact($list->tag_id, $contact->hashkey);

				$contact_type = 'tagged contact';
			
				if ( $tag_added && $list->tag_synced_lists )
				{
					foreach ( unserialize($list->tag_synced_lists) as $synced_list )
					{
						// e.g. leadin_constant_contact_connect_wp
						$leadin_esp_wp = 'leadin_' . $synced_list['esp'] . '_connect_wp';
						global ${$leadin_esp_wp};

						if ( ${$leadin_esp_wp}->activated )
							${$leadin_esp_wp}->push_contact_to_list($synced_list['list_id'], $email, $first_name, $last_name, $phone);
					}
				}
			}
		}
	}

	$li_emailer = new LI_Emailer();

	if ( $li_admin_email )
		$li_emailer->send_new_lead_email($hashkey); // Send the contact notification email

	if ( strstr($form_selector_classes, 'vex-dialog-form') )
	{
		// Send the subscription confirmation kickback email
		$leadin_subscribe_settings = get_option('leadin_subscribe_options');
		if ( !isset($leadin_subscribe_settings['li_subscribe_confirmation']) || $leadin_subscribe_settings['li_subscribe_confirmation'] )
			$li_emailer->send_subscriber_confirmation_email($hashkey);

		$contact_type = 'subscriber';
	}
	else if ( strstr($form_selector_id, 'commentform') )
		$contact_type = 'comment';

	leadin_track_plugin_activity("New lead", array("contact_type" => $contact_type));

	return $rows_updated;
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

	// SELECT whether the hashkey is tied to the li_tags list that is for the subscriber
	$q = $wpdb->prepare("SELECT contact_hashkey FROM $wpdb->li_tag_relationships ltr, $wpdb->li_tags lt WHERE lt.tag_form_selectors LIKE '%%%s%%' AND lt.tag_id = ltr.tag_id AND ltr.contact_hashkey = %s AND lt.tag_deleted = 0", 'vex-dialog-form', $hash);
	$vex_set = $wpdb->get_var($q);

	if ( $vex_set )
	{
		echo json_encode('vex_set');
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
}

add_action('wp_ajax_leadin_subscribe_show', 'leadin_subscribe_show'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_subscribe_show', 'leadin_subscribe_show'); // Call when user is not logged in

/**
 * Gets post and pages (name + title) for contacts filtering
 *
 * @return	json object
 */
function leadin_get_posts_and_pages ( )
{
	global $wpdb;

	$search_term = $_POST['search_term'];

	$q = $wpdb->prepare("SELECT post_title, post_name FROM " . $wpdb->prefix . "posts WHERE post_status = 'publish' AND ( post_name LIKE '%%%s%%' OR post_title LIKE '%%%s%%' ) GROUP BY post_name ORDER BY post_date DESC LIMIT 25", $search_term, $search_term);
    $wp_posts = $wpdb->get_results($q);

    if ( ! $_POST['search_term'] )
    {
    	$obj_any_page = (Object)Null;
    	$obj_any_page->post_title = $obj_any_page->post_name = 'any page';
    	array_unshift($wp_posts, $obj_any_page);
    }

    echo json_encode($wp_posts);
    die();
}

add_action('wp_ajax_leadin_get_posts_and_pages', 'leadin_get_posts_and_pages'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_get_posts_and_pages', 'leadin_get_posts_and_pages'); // Call when user is not logged in

/**
 * Gets form selectors
 *
 * @return	json object
 */
function leadin_get_form_selectors ( )
{
	global $wpdb;

	$search_term = $_POST['search_term'];
	$tagger = new LI_Tag_Editor();

	// Add in the custom form fields
	$q = "SELECT tag_form_selectors FROM $wpdb->li_tags WHERE tag_form_selectors != ''";
	$tags = $wpdb->get_results($q);

	// Get all the form selectors synced with a list 
	if ( count($tags) )
	{
		foreach ( $tags as $tag )
		{
			foreach ( explode(',', $tag->tag_form_selectors) as $selector )
			{
				if ( ! in_array($selector, $tagger->selectors) && $selector )
					array_push($tagger->selectors, $selector);
			}
		}
	}

	$fuzzy_selectors = array();
	if ( count($tagger->selectors) )
	{
		foreach ( $tagger->selectors as $key => $selector )
		{
			if ( $selector && $_POST['search_term'] )
			{
				if ( strstr($selector, $_POST['search_term']) )
				{
					if ( ! in_array($selector, $fuzzy_selectors) && $selector )
						array_push($fuzzy_selectors, $selector);
				}
			}
		}
	}

	array_unshift($tagger->selectors, 'any form');

	if ( count($fuzzy_selectors) )
		echo json_encode($fuzzy_selectors);
	else
		echo json_encode($tagger->selectors);
    
    die();
}

add_action('wp_ajax_leadin_get_form_selectors', 'leadin_get_form_selectors'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_get_form_selectors', 'leadin_get_form_selectors'); // Call when user is not logged in

?>