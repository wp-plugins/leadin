<?php

if ( !defined('LEADIN_PLUGIN_VERSION') ) 
{
    header('HTTP/1.0 403 Forbidden');
    die;
}

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_ADMIN_PATH') )
    define('LEADIN_ADMIN_PATH', untrailingslashit(__FILE__));

//=============================================
// Include Needed Files
//=============================================

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

if ( !class_exists('LI_Pointers') )
    require_once LEADIN_PLUGIN_DIR . '/inc/class-leadin-pointers.php';

//=============================================
// WPLeadInAdmin Class
//=============================================
class WPLeadInAdmin {
    
    var $li_viewers;
    var $stats_dashboard;
    var $action;

    /**
     * Class constructor
     */
    function __construct ()
    {
        //=============================================
        // Hooks & Filters
        //=============================================

        $plugin_version = get_option('leadin_pluginVersion');

        $this->action = $this->leadin_current_action();

        // If the plugin version matches the latest version escape the update function
        if ( $plugin_version != LEADIN_PLUGIN_VERSION )
            self::leadin_update_check();
        
        add_action('admin_menu', array(&$this, 'leadin_add_menu_items'));
        add_action('admin_print_scripts', array(&$this, 'add_leadin_admin_scripts'));
        add_filter('plugin_action_links_' . 'leadin/leadin.php', array($this, 'leadin_plugin_settings_link'));

    }

    function leadin_update_check ()
    {
        leadin_maybe_add_migration_db_columns();

        update_option('leadin_pluginVersion', LEADIN_PLUGIN_VERSION);
    }

    //=============================================
    // Menus
    //=============================================

    /**
     * Adds Leadin menu to /wp-admin sidebar
     */
    function leadin_add_menu_items ()
    {
        $options = get_option('leadin_options');

        global $submenu;
        global  $wp_version;

        // Block non-sanctioned users from accessing Leadin
        $capability = 'activate_plugins';
        if ( ! current_user_can('activate_plugins') )
        {
            if ( ! array_key_exists('li_grant_access_to_' . leadin_get_user_role(), $options ) )
                return FALSE;
            else
            {
                if ( current_user_can('manage_network') ) // super admin
                    $capability = 'manage_network';
                else if ( current_user_can('edit_pages') ) // editor
                    $capability = 'edit_pages';
                else if ( current_user_can('publish_posts') ) // author
                    $capability = 'publish_posts';
                else if ( current_user_can('edit_posts') ) // contributor
                    $capability = 'edit_posts';
                else if ( current_user_can('read') ) // subscriber
                    $capability = 'read';

            }
        }

        $leadin_icon = LEADIN_PATH . '/images/leadin-icon-16x16-white.png';

        add_menu_page('Leadin', 'Leadin', $capability, 'leadin', array($this, 'leadin_build_app'),  $leadin_icon , '25.100713');

        add_submenu_page('leadin', 'Contacts', 'Contacts', 'activate_plugins', 'leadin_contacts', array($this, 'leadin_build_app'));
        add_submenu_page('leadin', 'Settings', 'Settings', 'activate_plugins', 'leadin_settings', array($this, 'leadin_build_app'));

        if (get_option('leadin_puntMigration') == true && get_option('leadin_migrationStatus') != 'completed')
        {
            add_submenu_page('leadin', 'Migrate', 'Migrate', 'activate_plugins', 'leadin#migration/unpunt', array($this, 'unpunt_migration_callback'));
        }

        $submenu['leadin'][0][0] = 'Stats';

        if ( ! isset($_GET['page']) || $_GET['page'] != ('leadin' || 'leadin_settings' || 'leadin_contacts') )
        {
            if ( ! get_option('leadin_portalId') )
                $li_pointers = new LI_Pointers(TRUE);
            //else if ( ! get_option('leadin_portalId') && $options )
                //$li_pointers = new LI_Pointers(FALSE);
        }
    }

    //=============================================
    // Settings Page
    //=============================================

    /**
     * Adds setting link for Leadin to plugins management page 
     *
     * @param   array $links
     * @return  array
     */
    function leadin_plugin_settings_link ( $links )
    {
        $url = get_admin_url(get_current_blog_id(), 'admin.php?page=leadin_settings');
        $settings_link = '<a href="' . $url . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Creates leadin app
     */

    function leadin_build_app () {
        global $wp_version;

        echo '<div id="leadin" class="wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php')  ? 'pre-mp6' : ''). '"></div>';

