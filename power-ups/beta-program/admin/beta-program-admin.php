<?php

//=============================================
// Include Needed Files
//=============================================


//=============================================
// WPLeadInAdmin Class
//=============================================
class WPLeadInBetaAdmin extends WPLeadInAdmin {
    
    var $power_up_settings_section = 'leadin_beta_testing_section';
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
            $this->options = get_option('leadin_options');

            $this->power_up_icon = '<span class="dashicons dashicons-admin-generic"></span>';
            add_action('admin_init', array($this, 'leadin_beta_program_build_settings_page'));
        }
    }

    //=============================================
    // Settings Page
    //=============================================

    /**
     * Creates settings options
     */
    function leadin_beta_program_build_settings_page ()
    {
        // Need to use the santize function from the main admin class because that is where leadin_options is set
        add_settings_section(
            $this->power_up_settings_section,
            $this->power_up_icon . 'Leadin Beta Program',
            array($this, 'beta_tester_callback'),
            LEADIN_ADMIN_PATH
        );
    }

    function beta_tester_callback ( )
    {
        $options = $this->options;

        echo '<table class="form-table">';
            echo '<div class="leadin-section">';
                echo 'As a beta tester, you\'ll get early access to product updates in development and we\'ll ask you to provide feedback which helps decide new features for the plugin.';
            echo '</div>';

            echo '<div class="leadin-section">';
                echo ' <b>By definition, some of the beta features may not work as intended though, so this program is only for people who enjoy being on the bleeding-edge of technology.</b>';
            echo '</div>';
        
            echo '<div class="leadin-section">';
                echo 'If you\'re the adventurous type, we\'d love to have you aboard our beta program as we build the future of the product.';
            echo '</div>';

            printf(
                '<tr><td><label for="beta_tester_input"><input id="beta_tester_input" type="checkbox" name="leadin_options[beta_tester]" value="1"' . checked( 1, ( isset ( $options['beta_tester']) ? $options['beta_tester'] : 0 ), false ) . '/>' . 
                'Yes, I\'d like to participate in the Leadin Beta Program</label></td></tr>'
            );

        echo '</table>';
    }
}

?>