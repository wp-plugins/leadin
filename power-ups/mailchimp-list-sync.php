<?php
/**
	* Power-up Name: MailChimp List Sync
	* Power-up Class: WPMailChimpListSync
	* Power-up Menu Text: 
	* Power-up Slug: mailchimp_list_sync
	* Power-up Menu Link: settings
	* Power-up URI: http://leadin.com/mailchimp-list-sync
	* Power-up Description: Sync your subscribers to a MailChimp email list.
	* Power-up Icon: power-up-icon-mailchimp-list-sync
	* Power-up Icon Small: power-up-icon-mailchimp-list-sync_small
	* First Introduced: 0.7.0
	* Power-up Tags: Newsletter, Email
	* Auto Activate: No
	* Permanently Enabled: No
	* Hidden: No
*/

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_MAILCHIMP_LIST_SYNC_PATH') )
    define('LEADIN_MAILCHIMP_LIST_SYNC_PATH', LEADIN_PATH . '/power-ups/mailchimp-list-sync');

if ( !defined('LEADIN_MAILCHIMP_LIST_SYNC_PLUGIN_DIR') )
	define('LEADIN_MAILCHIMP_LIST_SYNC_PLUGIN_DIR', LEADIN_PLUGIN_DIR . '/power-ups/mailchimp-list-sync');

if ( !defined('LEADIN_MAILCHIMP_LIST_SYNC_PLUGIN_SLUG') )
	define('LEADIN_MAILCHIMP_LIST_SYNC_SLUG', basename(dirname(__FILE__)));

//=============================================
// Include Needed Files
//=============================================
require_once(LEADIN_MAILCHIMP_LIST_SYNC_PLUGIN_DIR . '/admin/mailchimp-list-sync-admin.php');
require_once(LEADIN_MAILCHIMP_LIST_SYNC_PLUGIN_DIR . '/inc/MailChimp-API.php');

//=============================================
// WPLeadIn Class
//=============================================
class WPMailChimpListSync extends WPLeadIn {
	
	var $admin;
	var $options;

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

		global $leadin_mailchimp_list_sync_wp;
		$leadin_mailchimp_list_sync_wp = $this;
		$this->options = get_option('leadin_mls_options');
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

	function push_mailchimp_subscriber_to_list ( $email = '', $first_name = '', $last_name = '', $phone = '' ) 
	{
		$options = $this->options;

		if ( isset($options['li_mls_api_key']) && $options['li_mls_api_key']  && isset($options['li_mls_subscribers_to_list']) && $options['li_mls_subscribers_to_list'] )
		{
	        $MailChimp = new LI_MailChimp($options['li_mls_api_key']);

	        $subscribe = $MailChimp->call("lists/subscribe", array(
				"id" => $options['li_mls_subscribers_to_list'],
				"email" => array( 'email' => $email),
				"send_welcome" => FALSE,
				"email_type" => 'html',
				"update_existing" => TRUE,
				'replace_interests' => FALSE,
				'double_optin' => FALSE,
				"merge_vars" => array(
				    'EMAIL' => $email,
				    'FNAME' => $first_name,
				    'LNAME' => $last_name,
				    'PHONE' => $phone
				)
			));
	    }
	}
}

//=============================================
// Subscribe Widget Init
//=============================================

global $leadin_mailchimp_list_sync_wp;

?>