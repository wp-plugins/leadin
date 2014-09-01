<?php

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
		leadin_set_wpdb_tables();

		$this->power_ups = self::get_available_power_ups();
        add_action('admin_bar_menu', array($this, 'add_leadin_link_to_admin_bar'), 999);
		
		if ( is_admin()  )
		{
			if ( ! defined('DOING_AJAX') || ! DOING_AJAX )
			{
				if ( current_user_can('manage_options') )	
					$li_wp_admin = new WPLeadInAdmin($this->power_ups);
			}
		}
		else
		{

			add_action('wp_enqueue_scripts', array($this, 'add_leadin_frontend_scripts'));
			// Get all the power-ups and instantiate them
		}
	}

	//=============================================
	// Scripts & Styles
	//=============================================

	/**
	 * Adds front end javascript + initializes ajax object
	 */
	function add_leadin_frontend_scripts ()
	{
		wp_register_script('leadin-tracking', LEADIN_PATH . '/assets/js/build/leadin-tracking.min.js', array ('jquery'), FALSE, TRUE);
		wp_enqueue_script('leadin-tracking');

		// replace https with http for admin-ajax calls for SSLed backends
		//wp_localize_script('leadin-tracking', 'li_ajax', array('ajax_url' => str_replace('https:', 'http:', admin_url('admin-ajax.php'))));
		wp_localize_script('leadin-tracking', 'li_ajax', array('ajax_url' => get_admin_url(NULL,'') . '/admin-ajax.php'));
	}

	/**
     * Adds LeadIn link to top-level admin bar
     */
	function add_leadin_link_to_admin_bar( $wp_admin_bar ) {
		global $wp_version;

		$args = array(
			'id'     => 'leadin-admin-menu',
			'title'  => '<span class="ab-icon" '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? ' style="margin-top: 3px;"' : ''). '><img src="/wp-content/plugins/leadin/images/leadin-svg-icon.svg" style="height:16px; width:16px;"></span><span class="ab-label">LeadIn</span>', // alter the title of existing node
			'parent' => FALSE,	 // set parent to false to make it a top level (parent) node
			'href' => get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_stats',
			'meta' => array('title' => 'LeadIn')
		);

		$wp_admin_bar->add_node( $args );
	}

	/**
     * List available power-ups
     */
    public static function get_available_power_ups ( $min_version = FALSE, $max_version = FALSE ) {
        static $power_ups = null;

        if ( ! isset( $power_ups ) ) {
            $files = self::glob_php( LEADIN_PLUGIN_DIR . '/power-ups' );

            $power_ups = array();

            foreach ( $files as $file ) {

                if ( ! $headers = self::get_power_up($file) ) {
                    continue;
                }

                $power_up = new $headers['class']($headers['activated']);
                $power_up->power_up_name    = $headers['name'];
                $power_up->menu_text        = $headers['menu_text'];
                $power_up->menu_link        = $headers['menu_link'];
                $power_up->slug             = $headers['slug'];
                $power_up->link_uri         = $headers['uri'];
                $power_up->description      = $headers['description'];
                $power_up->icon             = $headers['icon'];
                $power_up->permanent        = ( $headers['permanent'] == 'Yes' ? 1 : 0 );
                $power_up->auto_activate    = ( $headers['auto_activate'] == 'Yes' ? 1 : 0 );
                $power_up->hidden           = ( $headers['hidden'] == 'Yes' ? 1 : 0 );
                $power_up->activated        = $headers['activated'];

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
            'name'              => 'Power-up Name',
            'class'             => 'Power-up Class',
            'menu_text'         => 'Power-up Menu Text',
            'menu_link'         => 'Power-up Menu Link',
            'slug'              => 'Power-up Slug',
            'uri'               => 'Power-up URI',
            'description'       => 'Power-up Description',
            'icon'              => 'Power-up Icon',
            'icon_small'        => 'Power-up Icon Small',
            'introduced'        => 'First Introduced',
            'auto_activate'     => 'Auto Activate',
            'permanent'         => 'Permanently Enabled',
            'power_up_tags'     => 'Power-up Tags',
            'hidden'            => 'Hidden'
        );

        $file = self::get_power_up_path( self::get_power_up_slug( $power_up ) );
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
            LEADIN_PLUGIN_DIR . '/power-ups/mailchimp-connect.php', 
            LEADIN_PLUGIN_DIR . '/power-ups/constant-contact-connect.php',
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
    public static function is_power_up_active ( $power_up_slug )
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

global $li_wp_admin;