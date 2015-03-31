<?php
/*
Plugin Name: Leadin
Plugin URI: http://leadin.com
Description: Leadin is an easy-to-use marketing automation and lead tracking plugin for WordPress that helps you better understand your web site visitors.
Version: 3.1.6
Author: Andy Cook, Nelson Joyce
Author URI: http://leadin.com
License: GPL2
*/

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_PATH') )
    define('LEADIN_PATH', untrailingslashit(plugins_url('', __FILE__ )));

if ( !defined('LEADIN_PLUGIN_DIR') )
	define('LEADIN_PLUGIN_DIR', untrailingslashit(dirname( __FILE__ )));

if ( !defined('LEADIN_PLUGIN_SLUG') )
	define('LEADIN_PLUGIN_SLUG', basename(dirname(__FILE__)));

if ( !defined('LEADIN_DB_VERSION') )
	define('LEADIN_DB_VERSION', '2.2.4');

if ( !defined('LEADIN_PLUGIN_VERSION') )
	define('LEADIN_PLUGIN_VERSION', '3.1.6');

if ( !defined('MIXPANEL_PROJECT_TOKEN') )
    define('MIXPANEL_PROJECT_TOKEN', '2791b6942b57bbef2ba12b2452468253');

if ( !defined('SEGMENT_WRITE_KEY') )
    define('SEGMENT_WRITE_KEY', 'fklilck6m2');

if ( !defined('MC_KEY') )
    define('MC_KEY', '934aaed05049dde737d308be26167eef-us3');

if ( !defined('LEADIN_SOURCE') )
    define('LEADIN_SOURCE', 'leadin.com');

//=============================================
// Include Needed Files
//=============================================

if ( file_exists(LEADIN_PLUGIN_DIR . '/inc/leadin-constants.php') )
	include_once(LEADIN_PLUGIN_DIR . '/inc/leadin-constants.php');

require_once(LEADIN_PLUGIN_DIR . '/inc/leadin-ajax-functions.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/leadin-functions.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/class-emailer.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/class-leadin-updater.php');
require_once(LEADIN_PLUGIN_DIR . '/admin/leadin-admin.php');

require_once(LEADIN_PLUGIN_DIR . '/lib/mixpanel/LI_Mixpanel.php');
require_once(LEADIN_PLUGIN_DIR . '/lib/segment/lib/Segment.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/class-leadin.php');

require_once(LEADIN_PLUGIN_DIR . '/power-ups/subscribe-widget.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/contacts.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/mailchimp-connect.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/constant-contact-connect.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/aweber-connect.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/campaign-monitor-connect.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/getresponse-connect.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/beta-program.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/lookups.php'); 



//=============================================
// Hooks & Filters
//=============================================

/**
 * Activate the plugin
 */
function activate_leadin ( $network_wide )
{
	// Check activation on entire network or one blog
	if ( is_multisite() && $network_wide ) 
	{ 
		global $wpdb;
 
		// Get this so we can switch back to it later
		$current_blog = $wpdb->blogid;
		// For storing the list of activated blogs
		$activated = array();
 
		// Get all blogs in the network and activate plugin on each one
		$q = "SELECT blog_id FROM $wpdb->blogs";
		$blog_ids = $wpdb->get_col($q);
		foreach ( $blog_ids as $blog_id ) 
		{
			switch_to_blog($blog_id);
			add_leadin_defaults();
			$activated[] = $blog_id;
			leadin_track_plugin_registration_hook(TRUE);
		}
 
		// Switch back to the current blog
		switch_to_blog($current_blog);
 
		// Store the array for a later function
		update_site_option('leadin_activated', $activated);
	}
	else
	{
		add_leadin_defaults();
		leadin_track_plugin_registration_hook(TRUE);
	}
}

/**
 * Check Leadin installation and set options
 */
