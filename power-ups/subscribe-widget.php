<?php
/**
	* Power-up Name: Subscribe Pop-up
	* Power-up Class: WPLeadInSubscribe
	* Power-up Menu Text: 
	* Power-up Slug: subscribe_widget
	* Power-up Menu Link: settings
	* Power-up URI: http://leadin.com/pop-subscribe-form-plugin-wordpress
	* Power-up Description: Convert more email subscribers with our pop-up.
	* Power-up Icon: powerup-icon-subscribe
	* Power-up Icon Small: powerup-icon-subscribe
	* First Introduced: 0.4.7
	* Power-up Tags: Lead Generation
	* Auto Activate: Yes
*/

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_SUBSCRIBE_WIDGET_PATH') )
    define('LEADIN_SUBSCRIBE_WIDGET_PATH', LEADIN_PATH . '/power-ups/subscribe-widget');

if ( !defined('LEADIN_SUBSCRIBE_WIDGET_PLUGIN_DIR') )
	define('LEADIN_SUBSCRIBE_WIDGET_PLUGIN_DIR', LEADIN_PLUGIN_DIR . '/power-ups/subscribe-widget');

if ( !defined('LEADIN_SUBSCRIBE_WIDGET_PLUGIN_SLUG') )
	define('LEADIN_SUBSCRIBE_PLUGIN_SLUG', basename(dirname(__FILE__)));

//=============================================
// Include Needed Files
//=============================================
require_once(LEADIN_SUBSCRIBE_WIDGET_PLUGIN_DIR . '/admin/subscribe-widget-admin.php');

//=============================================
// WPLeadIn Class
//=============================================
class WPLeadInSubscribe extends WPLeadIn {
	
	var $admin;

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

		add_filter('init', array($this, 'add_leadin_subscribe_frontend_scripts_and_styles'));

		add_action('get_footer', array(&$this, 'append_leadin_subscribe_settings'));
	}

	public function admin_init ( )
	{
		$admin_class = get_class($this) . 'Admin';
		$this->admin = new $admin_class($this->icon);
	}

	function power_up_setup_callback ( )
	{
		$this->admin->power_up_setup_callback();
	}

	/**
	 * Activate the power-up
	 */
	function add_leadin_subscribe_defaults ()
	{
		$lis_options = get_option('leadin_subscribe_options');

		if ( ($lis_options['li_susbscibe_installed'] != 1) || (!is_array($lis_options)) )
		{
			$opt = array(
				'li_susbscibe_installed' => '1',
				'li_subscribe_vex_class' => 'vex-theme-bottom-right-corner',
				'li_subscribe_heading' => 'Sign up for my newsletter to get new posts by email',
				'li_subscribe_btn_label' => 'SUBSCRIBE',
				'li_subscribe_name_fields' => '0',
				'li_subscribe_phone_field' => '0'

			);

			update_option('leadin_subscribe_options', $opt);
		}
	}

	/**
	 * Adds a hidden input at the end of the content containing the ouput of the location, heading, and button text options
	 *
	 * @return 
	 */
	function append_leadin_subscribe_settings ()
	{
		$lis_options = get_option('leadin_subscribe_options');

	    // Heading for the subscribe plugin
	    echo '<input id="leadin-subscribe-vex-class" value="' . ( isset($lis_options['li_subscribe_vex_class']) ? $lis_options['li_subscribe_vex_class'] : 'vex-theme-bottom-right-corner' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-heading" value="' . ( isset($lis_options['li_subscribe_heading']) ? $lis_options['li_subscribe_heading'] : 'Sign up for my newsletter to get new posts by email' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-btn-label" value="' . ( isset($lis_options['li_subscribe_btn_label']) ? $lis_options['li_subscribe_btn_label'] : 'SUBSCRIBE' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-name-fields" value="' . ( isset($lis_options['li_subscribe_name_fields']) ? $lis_options['li_subscribe_name_fields'] : '0' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-phone-field" value="' . ( isset($lis_options['li_subscribe_phone_field']) ? $lis_options['li_subscribe_phone_field'] : '0' )  . '" type="hidden"/>';

	    // Div checked by media query for mobile
	    echo '<span id="leadin-subscribe-mobile-check"></span>';
	}

	//=============================================
	// Scripts & Styles
	//=============================================

	/**
	 * Adds front end javascript + initializes ajax object
	 */
	function add_leadin_subscribe_frontend_scripts_and_styles ()
	{
		global $pagenow;

		if ( !is_admin() && $pagenow != 'wp-login.php' )
		{
			wp_register_script('leadin-subscribe', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/js/leadin-subscribe.js', array ('jquery', 'leadin'), false, true);
			wp_register_script('vex', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/js/vex.js', array ('jquery', 'leadin'), false, true);
			wp_register_script('vex-dialog', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/js/vex.dialog.js', array ('jquery', 'leadin'), false, true);

			wp_enqueue_script('leadin-subscribe');
			wp_enqueue_script('vex');
			wp_enqueue_script('vex-dialog');

			wp_register_style('leadin-subscribe-vex-css', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/css/vex.css');
			wp_enqueue_style('leadin-subscribe-vex-css');

			wp_register_style('leadin-subscribe', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/css/leadin-subscribe.css');
			wp_enqueue_style('leadin-subscribe');

			//wp_localize_script('leadin', 'li_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
		}
	}
}

//=============================================
// Subscribe Widget Init
//=============================================

global $leadin_subscribe_wp;

?>