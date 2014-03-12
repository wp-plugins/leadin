<?php
/*
Plugin Name: LeadIn
Plugin URI: http://leadin.com
Description: LeadIn is an easy-to-use marketing automation and lead tracking plugin for WordPress that helps you better understand your web site visitors.
Version: 0.6.1
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
	define('LEADIN_DB_VERSION', '0.4.3');

if ( !defined('LEADIN_PLUGIN_VERSION') )
	define('LEADIN_PLUGIN_VERSION', '0.6.1');

if ( !defined('MIXPANEL_PROJECT_TOKEN') )
    define('MIXPANEL_PROJECT_TOKEN', 'a9615503ec58a6bce2c646a58390eac1');


//=============================================
// Include Needed Files
//=============================================

require_once(LEADIN_PLUGIN_DIR . '/admin/leadin-admin.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/class-emailer.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/leadin-ajax-functions.php');
require_once(LEADIN_PLUGIN_DIR . '/inc/leadin-functions.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/subscribe-widget.php');
require_once(LEADIN_PLUGIN_DIR . '/power-ups/contacts.php');
require_once(LEADIN_PLUGIN_DIR . '/lib/mixpanel/Mixpanel.php');

//=============================================
// WPLeadIn Class
//=============================================
class WPLeadIn {
	
	var $power_ups;

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

		add_action('plugins_loaded', array($this, 'leadin_update_check'));
		add_filter('init', array($this, 'add_leadin_frontend_scripts'));

		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$li_wp_admin, 'leadin_plugin_settings_link'));
		add_action( 'admin_bar_menu', array($this, 'add_leadin_link_to_admin_bar'), 999 );

		$li_wp_admin 	= new WPLeadInAdmin($this->power_ups);
	}

	/**
	 * Activate the plugin
	 */
	function add_leadin_defaults ()
	{
		$li_options = get_option('leadin_options');

		if ( ($li_options['li_installed'] != 1) || (!is_array($li_options)) )
		{
			$opt = array(
				'li_installed'	=> 1,
				'li_db_version'	=> LEADIN_DB_VERSION,
				'li_email' 		=> get_bloginfo('admin_email'),
				'onboarding_complete'	=> 0,
				'ignore_settings_popup'	=> 0
			);
			
			update_option('leadin_options', $opt);
			$this->leadin_db_install();
		}

		$leadin_active_power_ups = get_option('leadin_active_power_ups');

		if ( !$leadin_active_power_ups )
		{
			$auto_activate = array(
				'contacts'
			);

			update_option('leadin_active_power_ups', serialize($auto_activate));
		}

		// 0.4.0 upgrade - Delete legacy db option version 0.4.0 (remove after beta testers upgrade)
        if ( get_option('leadin_db_version') )
            delete_option('leadin_db_version');

		// 0.4.0 upgrade - Delete legacy options version 0.4.0 (remove after beta testers upgrade)
        if ( $li_legacy_options = get_option('leadin_plugin_options') )
        {
        	leadin_update_option('leadin_options', 'li_email', $li_legacy_options['li_email']);
            delete_option('leadin_plugin_options');
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
			CREATE TABLE li_pageviews (
			  pageview_id int(11) unsigned NOT NULL AUTO_INCREMENT,
			  pageview_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  lead_hashkey varchar(16) NOT NULL,
			  pageview_title varchar(255) NOT NULL,
			  pageview_url text NOT NULL,
			  pageview_source text NOT NULL,
			  pageview_session_start int(1) NOT NULL,
			  PRIMARY KEY  (pageview_id)
			) CHARACTER SET utf8 COLLATE utf8_general_ci;

			CREATE TABLE li_leads (
			  lead_id int(11) unsigned NOT NULL AUTO_INCREMENT,
			  lead_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  hashkey varchar(16) NOT NULL,
			  lead_ip varchar(40) NOT NULL,
			  lead_source text NOT NULL,
			  lead_email varchar(255) NOT NULL,
			  lead_status SET( 'lead', 'comment', 'subscribe' ) NOT NULL DEFAULT 'lead',
			  merged_hashkeys text NOT NULL,
			  PRIMARY KEY  (lead_id)
			) CHARACTER SET utf8 COLLATE utf8_general_ci;

			CREATE TABLE li_submissions (
			  form_id int(11) unsigned NOT NULL AUTO_INCREMENT,
			  form_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  form_hashkey varchar(16) NOT NULL,
			  lead_hashkey varchar(16) NOT NULL,
			  form_page_title varchar(255) NOT NULL,
			  form_page_url text NOT NULL,
			  form_fields text NOT NULL,
			  form_type SET( 'lead', 'comment', 'subscribe' ) NOT NULL DEFAULT 'lead',
			  PRIMARY KEY  (form_id)
			) CHARACTER SET utf8 COLLATE utf8_general_ci;";

		dbDelta($sql);

	    leadin_update_option('leadin_options', 'li_db_version', LEADIN_DB_VERSION);
	}

	/**
	 * Checks the stored database version against the current data version + updates if needed
	 */
	function leadin_update_check ()
	{
	    global $wpdb;
	    $li_options = get_option('leadin_options');

	    // 0.4.0 upgrade - Delete legacy db option version 0.4.0 (remove after beta is launched)
        if ( get_option('leadin_db_version') )
            delete_option('leadin_db_version');

		// 0.4.0 upgrade - Delete legacy options version 0.4.0 (remove after beta is launched)
        if ( $li_legacy_options = get_option('leadin_plugin_options') )
        {
        	leadin_update_option('leadin_options', 'li_email', $li_legacy_options['li_email']);
            delete_option('leadin_plugin_options');
        }

        $leadin_active_power_ups = get_option('leadin_active_power_ups');

		if ( !$leadin_active_power_ups )
		{
			$auto_activate = array(
				'contacts'
			);

			update_option('leadin_active_power_ups', serialize($auto_activate));
		}

	    if ( isset($li_options['li_db_version'])  )
	    {
	    	if ( $li_options['li_db_version'] != LEADIN_DB_VERSION ) {
	        	$this->leadin_db_install();
	    	}
	    }

	    // 0.4.2 upgrade - After the DB installation converts the set structure from contact to lead, update all the blank contacts = leads
    	$q = $wpdb->prepare("UPDATE li_leads SET lead_status = 'lead' WHERE lead_status = 'contact' OR lead_status = ''", "");
    	$wpdb->query($q);

    	// 0.4.2 upgrade - After the DB installation converts the set structure from contact to lead, update all the blank form_type = leads
    	$q = $wpdb->prepare("UPDATE li_submissions SET form_type = 'lead' WHERE form_type = 'contact' OR form_type = ''", "");
    	$wpdb->query($q);
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
			wp_register_script('leadin', LEADIN_PATH . '/frontend/js/leadin.js', array ('jquery'), false, true);
			wp_register_script('jquery.cookie', LEADIN_PATH . '/frontend/js/jquery.cookie.js', array ('jquery'), false, true);
			
			wp_enqueue_script('leadin');
			wp_enqueue_script('jquery.cookie');
			
			wp_localize_script('leadin', 'li_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
		}
	}

	function add_leadin_link_to_admin_bar( $wp_admin_bar ) {
		$args = array(
			'id'     => 'leadin-admin-menu',     // id of the existing child node (New > Post)
			'title'  => '<span class="ab-icon"><img src="/wp-content/plugins/leadin/images/leadin-svg-icon.svg" style="height:16px; width:16px;"></span><span class="ab-label">LeadIn</span>', // alter the title of existing node
			'parent' => false,	 // set parent to false to make it a top level (parent) node
			'href' => get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts',
			'meta' => array('title' => 'LeadIn')
		);

		$wp_admin_bar->add_node( $args );
	}

	/**
     * List available Jetpack modules. Simply lists .php files in /modules/.
     * Make sure to tuck away module "library" files in a sub-directory.
     */
    public static function get_available_power_ups( $min_version = false, $max_version = false ) {
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
				$power_up->permanent 		= $headers['permanent'];
				$power_up->auto_activate 	= $headers['auto_activate'];
				$power_up->activated 		= $headers['activated'];

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
			'introduced'		=> 'First Introduced',
			'auto_activate'		=> 'Auto Activate',
			'permanent'			=> 'Permanently Enabled',
			'power_up_tags'		=> 'Power-up Tags'
		);

		$file = WPLeadIn::get_power_up_path( WPLeadIn::get_power_up_slug( $power_up ) );
		if ( ! file_exists( $file ) )
			return false;

		$pu = get_file_data( $file, $headers );

		if ( empty( $pu['name'] ) )
			return false;

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

        while ( false !== $file = readdir( $dir ) ) {
            if ( '.' == substr( $file, 0, 1 ) || '.php' != substr( $file, -4 ) ) {
                continue;
            }

            $file = "$absolute_path/$file";

            if ( ! is_file( $file ) ) {
                continue;
            }

            $files[] = $file;
        }

        closedir( $dir );

        return $files;
    }

    /**
	 * Check whether or not a LeadIn power-up is active.
	 *
	 * @param string $power_up The slug of a Jetpack module.
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
			return false;

		// If it's already active, then don't do it again
		$active = self::is_power_up_active($power_up_slug);
		if ( $active )
			return true;

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
			return false;

		// If it's already active, then don't do it again
		$active = self::is_power_up_active($power_up_slug);
		if ( ! $active )
			return true;

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
$leadin_wp = new WPLeadIn();


?>