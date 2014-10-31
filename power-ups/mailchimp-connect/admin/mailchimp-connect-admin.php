<?php
//=============================================
// WPLeadInAdmin Class
//=============================================
class WPMailChimpConnectAdmin extends WPLeadInAdmin {
    
    var $power_up_settings_section = 'leadin_mls_options_section';
    var $power_up_icon;
    var $options;
    var $authed = FALSE;
    var $invalid_key = FALSE;

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
            $this->options = get_option('leadin_mls_options');
            $this->authed = ( isset($this->options['li_mls_api_key']) && $this->options['li_mls_api_key'] ? TRUE : FALSE );

            if ( $this->authed )
                $this->invalid_key = $this->li_mls_check_invalid_api_key($this->options['li_mls_api_key']);
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
        register_setting('leadin_settings_options', 'leadin_mls_options', array($this, 'sanitize'));
        add_settings_section($this->power_up_settings_section, $this->power_up_icon . "MailChimp", '', LEADIN_ADMIN_PATH);
        add_settings_field('li_mls_api_key', 'API key', array($this, 'li_mls_api_key_callback'), LEADIN_ADMIN_PATH, $this->power_up_settings_section);

        if ( isset($this->options['li_mls_api_key']) )
        {
            if ( $this->options['li_mls_api_key'] )
                add_settings_field('li_print_synced_lists', 'Synced tags', array($this, 'li_print_synced_lists'), LEADIN_ADMIN_PATH, $this->power_up_settings_section);
        }
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
     * Prints API key input for settings page
     */
    function li_mls_api_key_callback ()
    {
        $li_mls_api_key = ( $this->options['li_mls_api_key'] ? $this->options['li_mls_api_key'] : '' ); // Get header from options, or show default
        
        printf(
            '<input id="li_mls_api_key" type="text" id="title" name="leadin_mls_options[li_mls_api_key]" value="%s" size="50"/>',
            $li_mls_api_key
        );

        if ( ! isset($li_mls_api_key) || ! $li_mls_api_key )
            echo '<p><a href="http://admin.mailchimp.com/account/api/" target="_blank">Get an API key from MailChimp.com</a></p>';
    }

    /**
     * Prints synced lists out for settings page in format  Tag Name → ESP list
     */
    function li_print_synced_lists ()
    {
        $li_mls_api_key = ( $this->options['li_mls_api_key'] ? $this->options['li_mls_api_key'] : '' ); // Get header from options, or show default
        
        if ( isset($li_mls_api_key ) )
        {
            $synced_lists = $this->li_get_synced_list_for_esp('mailchimp');
            $list_value_pairs = array();
            $synced_list_count = 0;

            echo '<table>';
            foreach ( $synced_lists as $synced_list )
            {
                foreach ( stripslashes_deep(unserialize($synced_list->tag_synced_lists)) as $tag_synced_list )
                {
                    if ( $tag_synced_list['esp'] == 'mailchimp' )
                    {
                        echo '<tr class="synced-list-row">';
                            echo '<td class="synced-list-cell"><span class="icon-tag"></span> ' . $synced_list->tag_text . '</td>';
                            echo '<td class="synced-list-cell"><span class="synced-list-arrow">&#8594;</span></td>';
                            echo '<td class="synced-list-cell"><span class="icon-envelope"></span> ' . $tag_synced_list['list_name'] . '</td>';
                            echo '<td class="synced-list-edit"><a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts&action=edit_tag&tag=' . $synced_list->tag_id . '">edit tag</a></td>';
                        echo '</tr>';

                        $synced_list_count++;
                    }
                }
            }
            echo '</table>';

            if ( ! $synced_list_count ) {
                echo '<p>MailChimp connected succesfully! <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_tags">Select a tag to send contacts to MailChimp</a>.</p>';
            } else {
                echo '<p><a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_tags">Edit your tags</a> or <a href="http://admin.mailchimp.com/lists/new-list/" target="_blank">Create a new list on MailChimp.com</a></p>';
            }
        }
    }

    /**
     * Get synced list for the ESP from the WordPress database
     *
     * @return array/object    
     */
    function li_get_synced_list_for_esp ( $esp_name, $output_type = 'OBJECT' )
    {
        global $wpdb;

        $q = $wpdb->prepare("SELECT * FROM $wpdb->li_tags WHERE tag_synced_lists LIKE '%%%s%%' AND tag_deleted = 0", $esp_name);
        $synced_lists = $wpdb->get_results($q, $output_type);

        return $synced_lists;
    }

    /**
     * Prints email input for settings page
     */
    function li_mls_subscribers_to_list_callback ()
    {
        $li_mls_subscribers_to_list = ( isset($this->options['li_mls_subscribers_to_list']) ? $this->options['li_mls_subscribers_to_list'] : '' );

        $lists = $this->li_mls_get_mailchimp_lists($this->options['li_mls_api_key']);

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

    /**
     * Format API-returned lists into parseable format on front end
     *
     * @return array    
     */
    function li_get_lists ( )
    {
        $lists = $this->li_mls_get_mailchimp_lists($this->options['li_mls_api_key']);
        
        $sanitized_lists = array();
        if ( count($lists['data']) )
        {
            foreach ( $lists['data'] as $list )
            {
                $list_obj = (Object)NULL;
                $list_obj->id = $list['id'];
                $list_obj->name = $list['name'];

                array_push($sanitized_lists, $list_obj);;
            }
        }
        
        return $sanitized_lists;
    }

    /**
     * Get lists from MailChimp account
     *
     * @param string
     * @return array    
     */
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

    /**
     * Use MailChimp API key to try to grab corresponding user profile to check validity of key
     *
     * @param string
     * @return bool    
     */
    function li_mls_check_invalid_api_key ( $api_key )
    {
        $MailChimp = new LI_MailChimp($api_key);

        $user_profile = $MailChimp->call("users/profile");

        if ( $user_profile )
            $invalid_key = FALSE;
        else
            $invalid_key = TRUE;

        return $invalid_key;
    } 
}

?>
