<?php

//=============================================
// Include Needed Files
//=============================================

require_once(LEADIN_PLUGIN_DIR . '/power-ups/lookups/admin/inc/blacklist_domains.php');

//=============================================
// LI_Contact Class
//=============================================
class LI_Contact {
	
	/**
	 * Variables
	 */
	var $hashkey;
	var $history;

	/**
	 * Class constructor
	 */
	function __construct () 
	{

	}

	/**
	 * Gets hashkey from lead id
	 *
	 * @param	int
	 * @return	string
	 */
	function set_hashkey_by_id ( $lead_id ) 
	{
		global $wpdb;

		$q = $wpdb->prepare("SELECT hashkey FROM $wpdb->li_leads WHERE lead_id = %d", $lead_id);
		$this->hashkey = $wpdb->get_var($q);
		
		return $this->hashkey;
	}

	/**
	 * Gets contact history from the database (pageviews, form submissions, and lead details)
	 *
	 * @param	string
	 * @return	object 	$history (pageviews_by_session, submission, lead)
	 */
	function get_contact_history () 
	{
		global $wpdb;

		$lead 			= $this->get_contact_details($this->hashkey);	
		$pageviews 		= $this->get_contact_pageviews($this->hashkey, 'ARRAY_A');
		$submissions 	= $this->get_contact_submissions($this->hashkey, 'ARRAY_A');
		$tags 			= $this->get_contact_tags($this->hashkey);

		if ( WPLeadIn::is_power_up_active('lookups') )
		{
			$lead->social_data = $this->get_social_details($lead);
			$lead->company_data = $this->get_company_details($lead);
		}

		// Merge the page views array and submissions array and reorder by date
		$events_array = array_merge($pageviews, $submissions); 
		usort($events_array, array('LI_Contact','sort_by_event_date'));
		
		$sessions = array();
		$cur_array = '0';
		$first_iteration = TRUE;
		$count = 0;
		$cur_event = 0;
		$prev_form_event = FALSE;
		$total_visits = 0;
		$total_pageviews = 0;
		$total_submissions = 0;
		$new_session = TRUE;

		$array_tags = array();
		if ( count($tags) )
		{
			foreach ( $tags as $tag )
			{
				array_push($array_tags, array (
						'form_hashkey' => $tag->form_hashkey, 
						'tag_text' => $tag->tag_text,
						'tag_slug' => $tag->tag_slug
					)
				);
			}
		}
		
		foreach ( $events_array as $event_name => $event )
		{
			// Create a new session array if pageview started a new session
			if ( $new_session )
			{
				$cur_array = $count;

				$sessions['session_' . $cur_array] = array();
				$sessions['session_' . $cur_array]['session_date'] = $event['event_date']; 
				$sessions['session_' . $cur_array]['events'] = array();

				$cur_event = $count;
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event] = array();
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['event_type'] = 'pageview';
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['event_date'] = $event['event_date'];
				
				// Set the first submission if it's not set and then leave it alone
				if ( ! isset($lead->last_visit) )
					$lead->last_visit = $event['event_date'];

				// Used for $lead->total_visits
				$total_visits++;
				$new_session = FALSE;
			}

			// Pageview activity
			if ( !isset($event['form_fields']) )
			{
				$cur_event = $count;
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event] = array();
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['event_type'] = 'pageview';
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['event_date'] = $event['event_date'];
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['activities'][] = $event;
				$total_pageviews++;

				// Always overwrite first_visit which will end as last pageview date
				$lead->first_visit = $event['event_date'];
				$lead->lead_source = $event['pageview_source'];
			}
			else
			{
				// Always overwrite the last_submission date which will end as last submission date
				$lead->first_submission = $event['event_date'];

				$event['form_name'] = 'form';
				if ( $event['form_selector_id'] )
					$event['form_name'] = '#' . $event['form_selector_id'];
				else if ( $event['form_selector_classes'] )
				{
					if ( strstr($event['form_selector_classes'], ',') )
					{
						$classes = explode(',', $event['form_selector_classes']);
						$event['form_name'] = ( isset($classes[0]) ? '.' . $classes[0] : 'form' );
					}
					else
						$event['form_name'] = '.' . $event['form_selector_classes'];
				}

				// Run through all the tags and see if the form_hashkey triggered the tag relationship
				$form_tags = array();
				if ( count($array_tags) )
				{
					foreach ( $array_tags as $at )
					{
						if ( $at['form_hashkey'] == $event['form_hashkey'] )
							array_push($form_tags, $at);
					}
				}

				$cur_event = $count;
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event] = array();
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['event_type'] 	= 'form';
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['event_date'] 	= $event['event_date'];
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['form_name'] 	= $event['form_name'];
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['form_tags'] 	= $form_tags;
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['activities'][] = $event;

