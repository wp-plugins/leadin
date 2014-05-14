<?php
//=============================================
// LI_Viewers Class
//=============================================
class LI_Viewers {
	
	/**
	 * Variables
	 */
	var $viewers;
	var $submissions;

	/**
	 * Class constructor
	 */
	function __construct ()
	{

	}

	/**
     * Get identified readers from a url
     * @param string
     * @return array
     */
	function get_identified_viewers ( $pageview_url )
	{
		global $wpdb;
		$q = $wpdb->prepare(
			"SELECT 
				li_leads.lead_email, li_leads.lead_id, MAX(li_pageviews.pageview_date) AS pageview_date
			FROM 
				li_leads, li_pageviews 
			WHERE 
				li_pageviews.pageview_url = %s AND 
				li_pageviews.lead_hashkey = li_leads.hashkey AND 
				li_leads.lead_deleted = 0 AND 
				li_leads.lead_email != '' 
			GROUP BY 
				li_leads.lead_id
			ORDER BY 
				pageview_date DESC", $pageview_url);
		$this->viewers = $wpdb->get_results($q);
		return $this->viewers;
	}

	/**
     * Get identified readers from a url
     * @param string
     * @return array
     */
	function get_submissions ( $pageview_url )
	{
		global $wpdb;
		$q = $wpdb->prepare(
			"SELECT
				li_leads.lead_email, li_leads.lead_id, MAX(li_submissions.form_date) AS form_date
			FROM 
				li_leads, li_submissions
			WHERE 
				li_submissions.form_page_url = %s AND 
				li_submissions.lead_hashkey = li_leads.hashkey AND 
				li_leads.lead_deleted = 0 AND 
				li_submissions.form_deleted = 0 
			GROUP BY 
				li_leads.lead_id
			ORDER BY 
				form_date DESC", $pageview_url);
		$this->submissions = $wpdb->get_results($q);
		return $this->submissions;
	}
}