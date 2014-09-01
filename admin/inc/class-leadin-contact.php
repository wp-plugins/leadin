<?php
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
				$cur_event = $count;
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event] = array();
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['event_type'] = 'form';
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['event_date'] = $event['event_date'];
				$sessions['session_' . $cur_array]['events']['event_' . $cur_event]['activities'][] = $event;

				// Set the first submission if it's not set and then leave it alone
				if ( ! isset($lead->last_submission) )
					$lead->last_submission = $event['event_date'];

				// Always overwrite the last_submission date which will end as last submission date
				$lead->first_submission = $event['event_date'];

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
				form_date AS event_date, 
				DATE_FORMAT(form_date, %s) AS form_date, 
				form_page_title, 
				form_page_url, 
				form_fields
			FROM 
				$wpdb->li_submissions 
			WHERE 
				form_deleted = 0 AND 
				lead_hashkey = %s ORDER BY event_date DESC", '%b %D %l:%i%p', $hashkey);
		
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
				pageview_date AS event_date,
				DATE_FORMAT(pageview_date, %s) AS pageview_day, 
				DATE_FORMAT(pageview_date, %s) AS pageview_date, 
				lead_hashkey, pageview_title, pageview_url, pageview_source, pageview_session_start 
			FROM 
				$wpdb->li_pageviews 
			WHERE 
				pageview_deleted = 0 AND
				lead_hashkey LIKE %s ORDER BY event_date DESC", '%b %D', '%b %D %l:%i%p', $hashkey);
		
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
				DATE_FORMAT(lead_date, %s) AS lead_date,
				lead_id,
				lead_ip, 
				lead_email
			FROM 
				$wpdb->li_leads 
			WHERE hashkey LIKE %s", '%b %D %l:%i%p', $hashkey);

		$contact_details = $wpdb->get_row($q, $output_type);

		return $contact_details;
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
                lt.tag_text, lt.tag_slug, lt.tag_order, lt.tag_id, ( ltr.tag_id IS NOT NULL AND ltr.tag_relationship_deleted = 0 ) AS tag_set
            FROM 
                $wpdb->li_tags lt
            LEFT OUTER JOIN 
            	$wpdb->li_tag_relationships ltr ON lt.tag_id = ltr.tag_id AND ltr.contact_hashkey = %s
            WHERE 
                lt.tag_deleted = 0 
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

						${$power_up_global}->push_contact_to_list($list['list_id'], $this->history->lead->lead_email);
					}

					array_push($synced_lists, $list['list_id']);
				}
			}
		}

		if ( $tags_to_update )
		{
			$q = $wpdb->prepare("UPDATE $wpdb->li_tag_relationships SET tag_relationship_deleted = 0 WHERE contact_hashkey = %s AND tag_relationship_id IN ( " . rtrim($tags_to_update, ',') . " ) ", $this->hashkey);
			$tag_updated = $wpdb->query($q);
		}

		$q = $wpdb->prepare("UPDATE $wpdb->li_tag_relationships SET tag_relationship_deleted = 1 WHERE contact_hashkey = %s " . ( $safe_tags ? "AND tag_relationship_id NOT IN ( " . rtrim($safe_tags, ',') . " ) " : '' ) . " AND tag_relationship_deleted = 0 ", $this->hashkey);
		$deleted_tags = $wpdb->query($q);
	}

	/**
	 * usort helper function to sort array by event date
	 *
	 * @param	string
	 * @return	array
	 */
	function sort_by_event_date ( $a, $b ) 
	{
		return $a['event_date'] < $b['event_date'];
	}
}
?>