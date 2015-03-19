<?php
/**
	* Power-up Name: Contact Lookups
	* Power-up Class: WPLeadInLookups
	* Power-up Menu Text: 
	* Power-up Menu Link: settings
	* Power-up Slug: lookups
	* Power-up URI: http://leadin.com/wordpress-lead-prospecting-plugin/
	* Power-up Description: See social profiles and company information for every contact.
	* Power-up Icon: powerup-icon-lookups
	* Power-up Icon Small: 
	* First Introduced: 2.3.0
	* Power-up Tags: Lookups
	* Auto Activate: No
	* Permanently Enabled: Yes
	* Hidden: No
	* cURL Required: Yes
	* Pro Only: Yes
*/

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_LOOKUPS_PATH') )
    define('LEADIN_LOOKUPS_PATH', LEADIN_PATH . '/power-ups/lookups');

if ( !defined('LEADIN_LOOKUPS_PLUGIN_DIR') )
	define('LEADIN_LOOKUPS_PLUGIN_DIR', LEADIN_PLUGIN_DIR . '/power-ups/lookups');

if ( !defined('LEADIN_LOOKUPS_PLUGIN_SLUG') )
	define('LEADIN_LOOKUPS_PLUGIN_SLUG', basename(dirname(__FILE__)));

//=============================================
// Include Needed Files
//=============================================

require_once(LEADIN_LOOKUPS_PLUGIN_DIR . '/admin/lookups-admin.php');

//=============================================
// WPLeadInLookups Class
//=============================================
class WPLeadInLookups extends WPLeadIn {
	
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

		global $leadin_lookups;
		$leadin_lookups = $this;
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
}

global $leadin_lookups;

?>