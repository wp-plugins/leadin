<?php

//=============================================
// Include Needed Files
//=============================================


//=============================================
// WPLeadInAdmin Class
//=============================================
class WPLeadInSubscribeAdmin extends WPLeadInAdmin {
    
    var $power_up_settings_section = 'leadin_subscribe_options_section';
    var $power_up_icon;

    /**
     * Class constructor
     */
    function __construct (  $power_up_icon )
    {
        //=============================================
        // Hooks & Filters
        //=============================================
        
        if ( is_admin() )
        {
            $this->power_up_icon = '<span class="dashicons dashicons-email-alt"></span>';
            add_action('admin_init', array($this, 'leadin_subscribe_build_settings_page'));
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

        add_settings_section(
            $this->power_up_settings_section,
            $this->power_up_icon . 'Subscribe Pop-up',
            '',
            LEADIN_ADMIN_PATH
        );

        add_settings_field(
            'li_subscribe_vex_class',
            'Pop-up Location',
            array($this, 'li_subscribe_vex_class_callback'),
            LEADIN_ADMIN_PATH,
            $this->power_up_settings_section
        );
        add_settings_field(
            'li_subscribe_heading',
            'Pop-up header text',
            array($this, 'li_subscribe_heading_callback'),
            LEADIN_ADMIN_PATH,
            $this->power_up_settings_section
        );
        add_settings_field(
            'li_subscribe_btn_label',
            'Button text',
            array($this, 'li_subscribe_btn_label_callback'),
            LEADIN_ADMIN_PATH,
            $this->power_up_settings_section
        );
        add_settings_field(
            'li_subscribe_name_fields',
            'Include First and Last Name',
            array($this, 'li_subscribe_name_fields_callback'),
            LEADIN_ADMIN_PATH,
            $this->power_up_settings_section
        );
        add_settings_field(
            'li_subscribe_phone_field',
            'Include Phone Number',
            array($this, 'li_subscribe_phone_field_callback'),
            LEADIN_ADMIN_PATH,
            $this->power_up_settings_section
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize ( $input )
    {
        $new_input = array();

        if( isset( $input['li_subscribe_vex_class'] ) )
            $new_input['li_subscribe_vex_class'] = sanitize_text_field( $input['li_subscribe_vex_class'] );

        if( isset( $input['li_subscribe_heading'] ) )
            $new_input['li_subscribe_heading'] = sanitize_text_field( $input['li_subscribe_heading'] );

        if( isset( $input['li_subscribe_btn_label'] ) )
            $new_input['li_subscribe_btn_label'] = sanitize_text_field( $input['li_subscribe_btn_label'] );

        if( isset( $input['li_subscribe_name_fields'] ) )
            $new_input['li_subscribe_name_fields'] = sanitize_text_field( $input['li_subscribe_name_fields'] );

        if( isset( $input['li_subscribe_phone_field'] ) )
            $new_input['li_subscribe_phone_field'] = sanitize_text_field( $input['li_subscribe_phone_field'] );

        return $new_input;
    }

    /**
     * Prints subscribe location input for settings page
     */
    function li_subscribe_vex_class_callback ()
    {
        $options = get_option('leadin_subscribe_options');
        $li_subscribe_vex_class = ( $options['li_subscribe_vex_class'] ? $options['li_subscribe_vex_class'] : 'vex-theme-bottom-right-corner' ); // Get class from options, or show default

        echo '<select id="li_subscribe_vex_class" name="leadin_subscribe_options[li_subscribe_vex_class]">';
            echo '<option value="vex-theme-bottom-right-corner"' . ( $li_subscribe_vex_class == 'vex-theme-bottom-right-corner' ? ' selected' : '' ) . '>Bottom right</option>';
            echo '<option value="vex-theme-bottom-left-corner"' . ( $li_subscribe_vex_class == 'vex-theme-bottom-left-corner' ? ' selected' : '' ) . '>Bottom left</option>';
            echo '<option value="vex-theme-top"' . ( $li_subscribe_vex_class == 'vex-theme-top' ? ' selected' : '' ) . '>Top</option>';
            echo '<option value="vex-theme-default"' . ( $li_subscribe_vex_class == 'vex-theme-default' ? ' selected' : '' ) . '>Pop-over content</option>';
        echo '</select>';
    }

    /**
     * Prints subscribe heading input for settings page
     */
    function li_subscribe_heading_callback ()
    {
        $options = get_option('leadin_subscribe_options');
        $li_subscribe_heading = ( $options['li_subscribe_heading'] ? $options['li_subscribe_heading'] : 'Sign up for my newsletter to get new posts by email' ); // Get header from options, or show default
        
        printf(
            '<input id="li_subscribe_heading" type="text" name="leadin_subscribe_options[li_subscribe_heading]" value="%s" size="50"/>',
            $li_subscribe_heading
        );
    }

    /**
     * Prints subscribe heading text input for settings page
     */
    function li_subscribe_btn_label_callback ()
    {
        $options = get_option('leadin_subscribe_options');
        $li_subscribe_btn_label = ( $options['li_subscribe_btn_label'] ? $options['li_subscribe_btn_label'] : 'SUBSCRIBE' ); // Get button text from options, or show default
        
        printf(
            '<input id="li_subscribe_btn_label" type="text" name="leadin_subscribe_options[li_subscribe_btn_label]" value="%s" size="50"/>',
            $li_subscribe_btn_label
        );

    }

    /**
     * Prints first and last name checkbox for settings page
     */
    function li_subscribe_name_fields_callback ()
    {
        $options = get_option('leadin_subscribe_options');
        $li_subscribe_name_fields = ( $options['li_subscribe_name_fields'] ? $options['li_subscribe_name_fields'] : '0' ); // Get name field options from options, or show default
        
        printf(
            '<input id="li_subscribe_name_fields" type="checkbox" name="leadin_subscribe_options[li_subscribe_name_fields]" value="1"' . checked( 1, $options['li_subscribe_name_fields'], false ) . '/>',
            $li_subscribe_name_fields
        );
    }

    /**
     * Prints phone number checkbox for settings page
     */
    function li_subscribe_phone_field_callback ()
    {
        $options = get_option('leadin_subscribe_options');
        $li_subscribe_phone_field = ( $options['li_subscribe_phone_field'] ? $options['li_subscribe_phone_field'] : '0' ); // Get phone field preference from options, or show default
        
        printf(
            '<input id="li_subscribe_phone_field" type="checkbox" name="leadin_subscribe_options[li_subscribe_phone_field]" value="1"' . checked( 1, $options['li_subscribe_phone_field'], false ) . '/>',
            $li_subscribe_phone_field
        );
    }

}

?>