        wp_enqueue_style('leadin-css');
        wp_enqueue_script('leadin-app');

    }

    function update_option_leadin_options_callback ( $old_value, $new_value )
    {
    }

    //=============================================
    // Admin Styles & Scripts
    //=============================================

    /**
     * Adds admin javascript
     */
    function add_leadin_admin_scripts ()
    {
        global $pagenow;
        global $wp_roles;
        global $wp_version;

        $ajaxUrl = get_admin_url(get_current_blog_id(), 'admin-ajax.php');

        $leadin_config = array(
            'portalId' => get_option('leadin_portalId'),
            'hapikey' => get_option('leadin_hapikey'),
            'env' => constant('LEADIN_ENV'),
            'user' => $this->leadin_get_user_for_tracking(),
            'allRoles' => $wp_roles->get_names(),
            'leadinPluginVersion' => constant('LEADIN_PLUGIN_VERSION'),
            'wpVersion' => $wp_version,
            'siteUrl' => get_site_url(),
            'adminEmail' => get_option('admin_email'),
            'siteName' => get_bloginfo('name'),
            'adminBaseUrl' => get_admin_url(get_current_blog_id(), 'admin.php'),
            'apiBaseUrl' => constant('LEADIN_API_BASE_URL'),
            'assetsBaseUrl' => constant('LEADIN_ADMIN_ASSETS_BASE_URL'),
            'migrationStatus' => leadin_check_migration_status(),
            'migrationPunted' => get_option('leadin_puntMigration'),
            'migrationNonce' => wp_create_nonce( 'leadin-migration-nonce' ),
            'leadinPluginDirectory' => LEADIN_PLUGIN_SLUG,
            'ajaxUrl' => is_ssl() ? str_replace('http:', 'https:', $ajaxUrl) : str_replace('https:', 'http:', $ajaxUrl),
            'locale' => get_locale(),
            'timezone' => get_option('gmt_offset'),
            'timezoneString' => get_option('timezone_string') // If not set by the user manually it will be an empty string
        );

        // remove after migration is complete
        if ( $leadin_config['migrationStatus'] == 'started')
        {
            $leadin_config['timeSinceLastMigration'] = time() - get_option('leadin_most_recent_migration_timestamp');

        }
        // remove after migration is complete

        if ( ($pagenow == 'admin.php' && isset($_GET['page']) && strstr($_GET['page'], 'leadin')) ) 
        {
            wp_register_script('leadin-head-js', leadin_get_resource_url('/bundle/head/head.js'), FALSE, FALSE, FALSE);
            wp_localize_script('leadin-head-js', 'leadin_config', $leadin_config);
            wp_enqueue_script('leadin-head-js');

            wp_register_script('leadin-app', leadin_get_resource_url('/bundle/app.js'), array( 'backbone' ), FALSE, TRUE);
            wp_register_style('leadin-css', leadin_get_resource_url('/bundle/app.css'));            
        }
    }

    //=============================================
    // Internal Class Functions
    //=============================================

    function leadin_get_user_for_tracking ()
    {
        $leadin_user = leadin_get_current_user();
        $tracking_leadin_user = array(
            'hashed_wp_url' => $leadin_user['user_id'],
            'name' => $leadin_user['alias'],
            'email' => $leadin_user['email'],
            'wp-url' => $leadin_user['wp_url'],
            'wp-version' => $leadin_user['wp_version'],
            'li-source' => LEADIN_SOURCE,
            'website' => $leadin_user['wp_url'],
            'company' => $leadin_user['wp_url'],
            'utm_source' => $leadin_user['utm_source'],
            'utm_medium' => $leadin_user['utm_medium'],
            'utm_term' => $leadin_user['utm_term'],
            'utm_content' => $leadin_user['utm_term'],
            'utm_campaign' => $leadin_user['utm_campaign'],
            'referral_source' => $leadin_user['referral_source'],
            'user_email' => $leadin_user['user_email']
        );
        return $tracking_leadin_user;
    }

    /**
     * GET and set url actions into readable strings
     * @return string if actions are set,   bool if no actions set
     */
    function leadin_current_action ()
    {
        if ( isset($_REQUEST['action']) && -1 != $_REQUEST['action'] )
            return $_REQUEST['action'];

        if ( isset($_REQUEST['action2']) && -1 != $_REQUEST['action2'] )
            return $_REQUEST['action2'];

        return FALSE;
    }    

}

?>
