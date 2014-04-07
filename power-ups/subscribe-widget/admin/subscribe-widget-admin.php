<?php

//=============================================
// Include Needed Files
//=============================================


//=============================================
// WPLeadInAdmin Class
//=============================================
class WPLeadInSubscribeAdmin extends WPLeadInAdmin {
    
    var $power_up_settings_section = 'leadin_subscribe_options_section';

    /**
     * Class constructor
     */
    function __construct ()
    {
        //=============================================
        // Hooks & Filters
        //=============================================

        if ( is_admin() )
        {
            add_action('admin_init', array($this, 'leadin_subscribe_build_settings_page'));
            add_action('admin_print_scripts', array($this, 'add_leadin_subscribe_admin_scripts'));
            add_action('admin_print_styles', array($this, 'add_leadin_subscribe_admin_styles'));
        }
    }

    //=============================================
    // Settings Page
    //=============================================

    /**
     * Creates settings options
     */
    function leadin_subscribe_build_settings_page ()
    {
        register_setting('leadin_settings_options', 'leadin_subscribe_options', array($this, 'sanitize'));
        add_settings_section($this->power_up_settings_section, 'Subscribe Pop-in', '', LEADIN_ADMIN_PATH);
        add_settings_field('li_subscribe_heading', 'Call-to-action text', array($this, 'li_subscribe_heading_callback'), LEADIN_ADMIN_PATH, $this->power_up_settings_section);
        add_settings_field('li_subscribe_btn_label', 'Button label', array($this, 'li_subscribe_btn_label_callback'), LEADIN_ADMIN_PATH, $this->power_up_settings_section);
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize ( $input )
    {
        $new_input = array();

        if( isset( $input['li_subscribe_heading'] ) )
            $new_input['li_subscribe_heading'] = sanitize_text_field( $input['li_subscribe_heading'] );

        if( isset( $input['li_subscribe_btn_label'] ) )
            $new_input['li_subscribe_btn_label'] = sanitize_text_field( $input['li_subscribe_btn_label'] );

        return $new_input;
    }

    /**
     * Prints email input for settings page
     */
    function li_subscribe_heading_callback ()
    {
        $options = get_option('leadin_subscribe_options');
        $li_subscribe_heading = ( $options['li_subscribe_heading'] ? $options['li_subscribe_heading'] : 'Sign up for my newsletter to get new posts by email' ); // Get header from options, or show default
        
        printf(
            '<input id="li_subscribe_heading" type="text" id="title" name="leadin_subscribe_options[li_subscribe_heading]" value="%s" size="50"/>',
            $li_subscribe_heading
        );
    }

    /**
     * Prints email input for settings page
     */
    function li_subscribe_btn_label_callback ()
    {
        $options = get_option('leadin_subscribe_options');
        $li_subscribe_btn_label = ( $options['li_subscribe_btn_label'] ? $options['li_subscribe_btn_label'] : 'SUBSCRIBE' ); // Get header from options, or show default
        
        printf(
            '<input id="li_subscribe_btn_label" type="text" id="title" name="leadin_subscribe_options[li_subscribe_btn_label]" value="%s" size="50"/>',
            $li_subscribe_btn_label
        );

    }

    //=============================================
    // Admin Styles & Scripts
    //=============================================

    /**
     * Adds admin javascript
     */
    function add_leadin_subscribe_admin_scripts ()
    {
        global $pagenow;

        if ( $pagenow == 'admin.php' && isset($_GET['page']) && strstr($_GET['page'], "leadin_settings") ) 
        {
            wp_register_script('leadin-subscribe-admin-js', LEADIN_SUBSCRIBE_WIDGET_PATH . '/admin/js/leadin-subscribe-admin.js', array ( 'jquery' ), FALSE, TRUE);
            wp_enqueue_script('leadin-subscribe-admin-js');
       }
    }

    /**
     * Adds admin javascript
     */
    function add_leadin_subscribe_admin_styles ()
    {
        global $pagenow;

        if ( $pagenow == 'admin.php' && isset($_GET['page']) && strstr($_GET['page'], "leadin_settings") ) 
        {
            wp_register_style('leadin-subscribe-admin-css', LEADIN_SUBSCRIBE_WIDGET_PATH . '/admin/css/leadin-subscribe-admin.css');
            wp_enqueue_style('leadin-subscribe-admin-css');
        }
    }
}

?>
