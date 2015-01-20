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
        global $pagenow;

        leadin_set_wpdb_tables();
        leadin_set_mysql_timezone_offset();

        $this->power_ups = self::get_available_power_ups();

        if ( is_user_logged_in() )
        {
            add_action('admin_bar_menu', array($this, 'add_leadin_link_to_admin_bar'), 999);
        }
 
        if ( is_admin() )
        {
            if ( ! defined('DOING_AJAX') || ! DOING_AJAX )
                $li_wp_admin = new WPLeadInAdmin($this->power_ups);
        }
        else
        {
            if ( in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php')) )
                add_action('login_enqueue_scripts', array($this, 'add_leadin_frontend_scripts'));
            else
                add_action('wp_enqueue_scripts', array($this, 'add_leadin_frontend_scripts'));
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
        $admin_url = admin_url('admin-ajax.php');
        wp_localize_script(
            'leadin-tracking', 
            'li_ajax', 
            array('ajax_url' => ( is_ssl() ? str_replace('http:', 'https:', $admin_url) : str_replace('https:', 'http:', $admin_url) ))
        );
    }

    /**
     * Adds Leadin link to top-level admin bar
     */
    function add_leadin_link_to_admin_bar ( $wp_admin_bar ) 
    {
        global $wp_version;

        if ( ! current_user_can('activate_plugins') )
        {
            if ( ! array_key_exists('li_grant_access_to_' . leadin_get_user_role(), get_option('leadin_options') ) )
                return FALSE;
        }

        $args = array(
            'id'     => 'leadin-admin-menu',
            'title'  => '<span class="ab-icon" '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? ' style="margin-top: 3px;"' : ''). '><img src="' . content_url() . '/plugins/leadin/images/leadin-svg-icon.svg" style="height:16px; width:16px;"></span><span class="ab-label">Leadin</span>', // alter the title of existing node
            'parent' => FALSE,   // set parent to false to make it a top level (parent) node
            'href' => get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_stats',
            'meta' => array('title' => 'Leadin')
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
                $power_up->curl_required    = ( $headers['curl_required'] == 'Yes' ? 1 : 0 );
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
            'hidden'            => 'Hidden',
            'curl_required'     => 'cURL Required'
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
            LEADIN_PLUGIN_DIR . '/power-ups/constant-contact-connect.php'
        ));

        closedir( $dir );

        return $files;
    }

    /**
     * Check whether or not a Leadin power-up is active.
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

    public static function activate_power_up ( $power_up_slug, $exit = TRUE )
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

    public static function deactivate_power_up ( $power_up_slug, $exit = TRUE )
    {
        if ( ! strlen( $power_up_slug ) )
            return FALSE;

        // If it's already deactivated, then don't do it again
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

    /**
     * Throws an error for when the premium version and the free version are activated in tandem
     */
    function deactivate_leadin_notice () 
    {
        ?>
        <div id="message" class="update-nag">
            <?php 
            _e( 
                '<p>' .
                    '<a style="font-size: 14px; float: right; color: #ccc; text-decoration: none; margin-top: -15px;" href="#">&#10006;</a>' .
                    '<b>Leadin Pro is now avaialble!</b>' .
                '</p>' .
                '<p>' . 
                    'Leadin Pro includes all the features you\'ve come to love from our plugin along with some powerful new ones too. <a href="#">See all the features</a>' .
                '</p>' .
                '<p>' .
                    'The launch of Leadin Pro also means that we are no longer supporting the version of Leadin hosted by the WordPress Plugin Directory.' . 
                    'Read more about why we are making this change on <a href="http://leadin.com/the-move-to-pro">the Leadin blog</a>. ' . 
                 '</p>' .
                '<p>' . 
                    'If you run have any questions or concerns, please feel free to email us - <a href="mailto:support@leadin.com">support@leadin.com</a>' .
                '</p>' . 
                '<p>' . 
                    '<a class="button button-primary" href="http://leadin.com">Download Leadin Pro for Free</a> ' .
                '</p>',
             'my-text-domain' 
            ); 
            ?>
        </div>
        <?php
    }

    /* Display a notice that can be dismissed */

    function example_admin_notice() {
        global $current_user ;
            $user_id = $current_user->ID;
            /* Check that the user hasn't already clicked to ignore the message */
        if ( ! get_user_meta($user_id, 'example_ignore_notice') ) {
            echo '<div class="updated"><p>'; 
            printf(__('This is an annoying nag message.  Why do people make these? | <a href="%1$s">Hide Notice</a>'), '?example_nag_ignore=0');
            echo "</p></div>";
        }
    }

    function example_nag_ignore() {
        global $current_user;
            $user_id = $current_user->ID;
            /* If user clicks to ignore the notice, add that to their user meta */
            if ( isset($_GET['example_nag_ignore']) && '0' == $_GET['example_nag_ignore'] ) {
                 add_user_meta($user_id, 'example_ignore_notice', 'true', true);
        }
    }
}

//=============================================
// Leadin Init
//=============================================

global $li_wp_admin;