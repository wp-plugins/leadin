<?php

//=============================================
// Include Needed Files
//=============================================

include_once(LEADIN_PLUGIN_DIR . '/admin/inc/sources/Snowplow/RefererParser/LI_Parser.php');
include_once(LEADIN_PLUGIN_DIR . '/admin/inc/sources/Snowplow/RefererParser/LI_Referer.php');
include_once(LEADIN_PLUGIN_DIR . '/admin/inc/sources/Snowplow/RefererParser/LI_Medium.php');

//=============================================
// WPStatsDashboard Class
//=============================================
class LI_StatsDashboard {

    /**
     * Variables
     */
    var $total_contacts     = 0;
    var $total_new_contacts     = 0;
    var $best_day_ever      = 0;
    var $avg_contacts_last_90_days  = 0;
    var $total_contacts_last_30_days = 0;
    var $total_contacts_last_90_days = 0;
    var $total_returning_contacts = 0;
    var $max_source = 0;
    
    /**
     * Arrays
     */
    var $returning_contacts;
    var $new_contacts;
    
    /**
     * Sources counts
     */
	var $organic_count 	= 0;
	var $referral_count = 0;
	var $social_count 	= 0;
	var $email_count 	= 0;
	var $paid_count 	= 0;
	var $direct_count 	= 0;

	var $x_axis_labels 	= '';
	var $column_colors 	= '';
	var $column_data 	= '';
	var $average_data 	= '';
	var $weekend_column_data = '';

	var $parser;

	function __construct ()
	{
		$this->parser = new LI_Parser();
		$this->get_data_last_30_days_graph();
		$this->get_sources();
		
		$this->returning_contacts = $this->get_returning_contacts();
		$this->total_returning_contacts = count($this->returning_contacts);

		$this->new_contacts = $this->get_new_contacts();
		$this->total_new_contacts = count($this->new_contacts);
	}

	function get_data_last_30_days_graph ()
	{
		global $wpdb;
		$q = "SELECT DATE(lead_date) as lead_date, COUNT(DISTINCT hashkey) contacts FROM li_leads WHERE lead_email != '' GROUP BY DATE(lead_date)";
		$contacts = $wpdb->get_results($q);

		for ( $i = count($contacts); $i >= 0; $i-- )
		{
			$this->total_contacts += ( $contacts[$i]->contacts ? $contacts[$i]->contacts : 0);
			$this->best_day_ever = ( $contacts[$i]->contacts && $contacts[$i]->contacts > $this->best_day_ever ? $contacts[$i]->contacts : $this->best_day_ever);
		}

        for ( $i = 90; $i >= 0; $i-- )
        {
            $array_key = leadin_search_object_by_value($contacts, date('Y-m-d', strtotime('-'. $i .' days')), 'lead_date');
            $this->total_contacts_last_90_days += ( $array_key ? $contacts[$array_key]->contacts : 0);
        }

        $this->avg_contacts_last_90_days = floor($this->total_contacts_last_90_days/90);

		for ( $i = 31; $i >= 0; $i-- )
		{
			$array_key = leadin_search_object_by_value($contacts, date('Y-m-d', strtotime('-'. $i .' days')), 'lead_date');
			
			$this->total_contacts_last_30_days += ( $array_key ? $contacts[$array_key]->contacts : 0);

			// x axis labels
			$this->x_axis_labels .= "'" . strtoupper(date('M j', strtotime('-'. $i .' days'))) . "'". ( $i != 0 ? "," : "" );

			// colors for chart columns
			$this->column_colors .= ( $array_key && $contacts[$array_key]->contacts > $this->avg_contacts_last_90_days ? "'#9de596'" : "'#d8d8d8'" ) . ( $i != 0 ? "," : "");

			// column data points
			$this->column_data .= "{ name: '" . strtoupper(date('M j', strtotime('-'. $i .' days'))) . "', y: " . ( $array_key ? $contacts[$array_key]->contacts : '0' ) . " }" . ( $i != 0 ? ", " : "" );

            // weekend background column points
			if ( leadin_is_weekend(date('M j', strtotime('-'. $i .' days'))) )
				$this->weekend_column_data .= "{ name: '" . strtoupper(date('M j', strtotime('-'. $i .' days'))) . "', color: 'rgba(0,0,0,.05)', y: " . ( $array_key ? $contacts[$array_key]->contacts : '0' ) . " }" . ( $i != 0 ? ", " : "" );
			else
				$this->weekend_column_data .= "{ name: '" . strtoupper(date('M j', strtotime('-'. $i .' days'))) . "', color: 'rgba(0,0,0,0)', y: " . ( $array_key ? $contacts[$array_key]->contacts : '0' ) . " }" . ( $i != 0 ? ", " : "" );		

            // average line
            if ( $this->avg_contacts_last_90_days )
            {
                $this->average_data .= $this->avg_contacts_last_90_days . ( $i != 0 ? "," : "");
            }
		}
	}

