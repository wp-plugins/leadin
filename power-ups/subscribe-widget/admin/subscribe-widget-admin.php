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
    var $options;

    /**
     * Class constructor
     */
    function __construct (  $power_up_icon_small )
    {
        //=============================================
        // Hooks & Filters
        //=============================================
        
        if ( is_admin() )
        {
            $this->power_up_icon = $power_up_icon_small;
            add_action('admin_init', array($this, 'leadin_subscribe_build_settings_page'));
            $this->options = get_option('leadin_subscribe_options');
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
            array($this, 'print_hidden_settings_fields'),
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
            'li_subscribe_additional_fields',
            'Also include fields for',
            array($this, 'li_subscribe_additional_fields_callback'),
            LEADIN_ADMIN_PATH,
            $this->power_up_settings_section
        );
        add_settings_field( 
            'li_subscribe_templates', 
            'Show subscribe pop-up on', 
            array($this, 'li_subscribe_templates_callback'), 
            LEADIN_ADMIN_PATH, 
            $this->power_up_settings_section
        );
        add_settings_field( 
            'li_subscribe_confirmation', 
            'Subscription confirmation', 
            array($this, 'li_subscribe_confirmation_callback'), 
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

        if( isset( $input['li_susbscibe_installed'] ) )
            $new_input['li_susbscibe_installed'] = sanitize_text_field( $input['li_susbscibe_installed'] );

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

        if( isset( $input['li_subscribe_template_pages'] ) )
            $new_input['li_subscribe_template_pages'] = sanitize_text_field( $input['li_subscribe_template_pages'] );

        if( isset( $input['li_subscribe_template_posts'] ) )
            $new_input['li_subscribe_template_posts'] = sanitize_text_field( $input['li_subscribe_template_posts'] );
        
        if( isset( $input['li_subscribe_template_home'] ) )
            $new_input['li_subscribe_template_home'] = sanitize_text_field( $input['li_subscribe_template_home'] );

        if( isset( $input['li_subscribe_template_archives'] ) )
            $new_input['li_subscribe_template_archives'] = sanitize_text_field( $input['li_subscribe_template_archives'] );

        if( isset( $input['li_subscribe_confirmation'] ) )
            $new_input['li_subscribe_confirmation'] = sanitize_text_field( $input['li_subscribe_template_home'] );
        else
            $new_input['li_subscribe_confirmation'] = '0';

        return $new_input;
    }

    function print_hidden_settings_fields ()
    {
         // Hacky solution to solve the Settings API overwriting the default values
        $options = $this->options;
        $li_susbscibe_installed = ( $options['li_susbscibe_installed'] ? $options['li_susbscibe_installed'] : 1 );

        printf(
            '<input id="li_susbscibe_installed" type="hidden" name="leadin_subscribe_options[li_susbscibe_installed]" value="%d"/>',
            $li_susbscibe_installed
        );
    }
    /**
     * Prints subscribe location input for settings page
     */
    function li_subscribe_vex_class_callback ()
    {
        $options = $this->options;
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
        $options = $this->options;
        $li_subscribe_heading = ( $options['li_subscribe_heading'] ? $options['li_subscribe_heading'] : 'Sign up for my newsletter to get new posts by email' ); // Get header from options, or show default
        
        printf(
            '<input id="li_subscribe_heading" type="text" name="leadin_subscribe_options[li_subscribe_heading]" value="%s" size="50"/>',
            $li_subscribe_heading
        );
    }

    /**
     * Prints button label
     */
    function li_subscribe_btn_label_callback ()
    {
        $options = $this->options;
        $li_subscribe_btn_label = ( $options['li_subscribe_btn_label'] ? $options['li_subscribe_btn_label'] : 'SUBSCRIBE' ); // Get button text from options, or show default
        
        printf(
            '<input id="li_subscribe_btn_label" type="text" name="leadin_subscribe_options[li_subscribe_btn_label]" value="%s" size="50"/>',
            $li_subscribe_btn_label
        );

    }

    /**
     * Prints additional fields for first name, last name and phone number
     */
    function li_subscribe_additional_fields_callback ()
    {
        $options = $this->options;

        printf(
            '<p><input id="li_subscribe_name_fields" type="checkbox" name="leadin_subscribe_options[li_subscribe_name_fields]" value="1"' . checked( 1, ( isset($options['li_subscribe_name_fields']) ? $options['li_subscribe_name_fields'] : '0' ), false ) . '/>' . 
            '<label for="li_subscribe_name_fields">First + last name</label></p>'
        );

        printf(
            '<p><input id="li_subscribe_phone_field" type="checkbox" name="leadin_subscribe_options[li_subscribe_phone_field]" value="1"' . checked( 1, ( isset($options['li_subscribe_phone_field']) ? $options['li_subscribe_phone_field'] : '0' ), false ) . '/>' . 
            '<label for="li_subscribe_phone_field">Phone number</label></p>'
        );
    }

    /**
     * Prints the options for toggling the widget on posts, pages, archives and homepage
     */
    function li_subscribe_templates_callback ()
    {
        $options = $this->options;

        // If none of the values are set it's safe to assume the user hasn't toggled any yet so we should default them all
        if ( ! isset ($options['li_subscribe_template_posts']) && ! isset ($options['li_subscribe_template_pages']) && ! isset ($options['li_subscribe_template_archives']) && ! isset ($options['li_subscribe_template_home']) )
        {
            $options['li_subscribe_template_posts']     = 1;
            $options['li_subscribe_template_pages']     = 1;
            $options['li_subscribe_template_archives']  = 1;
            $options['li_subscribe_template_home']      = 1;
        }

        printf(
            '<p><input id="li_subscribe_template_posts" type="checkbox" name="leadin_subscribe_options[li_subscribe_template_posts]" value="1"' . checked( 1, ( isset($options['li_subscribe_template_posts']) ? $options['li_subscribe_template_posts'] : '0' ), false ) . '/>' . 
            '<label for="li_subscribe_template_posts">Posts</label></p>'
        );

        printf(
            '<p><input id="li_subscribe_template_pages" type="checkbox" name="leadin_subscribe_options[li_subscribe_template_pages]" value="1"' . checked( 1, ( isset($options['li_subscribe_template_pages']) ? $options['li_subscribe_template_pages'] : '0' ), false ) . '/>' . 
            '<label for="li_subscribe_template_pages">Pages</label></p>'
        );

        printf(
            '<p><input id="li_subscribe_template_archives" type="checkbox" name="leadin_subscribe_options[li_subscribe_template_archives]" value="1"' . checked( 1, ( isset($options['li_subscribe_template_archives']) ? $options['li_subscribe_template_archives'] : '0' ), false ) . '/>' . 
            '<label for="li_subscribe_template_archives">Archives</label></p>'
        );

        printf(
            '<p><input id="li_subscribe_template_home" type="checkbox" name="leadin_subscribe_options[li_subscribe_template_home]" value="1"' . checked( 1, ( isset($options['li_subscribe_template_home']) ? $options['li_subscribe_template_home'] : '0' ), false ) . '/>' . 
            '<label for="li_subscribe_template_home">Homepage</label></p>'
        );
    }

    /**
     * Prints the options for toggling the widget on posts, pages, archives and homepage
     */
    function li_subscribe_confirmation_callback ()
    {
        $options = $this->options;

        printf(
            '<p><input id="li_subscribe_confirmation" type="checkbox" name="leadin_subscribe_options[li_subscribe_confirmation]" value="1"' . checked( 1, ( isset($options['li_subscribe_confirmation']) ? $options['li_subscribe_confirmation'] : 1 ) , false ) . '/>' . 
            '<label for="li_subscribe_confirmation">Send new subscribers a confirmation email</label></p>'
        );
    }
}

?>