<?php
/**
	* Power-up Name: Contact Sync
	* Power-up Class: WPConstantContactListSync
	* Power-up Menu Text: 
	* Power-up Slug: constant_contact_list_sync
	* Power-up Menu Link: settings
	* Power-up URI: http://leadin.com/constant-contact-list-sync
	* Power-up Description: Sync your subscribers to a Constant Contact email list.
	* Power-up Icon: power-up-icon-constant-contact-list-sync
	* Power-up Icon Small: power-up-icon-constant-contact-list-sync_small
	* First Introduced: 0.8.0
	* Power-up Tags: Newsletter, Email
	* Auto Activate: No
*/

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_CONSTANT_CONTACT_LIST_SYNC_PATH') )
    define('LEADIN_CONSTANT_CONTACT_LIST_SYNC_PATH', LEADIN_PATH . '/power-ups/constant-contact-list-sync');

if ( !defined('LEADIN_CONSTANT_CONTACT_LIST_SYNC_PLUGIN_DIR') )
	define('LEADIN_CONSTANT_CONTACT_LIST_SYNC_PLUGIN_DIR', LEADIN_PLUGIN_DIR . '/power-ups/constant-contact-list-sync');

if ( !defined('LEADIN_CONSTANT_CONTACT_LIST_SYNC_PLUGIN_SLUG') )
	define('LEADIN_CONSTANT_CONTACT_LIST_SYNC_SLUG', basename(dirname(__FILE__)));

if ( !defined('LEADIN_CONSTANT_CONTACT_API_KEY') )
	define('LEADIN_CONSTANT_CONTACT_API_KEY', 'p5hrzdhe2zrwbm76r2u7pvtc');



//=============================================
// Include Needed Files
//=============================================
require_once(LEADIN_CONSTANT_CONTACT_LIST_SYNC_PLUGIN_DIR . '/admin/constant-contact-list-sync-admin.php');
require_once(LEADIN_CONSTANT_CONTACT_LIST_SYNC_PLUGIN_DIR . '/inc/li_constant_contact.php');

//=============================================
// WPLeadIn Class
//=============================================
class WPConstantContactListSync extends WPLeadIn {
	
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

		global $leadin_constant_contact_list_sync_wp;
		$leadin_constant_contact_list_sync_wp = $this;
		$this->options = get_option('leadin_cc_options');
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

	function push_constant_contact_subscriber_to_list ( $email = '', $first_name = '', $last_name = '', $phone = '' ) 
	{
		$options = $this->options;

        $li_cc_subscribers_to_list = ( isset($options['li_cc_subscribers_to_list']) ? $options['li_cc_subscribers_to_list'] : '' );
        
        if ( isset($options['li_cc_email']) && isset($options['li_cc_password']) && $options['li_cc_email'] && $options['li_cc_password'] && $li_cc_subscribers_to_list )
		{
			$this->constant_contact = new LI_ConstantContact($options['li_cc_email'], $options['li_cc_password'], LEADIN_CONSTANT_CONTACT_API_KEY, TRUE);

			$contact = array();

			if ( $email )
				$contact['EmailAddress'] = $email;

			if ( $first_name )
				$contact['FirstName'] = $first_name;

			if ( $last_name )
				$contact['LastName'] = $last_name;

			if ( $phone )
				$contact['HomePhone'] = $phone;

			if ( $phone )
				$contact['WorkPhone'] = $phone;

			$this->constant_contact->add_contact($contact, array($li_cc_subscribers_to_list));
	    }
	}
}

//=============================================
// Subscribe Widget Init
//=============================================

global $leadin_constant_contact_list_sync_wp;

?>