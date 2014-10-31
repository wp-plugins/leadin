<?php
/**
	* Power-up Name: Pop-up Form
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
	* Permanently Enabled: No
	* Hidden: No
	* cURL Required: No
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

		$this->options = get_option('leadin_subscribe_options');

		if ( ! is_admin() ) 
		{
			add_action('wp_footer', array(&$this, 'append_leadin_subscribe_settings'));
			add_action('wp_enqueue_scripts', array($this, 'add_leadin_subscribe_frontend_scripts_and_styles'));
		}

		if ( ($this->options['li_susbscibe_installed'] != 1) || (!is_array($this->options)) )
		{
			$this->add_defaults();
		}
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
		global $wpdb;

		$options = $this->options;

		if ( ($options['li_susbscibe_installed'] != 1) || (!is_array($options)) )
		{
			// conditionals below are a hack for not overwriting the users settings with defaults in 0.8.4
			$opt = array(
				'li_susbscibe_installed' 			=> '1',
				'li_subscribe_vex_class' 			=> ( isset($options['li_subscribe_vex_class']) ? $options['li_subscribe_vex_class'] : 'vex-theme-bottom-right-corner'),
				'li_subscribe_heading' 				=> ( isset($options['li_subscribe_heading']) ? $options['li_subscribe_heading'] : 'Sign up for email updates'),
				'li_subscribe_text' 				=> ( isset($options['li_subscribe_text']) ? $options['li_subscribe_text'] : ''),
				'li_subscribe_btn_label' 			=> ( isset($options['li_subscribe_btn_label']) ? $options['li_subscribe_btn_label'] : 'SUBSCRIBE'),
				'li_subscribe_name_fields' 			=> ( isset($options['li_subscribe_name_fields']) ? $options['li_subscribe_name_fields'] : '0'),
				'li_subscribe_phone_field' 			=> ( isset($options['li_subscribe_phone_field']) ? $options['li_subscribe_phone_field'] : '0'),
				'li_subscribe_template_posts' 		=> '1',
				'li_subscribe_template_pages' 		=> '1',
				'li_subscribe_template_archives' 	=> '1',
				'li_subscribe_template_home' 		=> '1'

			);

			update_option('leadin_subscribe_options', $opt);

			// Create smart list for subscribe pop-up
			$q = $wpdb->prepare("SELECT tag_id FROM $wpdb->li_tags WHERE tag_synced_lists LIKE '%%%s%'", ".vex-dialog-form");
			$subscriber_list_exists = $wpdb->get_var($q);

			if ( ! $subscriber_list_exists )
			{
				$tagger = new LI_Tag_Editor();
				$tagger->add_tag('Subscribers', '.vex-dialog-form', '');
			}
		}
	}

	/**
	 * Adds a hidden input at the end of the content containing the ouput of the location, heading, and button text options
	 *
	 */
	function append_leadin_subscribe_settings ()
	{
		$options = $this->options;

	    // Settings for the subscribe plugin injected into footer and pulled via jQuery on the front end
	    echo '<input id="leadin-subscribe-vex-class" value="' . ( isset($options['li_subscribe_vex_class']) ? $options['li_subscribe_vex_class'] : 'vex-theme-bottom-right-corner' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-heading" value="' . ( isset($options['li_subscribe_heading']) ? $options['li_subscribe_heading'] : 'Sign up for email updates' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-text" value="' . ( isset($options['li_subscribe_text']) ? $options['li_subscribe_text'] : '' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-btn-label" value="' . ( isset($options['li_subscribe_btn_label']) ? $options['li_subscribe_btn_label'] : 'SUBSCRIBE' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-name-fields" value="' . ( isset($options['li_subscribe_name_fields']) ? $options['li_subscribe_name_fields'] : '0' )  . '" type="hidden"/>';
	    echo '<input id="leadin-subscribe-phone-field" value="' . ( isset($options['li_subscribe_phone_field']) ? $options['li_subscribe_phone_field'] : '0' )  . '" type="hidden"/>';

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

		$options = $this->options;
		$li_options = get_option('leadin_options');

		if ( ! isset ($options['li_subscribe_template_posts']) && ! isset ($options['li_subscribe_template_pages']) && ! isset ($options['li_subscribe_template_archives']) && ! isset ($options['li_subscribe_template_home']) )
			return FALSE;

		if ( isset ($options['li_subscribe_template_posts']) || isset ($options['li_subscribe_template_pages']) || isset ($options['li_subscribe_template_archives']) || isset ($options['li_subscribe_template_home']) )
		{
			// disable pop-up on posts if setting not set
			if ( is_single() && ! isset ($options['li_subscribe_template_posts']) )
				return FALSE;

			// disable pop-up on pages if setting not set
			if ( is_page() && ! isset ($options['li_subscribe_template_pages']) )
				return FALSE;
			
			// disable pop-up on archives if setting not set
			if ( is_archive() && ! isset ($options['li_subscribe_template_archives']) )
				return FALSE;

			// disable pop-up on homepage if setting not set
			if ( $_SERVER["REQUEST_URI"] == '/' && ! isset ($options['li_subscribe_template_home']) )
				return FALSE;
		}
		


		if ( ! is_admin() && $pagenow != 'wp-login.php' )
		{
			wp_register_script('leadin-subscribe', LEADIN_PATH . '/assets/js/build/leadin-subscribe.min.js', array ('jquery', 'leadin-tracking'), false, true);
			wp_enqueue_script('leadin-subscribe');

			wp_register_style('leadin-subscribe-css', LEADIN_PATH . '/assets/css/build/leadin-subscribe.css');
			wp_enqueue_style('leadin-subscribe-css');

			if ( isset($li_options['premium']) )
			{
				if ( $li_options['premium'] )
				{
					wp_register_style('leadin-subscribe-premium-css', LEADIN_PATH . '/assets/css/build/leadin-subscribe-premium.css');
					wp_enqueue_style('leadin-subscribe-premium-css');
				}
			}
		}
	}
}

//=============================================
// Subscribe Widget Init
//=============================================

global $leadin_subscribe_wp;

?>