	function get_returning_contacts ()
	{
		global $wpdb;

		$q = "
			SELECT 
				DISTINCT lead_hashkey lh,
				lead_id, 
				lead_email, 
				( SELECT COUNT(*) FROM li_pageviews WHERE lead_hashkey = lh ) as pageviews,
				( SELECT MAX(pageview_source) AS pageview_source FROM li_pageviews WHERE lead_hashkey = lh AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS lead_source 
			FROM 
				li_leads, li_pageviews 
			WHERE 
				pageview_date >= CURRENT_DATE() AND 
				li_leads.hashkey = li_pageviews.lead_hashkey AND 
				pageview_deleted = 0 AND lead_email != '' AND lead_deleted = 0";

		return $wpdb->get_results($q);
	}

	function get_new_contacts ()
	{
		global $wpdb;

		$q = "
			SELECT DISTINCT lead_hashkey lh,
				lead_id, 
				lead_email, 
				( SELECT COUNT(*) FROM li_pageviews WHERE lead_hashkey = lh ) as pageviews, 
				( SELECT MAX(pageview_source) AS pageview_source FROM li_pageviews WHERE lead_hashkey = lh AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS lead_source 
			FROM 
				li_leads, li_pageviews 
			WHERE 
				lead_date >= CURRENT_DATE() AND 
				li_leads.hashkey = li_pageviews.lead_hashkey AND 
				pageview_deleted = 0 AND lead_email != '' AND lead_deleted = 0";

		return $wpdb->get_results($q);
	}

	function get_sources ()
	{
		global $wpdb;

		$q = "SELECT hashkey lh,
			( SELECT MAX(pageview_source) AS pageview_source FROM li_pageviews WHERE lead_hashkey = lh AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS lead_source 
		 FROM 
		 	li_leads
		 WHERE 
		 	lead_date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() AND lead_email != ''";

		$contacts = $wpdb->get_results($q);

		foreach ( $contacts as $contact ) 
		{
			$source = $this->check_lead_source($contact->lead_source);

			switch ( $source )
		    {
		    	case 'search' :
		    		$this->organic_count++;
		    	break;

		    	case 'social' :
		    		$this->social_count++;
		    	break;
		    
		    	case 'email' :
		    		$this->email_count++;
		    	break;

		    	case 'referral' :
		    		$this->referral_count++;
		    	break;

		    	case 'paid' :
		    		$this->paid_count++;
		    	break;

		    	case 'direct' :
		    		$this->direct_count++;
		    	break;
		    }
		}

		$this->max_source = max(array($this->organic_count, $this->referral_count, $this->social_count, $this->email_count, $this->paid_count, $this->direct_count));
	}

	function check_lead_source ( $source )
	{
		if ( $source )
		{
			if ( strstr(urldecode($source), 'utm_medium=cpc') || strstr(urldecode($source), 'utm_medium=ppc') )
				return 'paid';

			if ( strstr($source, 'utm_') )
			{
				$url = $source;
				$url_parts = parse_url($url);
				parse_str($url_parts['query'], $path_parts);

				if ( isset($path_parts['adurl']) )
					return 'paid';

				if ( isset($path_parts['utm_medium']) )
				{
					if ( $path_parts['utm_medium'] == 'cpc' || $path_parts['utm_medium'] == 'ppc' )
						return 'paid';

					if ( $path_parts['utm_medium'] == 'social' )
						return 'social';

					if ( $path_parts['utm_medium'] == 'email' )
						return 'email';
				}

				if ( isset($path_parts['utm_source']) )
				{
					if ( strstr($path_parts['utm_source'], 'email') ) 
						return 'email';
				}
			}

			$referer = $this->parser->parse(
			     $source
			);

			if ( $referer->isKnown() )
				return $referer->getMedium();
			else
			    return 'referral';
		}
		else
			return 'direct';
	}

	function print_readable_source ( $source )
	{
		switch ( $source )
	    {
	    	case 'search' :
	    		return 'Organic Search';
	    	break;

	    	case 'social' :
	    		return 'Social Media';
	    	break;
	    
	    	case 'email' :
	    		return 'Email Marketing';
	    	break;

	    	case 'referral' :
	    		return 'Referral';
	    	break;

	    	case 'paid' :
	    		return 'Paid';
	    	break;

	    	case 'direct' :
	    		return 'Direct';
	    	break;
	    }
	}
}

?>