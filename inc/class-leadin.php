<?php

//=============================================
// WPLeadIn Class
//=============================================
class WPLeadIn {
    /**
     * Class constructor
     */
    function __construct ()
    {
        global $pagenow;

        leadin_set_mysql_timezone_offset();

        if ( is_user_logged_in() )
        {
            add_action('admin_bar_menu', array($this, 'add_leadin_link_to_admin_bar'), 999);
        }
 
        if ( is_admin() )
        {
            if ( ! defined('DOING_AJAX') || ! DOING_AJAX )
                $li_wp_admin = new WPLeadInAdmin();
        }
        else
        {
            // Adds the leadin-tracking script to wp-login.php page which doesnt hook into the enqueue logic
            if ( $this->leadin_is_login_or_register_page() )
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

        add_filter('script_loader_tag', array($this, 'leadin_add_crossorigin_attribute'), 10, 2);

        $embedDomain = constant('LEADIN_EMBED_DOMAIN');
        $portalId = get_option('leadin_portalId');

        if ( empty($portalId) ) {
            echo '<!-- Leadin embed JS disabled as a portalId has not yet been configured -->';
            return;
        }

        $embedUrl = '//'.$embedDomain.'/js/v1/'.$portalId.'.js';


        if ( is_single() )
            $page_type = 'post';
        else if ( is_front_page() )
            $page_type = 'home';
        else if ( is_archive() )
            $page_type = 'archive';
        else if ( $this->leadin_is_login_or_register_page() )
            $page_type = 'login';
        else if ( is_page() )
            $page_type = 'page';
        else
            $page_type = 'other';

        $leadin_wordpress_info = array(
            'userRole' => (is_user_logged_in()) ? leadin_get_user_role() : 'visitor',
            'pageType' => $page_type,
            'leadinPluginVersion' => LEADIN_PLUGIN_VERSION
        );

        wp_register_script('leadin-embed-js', $embedUrl, array ('jquery'), FALSE, TRUE);
        wp_localize_script('leadin-embed-js', 'leadin_wordpress', $leadin_wordpress_info);
        wp_enqueue_script('leadin-embed-js');
    }

    function leadin_add_crossorigin_attribute ( $tag, $handle ) {
        if ('leadin-embed-js' !== $handle)
            return $tag;
        else
            return str_replace(' src', 'crossorigin="use-credentials" src', $tag);
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


        $leadin_icon = '<img src="' . LEADIN_PATH . '/images/leadin-icon-16x16-white.png' . '">';

        $args = array(
            'id'     => 'leadin-admin-menu',
            'title'  => '<span class="ab-icon" '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? ' style="margin-top: 3px;"' : '' ) . '>' . $leadin_icon . '</span><span class="ab-label">Leadin</span>', // alter the title of existing node
            'parent' => FALSE,   // set parent to false to make it a top level (parent) node
            'href' => get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin',
            'meta' => array('title' => 'Leadin')
        );

        $wp_admin_bar->add_node( $args );
    }

    public static function leadin_is_login_or_register_page ()
    {
        return in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ));
    }
}

//=============================================
// Leadin Init
//=============================================

global $li_wp_admin;