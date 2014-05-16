<?php
//=============================================
// WPLeadInAdmin Class
//=============================================
class WPMailChimpListSyncAdmin extends WPLeadInAdmin {
    
    var $power_up_settings_section = 'leadin_mls_options_section';
    var $power_up_icon;

    /**
     * Class constructor
     */
    function __construct ( $power_up_icon_small )
    {
        //=============================================
        // Hooks & Filters
        //=============================================

        if ( is_admin() )
        {
            $this->power_up_icon = $power_up_icon_small;
            add_action('admin_init', array($this, 'leadin_mls_build_settings_page'));
        }
    }

    //=============================================
    // Settings Page
    //=============================================

    /**
     * Creates settings options
     */
    function leadin_mls_build_settings_page ()
    {
        $options = get_option('leadin_mls_options');

        register_setting('leadin_settings_options', 'leadin_mls_options', array($this, 'sanitize'));
        add_settings_section($this->power_up_settings_section, $this->power_up_icon . "MailChimp", '', LEADIN_ADMIN_PATH);
        add_settings_field('li_mls_api_key', 'API key', array($this, 'li_mls_api_key_callback'), LEADIN_ADMIN_PATH, $this->power_up_settings_section);
        
        if ( isset($options['li_mls_api_key']) && $options['li_mls_api_key'] )
            add_settings_field('li_mls_subscribers_to_list', 'Add subscribers to list', array($this, 'li_mls_subscribers_to_list_callback'), LEADIN_ADMIN_PATH, $this->power_up_settings_section);
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize ( $input )
    {
        $new_input = array();

        if( isset( $input['li_mls_api_key'] ) )
            $new_input['li_mls_api_key'] = sanitize_text_field( $input['li_mls_api_key'] );

        if( isset( $input['li_mls_subscribers_to_list'] ) )
            $new_input['li_mls_subscribers_to_list'] = sanitize_text_field( $input['li_mls_subscribers_to_list'] );

        return $new_input;
    }

    /**
     * Prints email input for settings page
     */
    function li_mls_api_key_callback ()
    {
        $options = get_option('leadin_mls_options');
        $li_mls_api_key = ( $options['li_mls_api_key'] ? $options['li_mls_api_key'] : '' ); // Get header from options, or show default
        
        printf(
            '<input id="li_mls_api_key" type="text" id="title" name="leadin_mls_options[li_mls_api_key]" value="%s" size="50"/>',
            $li_mls_api_key
        );

        echo '<p><a href="http://admin.mailchimp.com/account/api/" target="_blank">Get an API key from MailChimp.com</a></p>';
    }

    /**
     * Prints email input for settings page
     */
    function li_mls_subscribers_to_list_callback ()
    {
        $options = get_option('leadin_mls_options');
        $li_mls_subscribers_to_list = ( isset($options['li_mls_subscribers_to_list']) ? $options['li_mls_subscribers_to_list'] : '' );

        $lists = $this->li_mls_get_mailchimp_lists($options['li_mls_api_key']);

        echo '<select id="li_mls_subscribers_to_list" name="leadin_mls_options[li_mls_subscribers_to_list]" ' . ( ! count($lists['data']) ? 'disabled' : '' ) . '>';

            if ( count($lists['data']) )
            {
                $list_set = FALSE;

                foreach ( $lists['data'] as $list )
                {
                    if ( $list['id'] == $li_mls_subscribers_to_list && !$list_set )
                        $list_set = TRUE;

                    echo '<option ' . ( $list['id'] == $li_mls_subscribers_to_list ? 'selected' : '' ) . ' value="' . $list['id'] . '">' . $list['name'] . '</option>';
                }

                if ( !$list_set )
                    echo '<option selected value="">No list set...</option>';
            }
            else
            {
                echo '<option value="No lists...">No lists...</option>';
            }

        echo '</select>';

        echo '<p><a href="http://admin.mailchimp.com/lists/new-list/" target="_blank">Create a new list on MailChimp.com</a></p>';
    }

    function li_mls_get_mailchimp_lists ( $api_key )
    {
        $MailChimp = new LI_MailChimp($api_key);

        $lists = $MailChimp->call("lists/list", array(
            "start" => 0, // optional, control paging of lists, start results at this list #, defaults to 1st page of data (page 0)
            "limit" => 25, // optional, control paging of lists, number of lists to return with each call, defaults to 25 (max=100)
            "sort_field" => "created", // optional, "created" (the created date, default) or "web" (the display order in the web app). Invalid values will fall back on "created" - case insensitive.
            "sort_dir" => "DESC" // optional, "DESC" for descending (default), "ASC" for Ascending. Invalid values will fall back on "created" - case insensitive. Note: to get the exact display order as the web app you'd use "web" and "ASC"
        ));

        return $lists;
    }
}

?>
