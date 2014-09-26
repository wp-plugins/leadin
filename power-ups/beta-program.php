<?php
/**
	* Power-up Name: Beta Program
	* Power-up Class: WPLeadInBeta
	* Power-up Menu Text: 
	* Power-up Slug: beta_program
	* Power-up Menu Link: settings
	* Power-up URI: http://leadin.com/beta-program
	* Power-up Description: Get early access to product updates in development
	* Power-up Icon: powerup-icon-subscribe
	* Power-up Icon Small: powerup-icon-subscribe
	* First Introduced: 0.9.1
	* Power-up Tags: Lead Generation
	* Auto Activate: Yes
	* Permanently Enabled: Yes
	* Hidden: Yes
	* cURL Required: Yes
*/

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_BETA_PROGRAM_PATH') )
    define('LEADIN_BETA_PROGRAM_PATH', LEADIN_PATH . '/power-ups/beta-program');

if ( !defined('LEADIN_BETA_PROGRAM_PLUGIN_DIR') )
	define('LEADIN_BETA_PROGRAM_PLUGIN_DIR', LEADIN_PLUGIN_DIR . '/power-ups/beta-program');

if ( !defined('LEADIN_BETA_PROGRAM_PLUGIN_SLUG') )
	define('LEADIN_BETA_PROGRAM_SLUG', basename(dirname(__FILE__)));

//=============================================
// Include Needed Files
//=============================================
require_once(LEADIN_BETA_PROGRAM_PLUGIN_DIR . '/admin/beta-program-admin.php');

//=============================================
// WPLeadIn Class
//=============================================
class WPLeadInBeta extends WPLeadIn {
	
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
	}

	/**
	 * Initilizes the admin class and calls the constructor
	 */
	public function admin_init ( )
	{
		$admin_class = get_class($this) . 'Admin';
		$this->admin = new $admin_class($this->icon_small);
	}

	/**
	 * This is called for power-ups that menu-text set and therefore a submenu
	 */
	function power_up_setup_callback ( )
	{
		$this->admin->power_up_setup_callback();
	}
}

//=============================================
// Beta Program Widget Init
//=============================================

global $leadin_beta_program_wp;

?>