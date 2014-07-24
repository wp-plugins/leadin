<?php
/*
Plugin Name: LeadIn
Plugin URI: http://leadin.com
Description: LeadIn is an easy-to-use marketing automation and lead tracking plugin for WordPress that helps you better understand your web site visitors.
Version: 1.3.0
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
	define('LEADIN_DB_VERSION', '1.3.0');

if ( !defined('LEADIN_PLUGIN_VERSION') )
	define('LEADIN_PLUGIN_VERSION', '1.3.0');

if ( !defined('MIXPANEL_PROJECT_TOKEN') )
    define('MIXPANEL_PROJECT_TOKEN', 'a9615503ec58a6bce2c646a58390eac1');

//=============================================
// Include Needed Files
//=============================================

require_once(LEADIN_PLUGIN_DIR . '/admin/leadin-admin.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/class-emailer.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/leadin-ajax-functions.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/leadin-functions.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/class-leadin-updater.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/subscribe-widget.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/contacts.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/mailchimp-list-sync.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/constant-contact-list-sync.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/beta-program.php');
require_once(LEADIN_PLUGIN_DIR . '/lib/mixpanel/LI_Mixpanel.php');

//=============================================
// WPLeadIn Class
//=============================================
class WPLeadIn {
	
	var $power_ups;
	var $options;

	/**
	 * Class constructor
	 */
	function __construct ()
	{
		//=============================================
		// Hooks & Filters
		//=============================================

		// Activate + install LeadIn
		register_activation_hook( __FILE__, array(&$this, 'add_leadin_defaults'));

		// Deactivate LeadIn
		register_deactivation_hook( __FILE__, array(&$this, 'deactivate_leadin'));

		$this->power_ups = $this->get_available_power_ups();
		$this->options = get_option('leadin_options');

		add_action('plugins_loaded', array($this, 'leadin_update_check'));
		add_filter('init', array($this, 'add_leadin_frontend_scripts'));

		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$li_wp_admin, 'leadin_plugin_settings_link'));
		add_action( 'admin_bar_menu', array($this, 'add_leadin_link_to_admin_bar'), 999 );

		$li_wp_admin = new WPLeadInAdmin($this->power_ups);

		if ( isset($this->options['beta_tester']) && $this->options['beta_tester'] )
			$li_wp_updater = new WPLeadInUpdater();

		global $wpdb;
		$wpdb->multisite_query = ( is_multisite() ? $wpdb->prepare(" AND blog_id = %d ", $wpdb->blogid) : "" );
	}

	/**
	 * Activate the plugin
	 */
	function add_leadin_defaults ()
	{
		$options = $this->options;

		if ( ($options['li_installed'] != 1) || (!is_array($options)) )
		{
			$opt = array(
				'li_installed'				=> 1,
				'li_db_version'				=> LEADIN_DB_VERSION,
				'li_email' 					=> get_bloginfo('admin_email'),
				'onboarding_complete'		=> 0,
				'ignore_settings_popup'		=> 0,
				'data_recovered'			=> 1,
				'delete_flags_fixed'		=> 1,
				'beta_tester'				=> 0
			);
			
			update_option('leadin_options', $opt);
			$this->leadin_db_install();
		}

		$leadin_active_power_ups = get_option('leadin_active_power_ups');

		if ( !$leadin_active_power_ups )
		{
			$auto_activate = array(
				'contacts',
				'beta_program'
			);

			update_option('leadin_active_power_ups', serialize($auto_activate));
		}

        leadin_track_plugin_registration_hook(TRUE);
	}

	/**
	 * Deactivate LeadIn plugin hook
	 */
	function deactivate_leadin ()
	{
		leadin_track_plugin_registration_hook(FALSE);
	}

	//=============================================
	// Database update
	//=============================================

	/**
	 * Creates or updates the LeadIn tables
	 */
	function leadin_db_install ()
	{
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		$sql = "
			CREATE TABLE `li_leads` (
			  `lead_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `lead_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  `hashkey` varchar(16) DEFAULT NULL,
			  `lead_ip` varchar(40) DEFAULT NULL,
			  `lead_source` text,
			  `lead_email` varchar(255) DEFAULT NULL,
			  `lead_status` set('contact','lead','comment','subscribe','contacted','customer') NOT NULL DEFAULT 'contact',
			  `merged_hashkeys` text,
			  `lead_deleted` int(1) NOT NULL DEFAULT '0',
			  `blog_id` int(11) unsigned NOT NULL,
			  PRIMARY KEY (`lead_id`),
			  KEY `hashkey` (`hashkey`)
			) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;

			CREATE TABLE `li_pageviews` (
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

			CREATE TABLE `li_submissions` (
			  `form_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `form_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  `lead_hashkey` varchar(16) NOT NULL,
			  `form_page_title` varchar(255) NOT NULL,
			  `form_page_url` text NOT NULL,
			  `form_fields` text NOT NULL,
			  `form_type` set('contact','comment','subscribe') NOT NULL DEFAULT 'contact',
			  `form_selector_id` mediumtext NOT NULL,
			  `form_selector_classes` mediumtext NOT NULL,
			  `form_hashkey` varchar(16) NOT NULL,
			  `form_deleted` int(1) NOT NULL DEFAULT '0',
			  `blog_id` int(11) unsigned NOT NULL,
			  PRIMARY KEY (`form_id`),
			  KEY `lead_hashkey` (`lead_hashkey`)
			) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";

		dbDelta($sql);

	    leadin_update_option('leadin_options', 'li_db_version', LEADIN_DB_VERSION);
	}

	/**
	 * Checks the stored database version against the current data version + updates if needed
	 */
	function leadin_update_check ()
	{
	    global $wpdb;
	    $options = $this->options;

	    // If the plugin version matches the latest version escape the update function
	    if ( isset ($options['leadin_version']) && $options['leadin_version'] == LEADIN_PLUGIN_VERSION )
	    	return FALSE;

        // 0.5.1 upgrade - Create active power-ups option if it doesn't exist
        $leadin_active_power_ups = get_option('leadin_active_power_ups');

		if ( !$leadin_active_power_ups )
		{
			$auto_activate = array(
				'contacts',
				'beta_program'
			);

			update_option('leadin_active_power_ups', serialize($auto_activate));
		}
		else
		{
			// 0.9.2 upgrade - set beta program power-up to auto-activate
			$activated_power_ups = unserialize($leadin_active_power_ups);

			// 0.9.3 bug fix for dupliate beta_program values being stored in the active power-ups array
			if ( !in_array('beta_program', $activated_power_ups) )
			{
				$activated_power_ups[] = 'beta_program';
				update_option('leadin_active_power_ups', serialize($activated_power_ups));
			}
			else 
			{
				$tmp = array_count_values($activated_power_ups);
				$count = $tmp['beta_program'];

				if ( $count > 1 )
				{
					$activated_power_ups = array_unique($activated_power_ups);
					update_option('leadin_active_power_ups', serialize($activated_power_ups));
				}
			}

			update_option('leadin_active_power_ups', serialize($activated_power_ups));
		}

		// 0.7.2 bug fix - data recovery algorithm for deleted contacts
		if ( ! isset($options['data_recovered']) )
		{
			leadin_recover_contact_data();
		}

		// Set the database version if it doesn't exist
	    if ( isset($options['li_db_version']) )
	    {
	    	if ( $options['li_db_version'] != LEADIN_DB_VERSION ) 
	    	{
	        	$this->leadin_db_install();

		    	// 1.1.0 upgrade - After the DB installation converts the set structure from contact to lead, update all the blank form_type = leads
		    	$q = $wpdb->prepare("UPDATE li_submissions SET form_type = 'contact' WHERE form_type = 'lead' OR form_type = ''" . $wpdb->multisite_query, "");
		    	$wpdb->query($q);
	    	}
	    }
	    else
	    {
	    	$this->leadin_db_install();
	    }

	    // 0.8.3 bug fix - bug fix for duplicated contacts that should be merged
		if ( ! isset($options['delete_flags_fixed']) )
		{
			leadin_delete_flag_fix();
		}

		// Set the plugin version
	    leadin_update_option('leadin_options', 'leadin_version', LEADIN_PLUGIN_VERSION);
	}

	//=============================================
	// Scripts & Styles
	//=============================================

	/**
	 * Adds front end javascript + initializes ajax object
	 */
	function add_leadin_frontend_scripts ()
	{
		if ( !is_admin() )
		{
			wp_register_script('leadin-tracking', LEADIN_PATH . '/assets/js/build/leadin-tracking.min.js', array ('jquery'), FALSE, TRUE);
			wp_enqueue_script('leadin-tracking');

			// replace https with http for admin-ajax calls for SSLed backends
			wp_localize_script('leadin-tracking', 'li_ajax', array('ajax_url' => str_replace('https:', 'http:', admin_url('admin-ajax.php'))));
		}
	}

	/**
     * Adds LeadIn link to top-level admin bar
     */
	function add_leadin_link_to_admin_bar( $wp_admin_bar ) {
		global $wp_version;

		$args = array(
			'id'     => 'leadin-admin-menu',     // id of the existing child node (New > Post)
			'title'  => '<span class="ab-icon" '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? ' style="margin-top: 3px;"' : ''). '><img src="/wp-content/plugins/leadin/images/leadin-svg-icon.svg" style="height:16px; width:16px;"></span><span class="ab-label">LeadIn</span>', // alter the title of existing node
			'parent' => FALSE,	 // set parent to false to make it a top level (parent) node
			'href' => get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts',
			'meta' => array('title' => 'LeadIn')
		);

		$wp_admin_bar->add_node( $args );
	}

	/**
     * List available power-ups
     */
    public static function get_available_power_ups( $min_version = FALSE, $max_version = FALSE ) {
        static $power_ups = null;

        if ( ! isset( $power_ups ) ) {
            $files = WPLeadIn::glob_php( LEADIN_PLUGIN_DIR . '/power-ups' );

            $power_ups = array();

            foreach ( $files as $file ) {

                if ( ! $headers = WPLeadIn::get_power_up($file) ) {
					continue;
				}

				$power_up = new $headers['class']($headers['activated']);
				$power_up->power_up_name 	= $headers['name'];
				$power_up->menu_text 		= $headers['menu_text'];
				$power_up->menu_link 		= $headers['menu_link'];
				$power_up->slug 			= $headers['slug'];
				$power_up->link_uri 		= $headers['uri'];
				$power_up->description 		= $headers['description'];
				$power_up->icon 			= $headers['icon'];
				$power_up->permanent 		= ( $headers['permanent'] == 'Yes' ? 1 : 0 );
				$power_up->auto_activate 	= ( $headers['auto_activate'] == 'Yes' ? 1 : 0 );
				$power_up->hidden 			= ( $headers['hidden'] == 'Yes' ? 1 : 0 );
				$power_up->activated 		= $headers['activated'];

				// Set the small icons HTML for the settings page
				if ( strstr($headers['icon_small'], 'dashicons') )
					$power_up->icon_small = '<span class="dashicons ' . $headers['icon_small'] . '"></span>';
				else
					$power_up->icon_small = '<img src="' . LEADIN_PATH . '/images/' . $headers['icon_small'] . '.png" class="power-up-settings-icon"/>';

				array_push($power_ups, $power_up);
            }
        }

        return $power_ups;       
    }

    /**
	 * Extract a power-up's slug from its full path.
	 */
	public static function get_power_up_slug ( $file ) {
		return str_replace( '.php', '', basename( $file ) );
	}

	/**
	 * Generate a power-up's path from its slug.
	 */
	public static function get_power_up_path ( $slug ) {
		return LEADIN_PLUGIN_DIR . "/power-ups/$slug.php";
	}

    /**
	 * Load power-up data from power-up file. Headers differ from WordPress
	 * plugin headers to avoid them being identified as standalone
	 * plugins on the WordPress plugins page.
	 *
	 * @param $power_up The file path for the power-up
	 * @return $pu array of power-up attributes
	 */
	public static function get_power_up ( $power_up )
	{
		$headers = array(
			'name'				=> 'Power-up Name',
			'class'				=> 'Power-up Class',
			'menu_text'			=> 'Power-up Menu Text',
			'menu_link'			=> 'Power-up Menu Link',
			'slug'				=> 'Power-up Slug',
			'uri'				=> 'Power-up URI',
			'description'		=> 'Power-up Description',
			'icon'				=> 'Power-up Icon',
			'icon_small'		=> 'Power-up Icon Small',
			'introduced'		=> 'First Introduced',
			'auto_activate'		=> 'Auto Activate',
			'permanent'			=> 'Permanently Enabled',
			'power_up_tags'		=> 'Power-up Tags',
			'hidden'			=> 'Hidden'
		);

		$file = WPLeadIn::get_power_up_path( WPLeadIn::get_power_up_slug( $power_up ) );
		if ( ! file_exists( $file ) )
			return FALSE;

		$pu = get_file_data( $file, $headers );

		if ( empty( $pu['name'] ) )
			return FALSE;

		$pu['activated'] = self::is_power_up_active($pu['slug']);

		return $pu;
	}

    /**
     * Returns an array of all PHP files in the specified absolute path.
     * Equivalent to glob( "$absolute_path/*.php" ).
     *
     * @param string $absolute_path The absolute path of the directory to search.
     * @return array Array of absolute paths to the PHP files.
     */
    public static function glob_php( $absolute_path ) {
        $absolute_path = untrailingslashit( $absolute_path );
        $files = array();
        if ( ! $dir = @opendir( $absolute_path ) ) {
            return $files;
        }

        while ( FALSE !== $file = readdir( $dir ) ) {
            if ( '.' == substr( $file, 0, 1 ) || '.php' != substr( $file, -4 ) ) {
                continue;
            }

            $file = "$absolute_path/$file";

            if ( ! is_file( $file ) ) {
                continue;
            }

            $files[] = $file;
        }

        $files = leadin_sort_power_ups($files, array(
        	LEADIN_PLUGIN_DIR . '/power-ups/contacts.php', 
        	LEADIN_PLUGIN_DIR . '/power-ups/subscribe-widget.php', 
        	LEADIN_PLUGIN_DIR . '/power-ups/mailchimp-list-sync.php', 
        	LEADIN_PLUGIN_DIR . '/power-ups/constant-contact-list-sync.php',
        	LEADIN_PLUGIN_DIR . '/power-ups/beta-program.php'
        ));

        closedir( $dir );

        return $files;
    }

    /**
	 * Check whether or not a LeadIn power-up is active.
	 *
	 * @param string $power_up The slug of a power-up
	 * @return bool
	 *
	 * @static
	 */
	public static function is_power_up_active( $power_up_slug )
	{
		return in_array($power_up_slug, self::get_active_power_ups());
	}

	/**
	 * Get a list of activated modules as an array of module slugs.
	 */
	public static function get_active_power_ups ()
	{
		$activated_power_ups = get_option('leadin_active_power_ups');
		if ( $activated_power_ups )
			return array_unique(unserialize($activated_power_ups));
		else
			return array();
	}

	public static function activate_power_up( $power_up_slug, $exit = TRUE )
	{
		if ( ! strlen( $power_up_slug ) )
			return FALSE;

		// If it's already active, then don't do it again
		$active = self::is_power_up_active($power_up_slug);
		if ( $active )
			return TRUE;

		$activated_power_ups = get_option('leadin_active_power_ups');
		
		if ( $activated_power_ups )
		{
			$activated_power_ups = unserialize($activated_power_ups);
			$activated_power_ups[] = $power_up_slug;
		}
		else
		{
			$activated_power_ups = array($power_up_slug);
		}

		update_option('leadin_active_power_ups', serialize($activated_power_ups));


		if ( $exit )
		{
			exit;
		}

	}

	public static function deactivate_power_up( $power_up_slug, $exit = TRUE )
	{
		if ( ! strlen( $power_up_slug ) )
			return FALSE;

		// If it's already active, then don't do it again
		$active = self::is_power_up_active($power_up_slug);
		if ( ! $active )
			return TRUE;

		$activated_power_ups = get_option('leadin_active_power_ups');
		
		$power_ups_left = leadin_array_delete(unserialize($activated_power_ups), $power_up_slug);
		update_option('leadin_active_power_ups', serialize($power_ups_left));
		
		if ( $exit )
		{
			exit;
		}

	}
}

//=============================================
// LeadIn Init
//=============================================

global $leadin_wp;
global $li_wp_admin;
global $multisite_query;
$leadin_wp = new WPLeadIn();

?>