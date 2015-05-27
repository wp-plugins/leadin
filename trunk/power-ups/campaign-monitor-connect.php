<?php
/**
    * Power-up Name: Campaign Monitor
    * Power-up Class: LICampaignMonitorConnect
    * Power-up Menu Text: 
    * Power-up Slug: campaign_monitor_connect
    * Power-up Menu Link: settings
    * Power-up URI: http://leadin.com/mailchimp-connect
    * Power-up Description: Push your contacts to Campaign Monitor email lists.
    * Power-up Icon: power-up-icon-campaign-monitor-connect
    * Power-up Icon Small: power-up-icon-campaign-monitor-connect_small
    * First Introduced: 0.7.0
    * Power-up Tags: Newsletter, Email
    * Auto Activate: No
    * Permanently Enabled: No
    * Hidden: No
    * cURL Required: Yes
*/

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_CAMPAIGN_MONITOR_CONNECT_PATH') )
    define('LEADIN_CAMPAIGN_MONITOR_CONNECT_PATH', LEADIN_PATH . '/power-ups/campaign-monitor-connect');

if ( !defined('LEADIN_CAMPAIGN_MONITOR_CONNECT_PLUGIN_DIR') )
    define('LEADIN_CAMPAIGN_MONITOR_CONNECT_PLUGIN_DIR', LEADIN_PLUGIN_DIR . '/power-ups/campaign-monitor-connect');

if ( !defined('LEADIN_CAMPAIGN_MONITOR_CONNECT_PLUGIN_SLUG') )
    define('LEADIN_CAMPAIGN_MONITOR_CONNECT_SLUG', basename(dirname(__FILE__)));

//=============================================
// Include Needed Files
//=============================================
require_once(LEADIN_CAMPAIGN_MONITOR_CONNECT_PLUGIN_DIR . '/admin/campaign-monitor-connect-admin.php');
require_once(LEADIN_CAMPAIGN_MONITOR_CONNECT_PLUGIN_DIR . '/inc/li_campaign_monitor.php');

//=============================================
// WPLeadIn Class
//=============================================
class LICampaignMonitorConnect extends WPLeadIn {
    
    var $admin;
    var $options;
    var $power_option_name = 'leadin_campaign_monitor_connect_options';

    /**
     * Class constructor
     */
    function __construct ( $activated )
    {
        //=============================================
        // Hooks & Filters 
        //=============================================

        if ( ! $activated )
            return false;

        global $leadin_campaign_monitor_connect_wp;
        $leadin_campaign_monitor_connect_wp = $this;
        $this->options = get_option($this->power_option_name);
    }

    public function admin_init ( )
    {
        $admin_class = get_class($this) . 'Admin';
        $this->admin = new $admin_class($this->icon_small);
    }

    function power_up_setup_callback ( )
    {
        $this->admin->power_up_setup_callback();
    }

    /**
     * Activate the power-up and add the defaults
     */
    function add_defaults ()
    {

    }

    /**
     * Adds a subcsriber to a specific list
     *
     * @param   string
     * @param   string
     * @param   string
     * @param   string
     * @param   string
     * @return  int/bool        API status code OR false if api key not set
     */
    function push_contact_to_list ( $list_id = '', $email = '', $first_name = '', $last_name = '', $phone = '' ) 
    {
        if ( isset($this->options['li_cm_api_key']) && $this->options['li_cm_api_key'] && $list_id )
        {
            $cm = new LI_Campaign_Monitor($this->options['li_cm_api_key']);
            $r = $cm->call('subscribers/' . $list_id, 'POST', array( 
                'EmailAddress' => $email, 
                'Name' => $first_name . ' ' . $last_name,
                'Resubscribe' => TRUE
            ));

            if ( $r['code'] <= 400 )
            {
                leadin_track_plugin_activity('Contact Pushed to List', array('esp_connector' => 'campaign_monitor'));
                return TRUE;
            }
            else
                return FALSE;
        }

        return FALSE;
    }

    /**
     * Adds a subcsriber to a specific list
     *
     * @param   string
     * @param   array
     * @return  int/bool        API status code OR false if api key not set
     */
    function bulk_push_contact_to_list ( $list_id = '', $contacts = '' ) 
    {
        /* 
            The majority of our user base doesn't use Campaign Monitor, so we decided not to retroactively sync contacts to the list.
            If people complain, we will respond with a support ticket and ask them to export/import manually.
        */
            
        return FALSE;
    }
}

//=============================================
// ESP Connect Init
//=============================================

global $leadin_campaign_monitor_connect_wp;

?>