function add_leadin_defaults ( )
{
	global $wpdb;

	$options = get_option('leadin_options');

	if ( ($options['li_installed'] != 1) || (!is_array($options)) )
	{
		$opt = array(
			'li_installed'				=> 1,
			'leadin_version'			=> LEADIN_PLUGIN_VERSION,
			'li_db_version'				=> LEADIN_DB_VERSION,
			'li_email' 					=> get_bloginfo('admin_email'),
			'li_updates_subscription'	=> 1,
			'onboarding_step'			=> 1,
			'onboarding_complete'		=> 0,
			'ignore_settings_popup'		=> 0,
			'data_recovered'			=> 1,
			'delete_flags_fixed'		=> 1,
			'beta_tester'				=> 0,
			'converted_to_tags'			=> 1,
			'names_added_to_contacts'	=> 1
		);

		// Add the Pro flag if this is a pro installation
		if ( ( defined('LEADIN_UTM_SOURCE') && LEADIN_UTM_SOURCE != 'leadin%20repo%20plugin' ) || ! defined('LEADIN_UTM_SOURCE') )
			$opt['pro'] = 1;

		// this is a hack because multisite doesn't recognize local options using either update_option or update_site_option...
		if ( is_multisite() )
		{
			$multisite_prefix = ( is_multisite() ? $wpdb->prefix : '' );
			$q = $wpdb->prepare("
				INSERT INTO " . $multisite_prefix . "options 
			        ( option_name, option_value ) 
			    VALUES ('leadin_options', %s)", serialize($opt));
			$wpdb->query($q);
		}
		else
			update_option('leadin_options', $opt);
		
		leadin_db_install();

		$multisite_prefix = ( is_multisite() ? $wpdb->prefix : '' );
		$q = "
			INSERT INTO " . $multisite_prefix . "li_tags 
		        ( tag_text, tag_slug, tag_form_selectors, tag_synced_lists, tag_order ) 
		    VALUES ('Commenters', 'commenters', '#commentform', '', 1),
		        ('Leads', 'leads', '', '', 2),
		        ('Contacted', 'contacted', '', '', 3),
		        ('Customers', 'customers', '', '', 4)";
		$wpdb->query($q);
	}

	$leadin_active_power_ups = get_option('leadin_active_power_ups');

	if ( ! $leadin_active_power_ups )
	{
		$auto_activate = array(
			'contacts',
			'lookups' // 3.1.4 change - auto activating this power-up and using the Pro flag to toggle on/off
		);

		update_option('leadin_active_power_ups', serialize($auto_activate));
	}

	setcookie ( "ignore_social_share" , "1",  2592000, "/" );

	// This is in here because Segment doesn't have register_once, so it's in a method that's only called once
	if ( leadin_check_pro_user() )
	{
		$traits = array();

		if ( defined('LEADIN_REFERRAL_SOURCE') )
			$traits['referral_source'] = LEADIN_REFERRAL_SOURCE;

		if ( defined('LEADIN_UTM_SOURCE') )
			$traits['utm_source'] = LEADIN_UTM_SOURCE;

		if ( defined('LEADIN_UTM_MEDIUM') )
			$traits['utm_medium'] = LEADIN_UTM_MEDIUM;

		if ( defined('LEADIN_UTM_TERM') )
			$traits['utm_term'] = LEADIN_UTM_TERM;

		if ( defined('LEADIN_UTM_CONTENT') )
			$traits['utm_content'] = LEADIN_UTM_CONTENT;

		if ( defined('LEADIN_UTM_CAMPAIGN') )
			$traits['utm_campaign'] = LEADIN_UTM_CAMPAIGN;

	    leadin_set_user_properties($traits);
	}
}

/**
 * Deactivate Leadin plugin hook
 */
function deactivate_leadin ( $network_wide )
{
	if ( is_multisite() && $network_wide ) 
	{ 
		global $wpdb;
 
		// Get this so we can switch back to it later
		$current_blog = $wpdb->blogid;
 
		// Get all blogs in the network and activate plugin on each one
		$q = "SELECT blog_id FROM $wpdb->blogs";
		$blog_ids = $wpdb->get_col($q);
		foreach ( $blog_ids as $blog_id ) 
		{
			switch_to_blog($blog_id);
			leadin_track_plugin_registration_hook(FALSE);
		}
 
		// Switch back to the current blog
		switch_to_blog($current_blog);
	}
	else
		leadin_track_plugin_registration_hook(FALSE);
}

function activate_leadin_on_new_blog ( $blog_id, $user_id, $domain, $path, $site_id, $meta )
{
	global $wpdb;

	if ( is_plugin_active_for_network('leadin/leadin.php') )
	{
		$current_blog = $wpdb->blogid;
		switch_to_blog($blog_id);
		add_leadin_defaults();
		switch_to_blog($current_blog);
	}
}

/**
 * Checks the stored database version against the current data version + updates if needed
 */
function leadin_init ()
{
    $leadin_wp = new WPLeadIn();
}

//=============================================
// Database update
//=============================================

/**
 * Creates or updates the Leadin tables
 */
function leadin_db_install ()
{
	global $wpdb;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
	$multisite_prefix = ( is_multisite() ? $wpdb->prefix : '' );

	$sql = "
		CREATE TABLE " . $multisite_prefix . "li_leads (
		  `lead_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		  `lead_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  `hashkey` varchar(16) DEFAULT NULL,
		  `lead_ip` varchar(40) DEFAULT NULL,
		  `lead_source` text,
		  `lead_email` varchar(255) DEFAULT NULL,
		  `lead_first_name` varchar(255) NOT NULL,
  		  `lead_last_name` varchar(255) NOT NULL,
		  `lead_status` set('contact','lead','comment','subscribe','contacted','customer') NOT NULL DEFAULT 'contact',
		  `merged_hashkeys` text,
		  `lead_deleted` int(1) NOT NULL DEFAULT '0',
		  `blog_id` int(11) unsigned NOT NULL,
		  `company_data` mediumtext NOT NULL,
  		  `social_data` mediumtext NOT NULL,
		  PRIMARY KEY (`lead_id`),
		  KEY `hashkey` (`hashkey`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

		CREATE TABLE " . $multisite_prefix . "li_pageviews (
		  `pageview_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		  `pageview_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  `lead_hashkey` varchar(16) NOT NULL,
		  `pageview_title` varchar(255) NOT NULL,
		  `pageview_url` text NOT NULL,
		  `pageview_source` text NOT NULL,
		  `pageview_session_start` int(1) NOT NULL,
		  `pageview_deleted` int(1) NOT NULL DEFAULT '0',
		  `blog_id` int(11) unsigned NOT NULL,
		  PRIMARY KEY (`pageview_id`),
		  KEY `lead_hashkey` (`lead_hashkey`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

		CREATE TABLE " . $multisite_prefix . "li_submissions (
		  `form_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		  `form_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  `lead_hashkey` varchar(16) NOT NULL,
		  `form_page_title` varchar(255) NOT NULL,
		  `form_page_url` text NOT NULL,
		  `form_fields` text NOT NULL,
		  `form_selector_id` mediumtext NOT NULL,
		  `form_selector_classes` mediumtext NOT NULL,
		  `form_hashkey` varchar(16) NOT NULL,
		  `form_deleted` int(1) NOT NULL DEFAULT '0',
		  `blog_id` int(11) unsigned NOT NULL,
		  PRIMARY KEY (`form_id`),
		  KEY `lead_hashkey` (`lead_hashkey`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

		CREATE TABLE " . $multisite_prefix . "li_tags (
		  `tag_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		  `tag_text` varchar(255) NOT NULL,
		  `tag_slug` varchar(255) NOT NULL,
		  `tag_form_selectors` mediumtext NOT NULL,
		  `tag_synced_lists` mediumtext NOT NULL,
		  `tag_order` int(11) unsigned NOT NULL,
		  `blog_id` int(11) unsigned NOT NULL,
		  `tag_deleted` int(1) NOT NULL,
		  PRIMARY KEY (`tag_id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

		CREATE TABLE " . $multisite_prefix . "li_tag_relationships (
		  `tag_relationship_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		  `tag_id` int(11) unsigned NOT NULL,
		  `contact_hashkey` varchar(16) NOT NULL,
  		  `form_hashkey` varchar(16) NOT NULL,
		  `tag_relationship_deleted` int(1) unsigned NOT NULL,
		  `blog_id` int(11) unsigned NOT NULL,
		  PRIMARY KEY (`tag_relationship_id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";

	dbDelta($sql);

    leadin_update_option('leadin_options', 'li_db_version', LEADIN_DB_VERSION);
}

add_action( 'plugins_loaded', 'leadin_init', 14 );

if ( is_admin() ) 
{
	// Activate + install Leadin
	register_activation_hook( __FILE__, 'activate_leadin');

	// Deactivate Leadin
	register_deactivation_hook( __FILE__, 'deactivate_leadin');

	// Activate on newly created wpmu blog
	add_action('wpmu_new_blog', 'activate_leadin_on_new_blog', 10, 6);
}

?>