				// Set the first submission if it's not set and then leave it alone
				if ( ! isset($lead->last_submission) )
					$lead->last_submission = $sessions['session_' . $cur_array]['events']['event_' . $cur_event];

				// Used for $lead->total_submissions
				$total_submissions++;
			}

			if ( (isset($event['pageview_session_start'])) && $event['pageview_session_start'] )
			{
				$new_session = TRUE;
			}

			$count++;
		}

		$lead->total_visits 		= $total_visits;
		$lead->total_pageviews 		= $total_pageviews;
		$lead->total_submissions 	= $total_submissions;

		$this->history 				= (object)NULL;
		$this->history->submission 	= $submissions[0];
		$this->history->sessions 	= $sessions;
		$this->history->lead 		= $lead;
		$this->history->tags 		= $tags;

		return stripslashes_deep($this->history);
	}

	/**
	 * Gets all the submissions for a contact
	 *
	 * @param	string
	 * @param 	string
	 * @return	array/object
	 */
	function get_contact_submissions ( $hashkey, $output_type = 'OBJECT' )
	{
		global $wpdb;

		$q = $wpdb->prepare("
			SELECT 
				DATE_SUB(form_date, INTERVAL %d HOUR) AS event_date, 
				DATE_FORMAT(DATE_SUB(form_date, INTERVAL %d HOUR), %s) AS form_date, 
				form_page_title, 
				form_hashkey,
				form_page_url, 
				form_fields,
				form_selector_id,
				form_selector_classes
			FROM 
				$wpdb->li_submissions 
			WHERE 
				form_deleted = 0 AND 
				lead_hashkey = %s ORDER BY event_date DESC", $wpdb->db_hour_offset, $wpdb->db_hour_offset, '%b %D %l:%i%p', $hashkey);
		
		$submissions = $wpdb->get_results($q, $output_type);

		return $submissions;
	}

	/**
	 * Gets all the pageviews for a contact
	 *
	 * @param	string
	 * @param 	string
	 * @return	array/object
	 */
	function get_contact_pageviews ( $hashkey, $output_type = 'OBJECT' )
	{
		global $wpdb;

		$q = $wpdb->prepare("
			SELECT 
				pageview_id,
				DATE_SUB(pageview_date, INTERVAL %d HOUR) AS event_date,
				DATE_FORMAT(DATE_SUB(pageview_date, INTERVAL %d HOUR), %s) AS pageview_day, 
				DATE_FORMAT(DATE_SUB(pageview_date, INTERVAL %d HOUR), %s) AS pageview_date, 
				lead_hashkey, pageview_title, pageview_url, pageview_source, pageview_session_start 
			FROM 
				$wpdb->li_pageviews 
			WHERE 
				pageview_deleted = 0 AND
				lead_hashkey LIKE %s ORDER BY event_date DESC", $wpdb->db_hour_offset, $wpdb->db_hour_offset, '%b %D', $wpdb->db_hour_offset, '%b %D %l:%i%p', $hashkey);
		
		$pageviews = $wpdb->get_results($q, $output_type);

		return $pageviews;
	}

	/**
	 * Gets the details row for a contact
	 *
	 * @param	string
	 * @param 	string
	 * @return	array/object
	 */
	function get_contact_details ( $hashkey, $output_type = 'OBJECT' )
	{
		global $wpdb;

		$q = $wpdb->prepare("
			SELECT 
				DATE_FORMAT(DATE_SUB(lead_date, INTERVAL %d HOUR), %s) AS lead_date,
				lead_id,
				lead_ip, 
				lead_email,
				lead_first_name, 
				lead_last_name,
				social_data,
				company_data,
				lead_deleted
			FROM 
				$wpdb->li_leads 
			WHERE hashkey LIKE %s", $wpdb->db_hour_offset, '%b %D %l:%i%p', $hashkey);

		$contact_details = $wpdb->get_row($q, $output_type);

		return $contact_details;
	}

	/**
	 * Sets the social_data on a contact
	 *
	 * @param	object
	 * @return	object
	 */
	function get_social_details ( $lead ) 
	{
		$site_url = site_url();
		$social_data = '';

		if ( ! $lead->social_data )
		{
			// Grab the social intel lookup
			$social_data = json_decode($this->query_social_lookup_endpoint(strtolower($lead->lead_email), $site_url));	

			if ( ! isset($social_data->status) )
			{
				$this->update_social_lookup_data($this->hashkey, serialize($social_data));

				// Update the first and last names if one of them isn't set, and the respected name part is present or the full name is present the full name is present or the correspond
				$first_name = '';
				$last_name 	= '';
				$update_name = FALSE;

				if ( ! $lead->lead_first_name )
				{
					if ( isset($social_data->fullcontactDetails->contactinfo->givenname) )
					{
						$first_name = $social_data->fullcontactDetails->contactinfo->givenname;
						$update_name = TRUE;
					}
					else if ( isset($social_data->fullcontactDetails->contactinfo->fullname) )
					{
						$first_name = reset(explode(' ', $social_data->fullcontactDetails->contactinfo->fullname));
						$update_name = TRUE;
					}
				}
				else
					$first_name = $lead->lead_first_name;

				if ( ! $lead->lead_last_name )
				{
					if ( isset($social_data->fullcontactDetails->contactinfo->familyname) )
					{
						$last_name = $social_data->fullcontactDetails->contactinfo->familyname;
						$update_name = TRUE;
					}
					else if ( isset($social_data->fullcontactDetails->contactinfo->fullname) )
					{
						$last_name = end(explode(' ', $social_data->fullcontactDetails->contactinfo->fullname));
						$update_name = TRUE;
					}						
				}
				else
					$last_name = $lead->lead_first_name;

				if ( $update_name )
					$this->update_contact_full_name($this->hashkey, $first_name, $last_name);

			}
			else if ( isset($social_data->status) && $social_data->status == 'error' )
			{
				$social_data = '';
			}
		}
		else
			$social_data = unserialize($lead->social_data);
		
		if ( isset($social_data->fullcontactDetails) ) 
        {
            $fullcontactDetails = $social_data->fullcontactDetails;

            if ( count($fullcontactDetails->organizations) )
            {
                foreach ( $fullcontactDetails->organizations as $org )
                {
                    if ( isset($org->isprimary) )
                    {
                    	$social_data->properties->primary->company_name = ( isset($org->name) ? $org->name : '' );
                    	$social_data->properties->primary->title 		= ( isset($org->title) ? $org->title : '' );
                    	break;
                    }
                }
            }

            if ( isset($social_data->twitterDetails->description) )
            {
            	if ( $social_data->twitterDetails->description )
                	$social_data->properties->description = $social_data->twitterDetails->description;
            }

            if ( count($fullcontactDetails->socialprofiles) )
            {
            	$social_profiles = array();

	            foreach ( $fullcontactDetails->socialprofiles as $key => $profile )
		        {
		            $whitelisted_profiles = array('twitter', 'facebook', 'linkedin', 'googleplus');
		            if ( in_array($profile->typeid, $whitelisted_profiles) && ! empty($profile->username) )
		            {
		            	$social_profile = (object)NULL;
		            	$social_profile->typename 	= $profile->typename;
		            	$social_profile->url 		= leadin_safe_social_profile_url($profile->url);
		            	$social_profile->typeid 	= $profile->typeid;
		            	$social_profile->username 	= $profile->username;

		                $social_profiles[] = $social_profile;
		            }
		        }

		        if ( count($social_profiles) )
		        {
		        	if ( isset($social_data->properties->social_profiles) )
		        		$social_data->properties->social_profiles = $social_profiles;
		        }
		    }
        }

        return $social_data;
	}

	/**
	 * Sets the company_data on a contact
	 *
	 * @param	object
	 * @return	object
	 */
	function get_company_details ( $lead )
	{
		global $blacklist_freemail_domains;
		global $blacklist_nonmail_domains;

		$site_url 		= site_url();
		$email_domain 	= end(explode('@', $lead->lead_email));
		$leadin_user 	= leadin_get_current_user();
		$company_data 	= '';

		if ( strstr($leadin_user['email'], ',') )
			$leadin_user_email = reset(explode(',', $leadin_user['email']));
		else
			$leadin_user_email = $leadin_user['email'];

		if ( ! in_array($email_domain, $blacklist_nonmail_domains) && ! in_array($email_domain, $blacklist_freemail_domains) )
		{
			if ( ! $lead->company_data )
			{
				// Grab the company intel lookup
				$company_data = json_decode($this->query_company_lookup_endpoint($email_domain, $leadin_user_email, $site_url));
				
				if ( isset($company_data->status) && $company_data->status != 'error' )
				{
					$this->update_company_lookup_data($this->hashkey, serialize($company_data));
				}
				else
				{
				}
			}
			else
			{
				$company_data = unserialize($lead->company_data);
			}
		}

		return $company_data;
	}

	/**
	 * Gets all the tags for a contact
	 *
	 * @param	string
	 * @param 	string
	 * @return	array/object
	 */
	function get_contact_tags ( $hashkey = '', $output_type = 'OBJECT' )
	{
		global $wpdb;

		$q = $wpdb->prepare("
            SELECT 
                lt.tag_text, lt.tag_slug, lt.tag_order, lt.tag_id, ( ltr.tag_id IS NOT NULL AND ltr.tag_relationship_deleted = 0 ) AS tag_set, lt.tag_form_selectors, ltr.form_hashkey
            FROM 
                $wpdb->li_tags lt
            LEFT OUTER JOIN 
            	$wpdb->li_tag_relationships ltr ON lt.tag_id = ltr.tag_id AND ltr.contact_hashkey = %s
            WHERE 
                lt.tag_deleted = 0 
            GROUP BY lt.tag_slug 
            ORDER BY lt.tag_order ASC", $hashkey);

		$tags = $wpdb->get_results($q, $output_type);

		return $tags;
	}

	/**
	 * Set the tags on a contact
	 *
	 * @param	int
	 * @param 	array
	 * @return	bool 	rows deleted or not
	 */
	function update_contact_tags ( $contact_id, $update_tags )
	{
		global $wpdb;

		$esp_power_ups = array(
            'MailChimp'         => 'mailchimp_connect', 
            'Constant Contact'  => 'constant_contact_connect', 
            'AWeber'            => 'aweber_connect', 
            'GetResponse'       => 'getresponse_connect', 
            'MailPoet'          => 'mailpoet_connect', 
            'Campaign Monitor'  => 'campaign_monitor_connect'
        );

		$safe_tags = $tags_to_update = '';

		if ( ! isset($this->hashkey) )
			$this->hashkey = $this->set_hashkey_by_id($contact_id);

		if ( ! isset($this->history) )
			$this->history = $this->get_contact_history();

		$q = $wpdb->prepare("
            SELECT 
                lt.tag_text, lt.tag_slug, lt.tag_order, lt.tag_id, lt.tag_synced_lists, ltr.tag_relationship_id, ltr.tag_relationship_deleted, ( ltr.tag_id IS NOT NULL ) AS tag_set
            FROM 
                $wpdb->li_tags lt
            LEFT OUTER JOIN 
            	$wpdb->li_tag_relationships ltr ON lt.tag_id = ltr.tag_id AND ltr.contact_hashkey = %s
            WHERE 
                lt.tag_deleted = 0 
            ORDER BY lt.tag_order ASC", $this->hashkey);

		$tags = $wpdb->get_results($q);
		

		// Start looping through all the tags that exist
		foreach ( $tags as $tag )
		{
			// Check if the tag is in the list of tags to update and hit the li_tag_relationships table accordingly
			$update_tag = in_array($tag->tag_id, $update_tags);
			if ( $update_tag )
			{
				if ( ! $tag->tag_set )
				{
					$wpdb->insert(
						$wpdb->li_tag_relationships,
						array (
							'tag_id' => $tag->tag_id,
							'contact_hashkey' => $this->hashkey
						),
						array (
							'%d', '%s'
						)
					);

					$safe_tags .= $wpdb->insert_id . ',';

					leadin_track_plugin_activity('Tag Added - Contact Timeline');
				}
				else
				{
					$safe_tags .= $tag->tag_relationship_id . ',';
					$tags_to_update .= $tag->tag_relationship_id . ',';
				}
			}

			$synced_lists 	= array();
			//$removed_lists 	= array();

			// Only sync update contacts are deleted or were newly inserted
			if ( $tag->tag_synced_lists && $update_tag && ( $tag->tag_relationship_deleted || ! $tag->tag_set ) )
			{
				foreach ( unserialize($tag->tag_synced_lists) as $list )
				{
					// Skip syncing this list because the contact is already synced through another list
					if ( in_array($list['list_id'], $synced_lists) )
						continue;

					$power_up_global = 'leadin_' . $list['esp'] . '_connect' . '_wp';
					if ( array_key_exists($power_up_global, $GLOBALS) )
					{
						global ${$power_up_global};

						if ( ! ${$power_up_global}->activated )
							continue;

						${$power_up_global}->push_contact_to_list($list['list_id'], $this->history->lead->lead_email, $this->history->lead->lead_first_name, $this->history->lead->lead_last_name);
					}

					array_push($synced_lists, $list['list_id']);
				}
			}
		}

		if ( $tags_to_update )
		{
			$q = $wpdb->prepare("UPDATE $wpdb->li_tag_relationships SET tag_relationship_deleted = 0 WHERE contact_hashkey = %s AND tag_relationship_id IN ( " . rtrim($tags_to_update, ',') . " ) ", $this->hashkey);
			$tag_updated = $wpdb->query($q);

			leadin_track_plugin_activity('Tag Restored - Contact Timeline');
		}

		$q = $wpdb->prepare("UPDATE $wpdb->li_tag_relationships SET tag_relationship_deleted = 1 WHERE contact_hashkey = %s " . ( $safe_tags ? "AND tag_relationship_id NOT IN ( " . rtrim($safe_tags, ',') . " ) " : '' ) . " AND tag_relationship_deleted = 0 ", $this->hashkey);
		$deleted_tags = $wpdb->query($q);

		if ( $deleted_tags )
		{
			leadin_track_plugin_activity('Tag Removed - Contact Timeline');
		}
	}

	/**
	 * usort helper function to sort array by event date
	 *
	 * @param	string
	 * @return	array
	 */
	function sort_by_event_date ( $a, $b ) 
	{
		$val_a = strtotime($a['event_date']);
		$val_b = strtotime($b['event_date']);

		return $val_a < $val_b;
	}

	/**
	 * Query the social lookup endpoint on Leadin.com
	 *
	 * @param	string
	 * @param	string
	 * @return	array
	 */
	function query_social_lookup_endpoint ( $lookup_email, $caller_domain )
	{
		$api_endpoint = 'http://leadin.com/enrichment/v1/profile/email/email_lookup.php';
		$params =  '?lookup_email=' . $lookup_email . '&caller_domain=' . $caller_domain;

		$curl_handle = curl_init();
        curl_setopt($curl_handle,CURLOPT_URL, $api_endpoint . $params);
        curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
        curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
        $data = curl_exec($curl_handle);
        curl_close($curl_handle);


        return htmlspecialchars_decode($data);
		
	}

	/**
	 * Query the company lookup endpoint on Leadin.com
	 *
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	array
	 */
	function query_company_lookup_endpoint ( $lookup_company_url, $caller_email, $caller_domain )
	{
		$caller_domain = str_replace(array('http://', 'https://'), '', $caller_domain);
		$caller_domain = 'leadin.com';

		$api_endpoint = 'http://leadin.com/enrichment/v1/company/company_lookup.php';
		$params =  '?lookup_company_url=' . $lookup_company_url . '&caller_email=' . $caller_email . '&caller_domain=' . $caller_domain;

		$curl_handle = curl_init();
        curl_setopt($curl_handle,CURLOPT_URL, $api_endpoint . $params);
        curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
        curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
        $data = curl_exec($curl_handle);
        curl_close($curl_handle);


        return htmlspecialchars_decode($data);
	}

	/**
	 * Queries for the company_data and social_data fields on the contact in li_leads
	 *
	 * @param	string
	 * @return	object
	 */
	function get_cached_lookup_data ( $hashkey )
	{
		global $wpdb;

		$q = $wpdb->prepare("SELECT social_data, company_data FROM $wpdb->li_leads WHERE hashkey = %s", $hashkey);
		$result = $wpdb->get_row($q);

		return $result;
	}

	/**
	 * Cache the social lookup data in the database
	 *
	 * @param	string
	 * @param	string 		serialized array
	 * @param	string
	 * @return	bool
	 */
	function update_social_lookup_data ( $hashkey, $social_data )
	{
		global $wpdb;

		$q = $wpdb->prepare("UPDATE $wpdb->li_leads SET social_data = %s WHERE hashkey = %s", $social_data, $hashkey);
		$result = $wpdb->query($q);

		return $result;
	}

	/**
	 * Cache the company lookup data in the database
	 *
	 * @param	string
	 * @param	string 		serialized array
	 * @param	string
	 * @return	bool
	 */
	function update_company_lookup_data ( $hashkey, $company_data )
	{
		global $wpdb;

		$q = $wpdb->prepare("UPDATE $wpdb->li_leads SET company_data = %s WHERE hashkey = %s", $company_data, $hashkey);
		$result = $wpdb->query($q);

		return $result;
	}

	/**
	 * Update the first + last name on a contact row in li_leads
	 *
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	function update_contact_full_name ( $hashkey, $first_name, $last_name )
	{
		global $wpdb;

		$q = $wpdb->prepare("UPDATE $wpdb->li_leads SET lead_first_name = %s, lead_last_name = %s WHERE hashkey = %s", $first_name, $last_name, $hashkey);
		$result = $wpdb->query($q);

		return $result;
	}

	/**
	 * Redirects the user to a merged contact when the current contact has been deleted
	 *
	 * @param	string
	 * 
	 */
	function display_error_message_for_merged_contact ( $lead_email )
	{
		global $wpdb;

		$q = $wpdb->prepare("SELECT lead_id FROM $wpdb->li_leads WHERE lead_email = %s AND lead_deleted = 0 ORDER BY lead_date DESC LIMIT 1", $lead_email);
		$lead_id = $wpdb->get_var($q);

		if ( $lead_id )
		{
			echo '<div style="background: #fff; border-left: 4px solid #dd3d36; padding: 1px 12px; margin-bottom: 20px;" ><p>This contact record was merged with a more recent entry and is out of date... <br/><br/> <a class="button" href="' . get_admin_url() . 'admin.php?page=leadin_contacts&action=view&lead=' . $lead_id . '">View the latest timeline</a></p></div>';
		}
	} 
}
?>