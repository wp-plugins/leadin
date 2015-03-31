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

if ( !class_exists('LI_List_Table') )
    require_once LEADIN_PLUGIN_DIR . '/admin/inc/class-leadin-list-table.php';

if ( !class_exists('LI_Contact') )
    require_once LEADIN_PLUGIN_DIR . '/admin/inc/class-leadin-contact.php';

if ( !class_exists('LI_Pointers') )
    require_once LEADIN_PLUGIN_DIR . '/admin/inc/class-leadin-pointers.php';

if ( !class_exists('LI_Viewers') )
    require_once LEADIN_PLUGIN_DIR . '/admin/inc/class-leadin-viewers.php';

if ( !class_exists('LI_StatsDashboard') )
    require_once LEADIN_PLUGIN_DIR . '/admin/inc/class-stats-dashboard.php';

if ( !class_exists('LI_Tags_Table') )
    require_once LEADIN_PLUGIN_DIR . '/admin/inc/class-leadin-tags-list-table.php';

if ( !class_exists('LI_Tag_Editor') )
    require_once LEADIN_PLUGIN_DIR . '/admin/inc/class-leadin-tag-editor.php';

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

//=============================================
// WPLeadInAdmin Class
//=============================================
class WPLeadInAdmin {
    
    var $admin_power_ups;
    var $li_viewers;
    var $power_up_icon;
    var $stats_dashboard;
    var $action;

    /**
     * Class constructor
     */
    function __construct ( $power_ups )
    {
        //=============================================
        // Hooks & Filters
        //=============================================

        $options = get_option('leadin_options');

        $this->action = $this->leadin_current_action();

        // If the plugin version matches the latest version escape the update function
        if ( $options['leadin_version'] != LEADIN_PLUGIN_VERSION )
            self::leadin_update_check();

        $this->admin_power_ups = $power_ups;
        
        add_action('admin_menu', array(&$this, 'leadin_add_menu_items'));
        add_action('admin_init', array(&$this, 'leadin_build_settings_page'));
        add_action('admin_print_styles', array(&$this, 'add_leadin_admin_styles'));
        add_filter('plugin_action_links_' . 'leadin/leadin.php', array($this, 'leadin_plugin_settings_link'));

        if ( isset($_GET['page']) && $_GET['page'] == 'leadin_stats' )
        {
            add_action('admin_footer', array($this, 'build_contacts_chart'));
        }

        $updater_type = '';

        if ( isset($options['pro']) && $options['pro'] )
            $updater_type = 'pro';

        if ( $updater_type )
            $li_wp_updater = new WPLeadInUpdater($updater_type);
    }

    function leadin_update_check ()
    {
        $options = get_option('leadin_options');

        // 0.5.1 upgrade - Create active power-ups option if it doesn't exist
        $leadin_active_power_ups = get_option('leadin_active_power_ups');

        if ( ! $leadin_active_power_ups )
        {
            $auto_activate = array(
                'contacts',
                'lookups'
            );

            update_option('leadin_active_power_ups', serialize($auto_activate));
        }
        else
        {
            // 0.9.2 upgrade - set beta program power-up to auto-activate
            $activated_power_ups = unserialize($leadin_active_power_ups);
            $update_active_power_ups = FALSE;

            // 0.9.3 bug fix for duplicate beta_program values being stored in the active power-ups array
            if ( !in_array('beta_program', $activated_power_ups) )
            {
                $activated_power_ups[] = 'beta_program';
                $update_active_power_ups = TRUE;
            }
            else 
            {
                $tmp = array_count_values($activated_power_ups);
                $count = $tmp['beta_program'];

                if ( $count > 1 )
                {
                    $activated_power_ups = array_unique($activated_power_ups);
                    $update_active_power_ups = TRUE;
                }
            }

            // 2.0.1 upgrade - [plugin_slug]_list_sync changed to [plugin_slug]_connect
            $mailchimp_list_sync_key = array_search('mailchimp_list_sync', $activated_power_ups);
            if ( $mailchimp_list_sync_key !== FALSE )
            {
                unset($activated_power_ups[$mailchimp_list_sync_key]);
                $activated_power_ups[] = 'mailchimp_connect';
                $update_active_power_ups = TRUE;
            }

            $constant_contact_list_sync_key = array_search('constant_contact_list_sync', $activated_power_ups);
            if ( $constant_contact_list_sync_key !== FALSE )
            {
                unset($activated_power_ups[$constant_contact_list_sync_key]);
                $activated_power_ups[] = 'constant_contact_connect';
                $update_active_power_ups = TRUE;
            }

            if ( $update_active_power_ups )
                update_option('leadin_active_power_ups', serialize($activated_power_ups));
        }

        // 0.7.2 bug fix - data recovery algorithm for deleted contacts
        if ( ! isset($options['data_recovered']) )
        {
            leadin_recover_contact_data();
        }

         // Check if the pro flag is not set and version > 3.0 and if yes, set the pro flag. This resets the Pro flag and takes care of the settings bug from Pro 3.1.3
        if ( ! isset($options['pro']) ) 
        {  
            if ( isset($options['leadin_version']) && version_compare($options['leadin_version'], '3.0.0', '>=') && version_compare($options['leadin_version'], '3.1.4', '<') )
            {
                leadin_update_option('leadin_options', 'pro', 1);

                // Check to make sure the lookups power-up is activate for Pro users
                if ( ! in_array('lookups', $activated_power_ups) )
                    WPLeadIn::activate_power_up('lookups', FALSE);
            }
        }
        else
        {
            if ( $options['pro'] )
            {
                // Check to make sure the lookups power-up is activate for Pro users
                if ( ! in_array('lookups', $activated_power_ups) )
                    WPLeadIn::activate_power_up('lookups', FALSE);
            }
        }

        // Set the database version if it doesn't exist
        if ( isset($options['li_db_version']) )
        {
            if ( $options['li_db_version'] != LEADIN_DB_VERSION ) 
            {
                leadin_db_install();

                // 2.0.0 upgrade
                if ( ! isset($options['converted_to_tags']) )
                {
                    leadin_convert_statuses_to_tags();
                }

                // 2.2.3 upgrade
                if ( ! isset($options['names_added_to_contacts']) )
                {
                    leadin_set_names_retroactively();
                }
            }
        }
        else
        {
            leadin_db_install();
        }

        // 0.8.3 bug fix - bug fix for duplicated contacts that should be merged
        if ( ! isset($options['delete_flags_fixed']) )
        {
            leadin_delete_flag_fix();
        }

        // Set the plugin version
        leadin_update_option('leadin_options', 'leadin_version', LEADIN_PLUGIN_VERSION);

        if ( leadin_check_pro_user() )
        {
            $leadin_user = leadin_get_current_user();
            leadin_set_user_properties(array(
                '$wp-url'       => get_bloginfo('wpurl'),
                '$wp-version'   => $leadin_user['wp_version'],
                '$li-version'   => $leadin_user['li_version']
            ));
        }

        // Catch all for installs that get their options nixed for whatever reason
        leadin_check_missing_options($options);
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

        self::check_admin_action();

        if ( ini_get('allow_url_fopen') )
            $leadin_icon = ($wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? LEADIN_PATH . '/images/leadin-icon-32x32.png' : 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(LEADIN_PATH . '/images/leadin-svg-icon.svg')));
        else
        {

            $leadin_icon = LEADIN_PATH . '/images/leadin-icon-16x16.png';
        }

        add_menu_page('Leadin', 'Leadin', $capability, 'leadin_stats', array($this, 'leadin_build_stats_page'),  $leadin_icon , '25.100713');

        foreach ( $this->admin_power_ups as $power_up )
        {
            if ( $power_up->activated )
            {
                $power_up->admin_init();

                // Creates the menu icon for power-up if it's set. Overrides the main Leadin menu to hit the contacts power-up
                if ( $power_up->menu_text )
                    add_submenu_page('leadin_stats', $power_up->menu_text, $power_up->menu_text, $capability, 'leadin_' . $power_up->menu_link, array($power_up, 'power_up_setup_callback'));    
            }
        }

        add_submenu_page('leadin_stats', 'Tags', 'Tags', $capability, 'leadin_tags', array(&$this, 'leadin_build_tag_page'));
        add_submenu_page('leadin_stats', 'Settings', 'Settings', 'activate_plugins', 'leadin_settings', array(&$this, 'leadin_plugin_options'));
        add_submenu_page('leadin_stats', 'Power-ups', 'Power-ups', 'activate_plugins', 'leadin_power_ups', array(&$this, 'leadin_power_ups_page'));
        
        if ( ! leadin_check_pro_user() )
            add_submenu_page('leadin_stats', 'Pro Upgrade', 'Pro Upgrade', 'activate_plugins', 'leadin_pro_upgrade', array(&$this, 'leadin_pro_upgrade_page'));

        $submenu['leadin_stats'][0][0] = 'Stats';

        if ( !isset($_GET['page']) || $_GET['page'] != 'leadin_settings' )
        {
            $options = get_option('leadin_options');
            if ( !isset($options['ignore_settings_popup']) || !$options['ignore_settings_popup'] )
                $li_pointers = new LI_Pointers();
        }

        
    }

    function leadin_pro_upgrade_page ()
    {
       global  $wp_version;

        echo '<div id="leadin" class=" wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
        
        $this->leadin_header('Upgrade to Leadin Pro for free');
        
        ?>
        <p>Leadin Pro is the best version of Leadin to date. </p>
           <div class="compare">
                <div class="title">
                    <h2>Leadin (current version)</h2>
                </div>
                <div class="content">
                    <ul class="features">
                        <li>&#10003; Contacts Tracking</li>
                        <p>Learn more about your visitors.</p>
                        
                        <li>&#10003; Contacts Analytics</li>
                        <p>Find out what content and traffic sources convert the best.</p>
                        
                        <li>&#10003; Popup Form</li>
                        <p>Convert more visitors to contacts, faster.</p>
                        
                        <li>&#10003; Email Connectors</li>
                        <p>Push contacts to MailChimp, Constant Contact, Campaign Monitor, GetResponse and AWeber without replacing any of your forms.</p>
                    </ul>
                </div>
            </div>
            <div class="compare">
                <div class="title teal">        
                    <h2>Leadin Pro</h2>
                </div>
                <div class="content">
                    <p>All the features you're enjoying now, plus:</p>
                    <ul class="features">
                        <li><b>+</b> Contact Enrichment</li>
                        <p>In addition to seeing the pages your contacts have visited, you'll now be able to see publicly available information about your contacts and their businesses. Info like social accounts, job role, and company location all in your contact reports. </p>

                        <li><b>+</b> Improved Email Delivery</li>
                        <p>Instead of using your default mail server, we'll route your contact notifications through our email delivery service, ensuring you'll be more likely to actually receive your new contact notifications in your inbox.</p>
                    </ul>
                    
                    <p id="agree-pp-error" style="display: none; border-left: 4px solid #dd3d36; padding-left: 12px; margin-bottom: 25px;">
                        Before you can unlock the awesomeness of Leadin Pro, we need you to agree to our Privacy Policy, because lawyers.
                    </p>

                    <label for="agree-pp">
                        <input type="checkbox" id="agree-pp" name="agree-pp"/>
                        I agree to Leadin's <a href="http://leadin.com/legal/privacy-policy" target="_blank">Privacy Policy</a>
                    </label>
                    
                    <a id="pro-upgrade-button" class="big-button--orange">Upgrade to Leadin Pro</a>
                </div>
            </div>

            
                    <?php

        $this->leadin_footer();

        //end wrap
        echo '</div>';

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
        $url = get_admin_url() . 'admin.php?page=leadin_settings';
        $settings_link = '<a href="' . $url . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Creates the stats page
     */
    function leadin_build_stats_page ()
    {
        global $wp_version;
        $this->stats_dashboard = new LI_StatsDashboard();

        echo '<div id="leadin" class="li-stats wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
        
        $this->leadin_header('Leadin Stats: ' . date('F j Y, g:ia', current_time('timestamp')), 'leadin-stats__header', 'Loaded Stats Page');

        echo '<div class="leadin-stats__top-container">';
            echo $this->leadin_postbox('leadin-stats__chart', leadin_single_plural_label(number_format($this->stats_dashboard->total_contacts_last_30_days), 'new contact', 'new contacts') . ' last 30 days', $this->leadin_build_contacts_chart_stats());
        echo '</div>';

        echo '<div class="leadin-stats__postbox_containter">';
            echo $this->leadin_postbox('leadin-stats__new-contacts', leadin_single_plural_label(number_format($this->stats_dashboard->total_new_contacts), 'new contact', 'new contacts') . ' today', $this->leadin_build_new_contacts_postbox());
            echo $this->leadin_postbox('leadin-stats__returning-contacts', leadin_single_plural_label(number_format($this->stats_dashboard->total_returning_contacts), 'returning contact', 'returning contacts') . ' today', $this->leadin_build_returning_contacts_postbox());
        echo '</div>';



        echo '<div class="leadin-stats__postbox_containter">';
            echo $this->leadin_postbox('leadin-stats__sources', 'New contact sources last 30 days', $this->leadin_build_sources_postbox());
        echo '</div>';

        $this->leadin_footer();
    }


    /**
     * Creates the stats page
     */
    function leadin_build_tag_page ()
    {
        global $wp_version;

        if ( isset($_POST['tag_name']) )
        {
            $tag_id = ( isset($_POST['tag_id']) ? $_POST['tag_id'] : FALSE );
            $tagger = new LI_Tag_Editor($tag_id);

            $tag_name           = $_POST['tag_name'];
            $tag_form_selectors = '';
            $tag_synced_lists   = array();

            foreach ( $_POST as $name => $value )
            {
                // Create a comma deliniated list of selectors for tag_form_selectors
                if ( strstr($name, 'email_form_tags_') )
                {
                    $tag_selector = '';
                    if ( strstr($name, '_class') )
                        $tag_selector = str_replace('email_form_tags_class_', '.', $name);
                    else if ( strstr($name, '_id') )
                        $tag_selector = str_replace('email_form_tags_id_', '#', $name);

                    if ( $tag_selector )
                    {
                        if ( ! strstr($tag_form_selectors, $tag_selector) )
                            $tag_form_selectors .= $tag_selector . ',';
                    }
                } // Create a comma deliniated list of synced lists for tag_synced_lists
                else if ( strstr($name, 'email_connect_') )
                {
                    // Name comes through as email_connect_espslug_listid, so replace the beginning of each one with corresponding esp slug, which leaves just the list id
                    if ( strstr($name, '_mailchimp') )
                        $synced_list = array('esp' => 'mailchimp', 'list_id' => str_replace('email_connect_mailchimp_', '', $name), 'list_name' => $value);
                    else if ( strstr($name, '_constant_contact') )
                        $synced_list = array('esp' => 'constant_contact', 'list_id' => str_replace('email_connect_constant_contact_', '', $name), 'list_name' => $value);
                    else if ( strstr($name, '_aweber') )
                        $synced_list = array('esp' => 'aweber', 'list_id' => str_replace('email_connect_aweber_', '', $name), 'list_name' => $value);
                    else if ( strstr($name, '_campaign_monitor') )
                        $synced_list = array('esp' => 'campaign_monitor', 'list_id' => str_replace('email_connect_campaign_monitor_', '', $name), 'list_name' => $value);
                    else if ( strstr($name, '_getresponse') )
                        $synced_list = array('esp' => 'getresponse', 'list_id' => str_replace('email_connect_getresponse_', '', $name), 'list_name' => $value);

                    array_push($tag_synced_lists, $synced_list);
                }
            }

            if ( $_POST['email_form_tags_custom'] )
            {
                if ( strstr($_POST['email_form_tags_custom'], ',') )
                {
                    foreach ( explode(',', $_POST['email_form_tags_custom']) as $tag )
                    {
                        if ( ! strstr($tag_form_selectors, $tag) )
                            $tag_form_selectors .= $tag . ',';
                    }
                }
                else
                {
                    if ( ! strstr($tag_form_selectors, $_POST['email_form_tags_custom']) )
                        $tag_form_selectors .= $_POST['email_form_tags_custom'] . ',';
                }
            }

            // Sanitize the selectors by removing any spaces and any trailing commas
            $tag_form_selectors = rtrim(str_replace(' ', '', $tag_form_selectors), ',');

            if ( $tag_id )
            {
                $tagger->save_tag(
                    $tag_id,
                    $tag_name,
                    $tag_form_selectors,
                    serialize($tag_synced_lists)
                );
            }
            else
            {
                $tagger->tag_id = $tagger->add_tag(
                    $tag_name,
                    $tag_form_selectors,
                    serialize($tag_synced_lists)
                );
            }
        }

        echo '<div id="leadin" class="wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php')  ? 'pre-mp6' : ''). '">';

            if ( $this->action == 'edit_tag' || $this->action == 'add_tag' ) {
                $this->leadin_render_tag_editor();
            }
            else
            {
                $this->leadin_render_tag_list_page();
            }

            $this->leadin_footer();

        echo '</div>';
    }

    /**
     * Creates list table for Contacts page
     *
     */
    function leadin_render_tag_editor ()
    {
        ?>
        <div class="leadin-contacts">
            <?php

                if ( $this->action == 'edit_tag' || $this->action == 'add_tag' )
                {
                    $tag_id = ( isset($_GET['tag']) ? $_GET['tag'] : FALSE);
                    $tagger = new LI_Tag_Editor($tag_id);
                }

                if ( $tagger->tag_id )
                    $tagger->get_tag_details($tagger->tag_id);
                
                echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_tags">&larr; Manage tags</a>';

                if ( $this->action == 'edit_tag' ) {
                    $this->leadin_header('Edit a tag', 'leadin-contacts__header', 'Loaded Tag Editor');
                } else {
                    $this->leadin_header('Add a tag', 'leadin-contacts__header', 'Loaded Add Tag');
                }
            ?>

            <div class="">
                <form id="leadin-tag-settings" action="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_tags'; ?>" method="POST">

                    <table class="form-table"><tbody>
                        <tr>
                            <th scope="row"><label for="tag_name">Tag name</label></th>
                            <td><input name="tag_name" type="text" id="tag_name" value="<?php echo ( isset($tagger->details->tag_text) ? $tagger->details->tag_text : '' ); ?>" class="regular-text" placeholder="Tag Name"></td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Automatically tag contacts who fill out any of these forms</th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span>Automatically tag contacts who fill out any of these forms</span></legend>
                                    <?php 
                                        $tag_form_selectors = ( isset($tagger->details->tag_form_selectors) ? explode(',', str_replace(' ', '', $tagger->details->tag_form_selectors)) : '');
                                        
                                        foreach ( $tagger->selectors as $selector )
                                        {
                                            $html_id = 'email_form_tags_' . str_replace(array('#', '.'), array('id_', 'class_'), $selector); 
                                            $selector_set = FALSE;
                                            
                                            if ( isset($tagger->details->tag_form_selectors) && strstr($tagger->details->tag_form_selectors, $selector) )
                                            {
                                                $selector_set = TRUE;
                                                $key = array_search($selector, $tag_form_selectors);
                                                if ( $key !== FALSE )
                                                    unset($tag_form_selectors[$key]);
                                            }
                                            
                                            echo '<label for="' . $html_id . '">';
                                                echo '<input name="' . $html_id . '" type="checkbox" id="' . $html_id . '" value="" ' . ( $selector_set ? 'checked' : '' ) . '>';
                                                echo $selector;
                                            echo '</label><br>';
                                        }
                                    ?>
                                </fieldset>
                                <br>
                                <input name="email_form_tags_custom" type="text" value="<?php echo ( $tag_form_selectors ? implode(', ', $tag_form_selectors) : ''); ?>" class="regular-text" placeholder="#form-id, .form-class">
                                <p class="description">Include additional form's css selectors.</p>
                            </td>
                        </tr>

                        
                        <?php
                            $esp_power_ups = array(
                                'MailChimp'         => 'mailchimp_connect', 
                                'Constant Contact'  => 'constant_contact_connect', 
                                'AWeber'            => 'aweber_connect', 
                                'GetResponse'       => 'getresponse_connect', 
                                'MailPoet'          => 'mailpoet_connect', 
                                'Campaign Monitor'  => 'campaign_monitor_connect'
                            );

                            foreach ( $esp_power_ups as $power_up_name => $power_up_slug )
                            {
                                if ( WPLeadIn::is_power_up_active($power_up_slug) )
                                {
                                    global ${'leadin_' . $power_up_slug . '_wp'}; // e.g leadin_mailchimp_connect_wp
                                    $esp_name = strtolower(str_replace('_connect', '', $power_up_slug)); // e.g. mailchimp
                                    $lists = ${'leadin_' . $power_up_slug . '_wp'}->admin->li_get_lists();
                                    $synced_lists = ( isset($tagger->details->tag_synced_lists) ? unserialize($tagger->details->tag_synced_lists) : '' );

                                    echo '<tr>';
                                        echo '<th scope="row">Push tagged contacts to these ' . $power_up_name . ' lists</th>';
                                        echo '<td>';
                                            echo '<fieldset>';
                                                echo '<legend class="screen-reader-text"><span>Push tagged contacts to these ' . $power_up_name . ' email lists</span></legend>';
                                                //
                                                $esp_name_readable = ucwords(str_replace('_', ' ', $esp_name));
                                                $esp_url = str_replace('_', '', $esp_name) . '.com';

                                                switch ( $esp_name ) 
                                                {
                                                    case 'mailchimp' :
                                                        $esp_list_url = 'http://admin.mailchimp.com/lists/new-list/';
                                                        $settings_page_anchor_id = '#li_mls_api_key';
                                                        $invalid_key_message = 'It looks like your ' . $esp_name_readable . ' API key is invalid...<br/><br/>';
                                                        $invalid_key_link = '<a target="_blank" href="http://kb.mailchimp.com/accounts/management/about-api-keys#Find-or-Generate-Your-API-Key">Get your API key</a> from <a href="http://admin.mailchimp.com/account/api/" target="_blank">MailChimp.com</a>';
                                                    break;

                                                    case 'constant_contact' :
                                                        $esp_list_url = 'https://login.constantcontact.com/login/login.sdo?goto=https://ui.constantcontact.com/rnavmap/distui/contacts';
                                                        $settings_page_anchor_id = '#li_cc_email';
                                                    break;

                                                    case 'aweber' :
                                                        $esp_list_url = 'https://www.aweber.com/users/newlist#about';
                                                        $settings_page_anchor_id = '#li_ac_auth_code';
                                                        $invalid_key_message = 'It looks like your ' . $esp_name_readable . ' Authorization Code is invalid...<br/><br/>';
                                                        $invalid_key_link = '<a target="_blank" href="https://help.aweber.com/hc/en-us/articles/204031226-How-Do-I-Authorize-an-App-">Get your Authorization Code</a> from <a href="https://auth.aweber.com/1.0/oauth/authorize_app/156b03fb" target="_blank">AWeber.com</a>';
                                                    break;

                                                    case 'campaign_monitor' :
                                                        $esp_list_url = 'https://login.createsend.com/l';
                                                        $settings_page_anchor_id = '#li_cm_api_key';
                                                        $invalid_key_message = 'It looks like your ' . $esp_name_readable . ' API key is invalid...<br/><br/>';
                                                        $invalid_key_link = '<a target="_blank" href="http://help.campaignmonitor.com/topic.aspx?t=206">Get your API key</a> from <a href="https://login.createsend.com/l" target="_blank">CampaignMonitor.com</a>';
                                                    break;

                                                    case 'getresponse' :
                                                        $esp_list_url = 'https://app.getresponse.com/create_campaign.html';
                                                        $settings_page_anchor_id = '#li_gr_api_key';
                                                        $invalid_key_message = 'It looks like your ' . $esp_name_readable . ' API key is invalid...<br/><br/>';
                                                        $invalid_key_link = '<a target="_blank" href="http://support.getresponse.com/faq/where-i-find-api-key">Get your API key</a> from <a href="https://app.getresponse.com/account.html#api" target="_blank">GetResponse.com</a>';
                                                    break;

                                                    default:
                                                        $esp_list_url = '';
                                                        $settings_page_anchor_id = '';
                                                    break;
                                                }

                                                if ( ! ${'leadin_' . $power_up_slug . '_wp'}->admin->authed )
                                                {
                                                    echo 'It looks like you haven\'t set up your ' . $esp_name_readable . ' integration yet...<br/><br/>';
                                                    echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_settings' . $settings_page_anchor_id . '">Set up your ' . $esp_name_readable . ' integration</a>';
                                                }
                                                else if ( ${'leadin_' . $power_up_slug . '_wp'}->admin->invalid_key )
                                                {
                                                    echo $invalid_key_message;
                                                    echo '<p>' . $invalid_key_link . ' then try copying and pasting it again in <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_settings' . $settings_page_anchor_id . '">Leadin → Settings</a></p>';
                                                }
                                                else if ( count($lists) )
                                                {
                                                    foreach ( $lists as $list )
                                                    {
                                                        $list_id = $list->id;

                                                        // Hack for constant contact's list id string (e.g. http://api.constantcontact.com/ws/customers/name%40website.com/lists/1234567890) 
                                                        if ( $power_up_name == 'Constant Contact' )
                                                            $list_id = end(explode('/', $list_id));

                                                        $html_id = 'email_connect_' . $esp_name . '_' . $list_id;
                                                        $synced = FALSE;

                                                        if ( $synced_lists )
                                                        {
                                                            
                                                            // Search the synched lists for this tag for the list_id
                                                            $key = leadin_array_search_deep($list_id, $synced_lists, 'list_id');

                                                            if ( isset($key) )
                                                            {
                                                                // Double check that the list is synced with the actual ESP
                                                                if ( $synced_lists[$key]['esp'] == $esp_name )
                                                                    $synced = TRUE;
                                                            }
                                                        }

                                                        echo '<label for="' . $html_id  . '">';
                                                            echo '<input name="' . $html_id  . '" type="checkbox" id="' . $html_id  . '" value="' . $list->name . '" ' . ( $synced ? 'checked' : '' ) . '>';
                                                            echo $list->name;
                                                        echo '</label><br>';
                                                    }
                                                }
                                                else
                                                {
                                                    echo 'It looks like you don\'t have any ' . $esp_name_readable . ' lists yet...<br/><br/>';
                                                    echo '<a href="' . $esp_list_url . '" target="_blank">Create a list on ' . $esp_url . '</a>';
                                                }
                                            echo '</fieldset>';
                                        echo '</td>';
                                    echo '</tr>';
                                }
                            }
                        ?>
                        
                    </tbody></table>
                    <input type="hidden" name="tag_id" value="<?php echo $tag_id; ?>"/>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
                    </p>
                </form>
            </div>

        </div>

        <?php
    }

    /**
     * Creates list table for Contacts page
     *
     */
    function leadin_render_tag_list_page ()
    {
        global $wp_version;

        if ( $this->action == 'delete_tag')
        {
            $tag_id = ( isset($_GET['tag']) ? $_GET['tag'] : FALSE);
            $tagger = new LI_Tag_Editor($tag_id);
            $tagger->delete_tag($tag_id);
        }

        //Create an instance of our package class...
        $leadinTagsTable = new LI_Tags_Table();

        // Process any bulk actions before the contacts are grabbed from the database
        $leadinTagsTable->process_bulk_action();
        
        //Fetch, prepare, sort, and filter our data...
        $leadinTagsTable->data = $leadinTagsTable->get_tags();
        $leadinTagsTable->prepare_items();

        ?>
        <div class="leadin-contacts">

            <?php
                $this->leadin_header('Manage Leadin Tags <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_tags&action=add_tag" class="add-new-h2">Add New</a>', 'leadin-contacts__header', 'Loaded Tag List');
            ?>
            
            <div class="">

                <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
                <form id="" method="GET">
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                    
                    <div class="leadin-contacts__table">
                        <?php $leadinTagsTable->display();  ?>
                    </div>

                    <input type="hidden" name="contact_type" value="<?php echo ( isset($_GET['contact_type']) ? $_GET['contact_type'] : '' ); ?>"/>
                   
                    <?php if ( isset($_GET['filter_content']) ) : ?>
                        <input type="hidden" name="filter_content" value="<?php echo ( isset($_GET['filter_content']) ? stripslashes($_GET['filter_content']) : '' ); ?>"/>
                    <?php endif; ?>

                    <?php if ( isset($_GET['filter_action']) ) : ?>
                        <input type="hidden" name="filter_action" value="<?php echo ( isset($_GET['filter_action']) ? $_GET['filter_action'] : '' ); ?>"/>
                    <?php endif; ?>

                </form>
                
            </div>

        </div>

        <?php
    }


    /**
     * Creates contacts chart content
     */
    function leadin_build_contacts_chart_stats () 
    {
        $contacts_chart = "";

        $contacts_chart .= "<div class='leadin-stats__chart-container'>";
            $contacts_chart .= "<div id='contacts_chart' style='width:100%; height:250px;'></div>";
        $contacts_chart .= "</div>";
        $contacts_chart .= "<div class='leadin-stats__big-numbers-container'>";
            $contacts_chart .= "<div class='leadin-stats__big-number'>";
                $contacts_chart .= "<label class='leadin-stats__big-number-top-label'>TODAY</label>";
                $contacts_chart .= "<h1  class='leadin-stats__big-number-content'>" . number_format($this->stats_dashboard->total_new_contacts) . "</h1>";
                $contacts_chart .= "<label class='leadin-stats__big-number-bottom-label'>new " . ( $this->stats_dashboard->total_new_contacts != 1 ? 'contacts' : 'contact' ) . "</label>";
            $contacts_chart .= "</div>";
            $contacts_chart .= "<div class='leadin-stats__big-number big-number--average'>";
                $contacts_chart .= "<label class='leadin-stats__big-number-top-label'>AVG LAST 90 DAYS</label>";
                $contacts_chart .= "<h1  class='leadin-stats__big-number-content'>" . number_format($this->stats_dashboard->avg_contacts_last_90_days) . "</h1>";
                $contacts_chart .= "<label class='leadin-stats__big-number-bottom-label'>new " . ( $this->stats_dashboard->avg_contacts_last_90_days != 1 ? 'contacts' : 'contact' ) . "</label>";
            $contacts_chart .= "</div>";
            $contacts_chart .= "<div class='leadin-stats__big-number'>";
                $contacts_chart .= "<label class='leadin-stats__big-number-top-label'>BEST DAY EVER</label>";
                $contacts_chart .= "<h1  class='leadin-stats__big-number-content'>" . number_format($this->stats_dashboard->best_day_ever) . "</h1>";
                $contacts_chart .= "<label class='leadin-stats__big-number-bottom-label'>new " . ( $this->stats_dashboard->best_day_ever != 1 ? 'contacts' : 'contact' ) . "</label>";
            $contacts_chart .= "</div>";
            $contacts_chart .= "<div class='leadin-stats__big-number'>";
                $contacts_chart .= "<label class='leadin-stats__big-number-top-label'>ALL TIME</label>";
                $contacts_chart .= "<h1  class='leadin-stats__big-number-content'>" . number_format($this->stats_dashboard->total_contacts) . "</h1>";
                $contacts_chart .= "<label class='leadin-stats__big-number-bottom-label'>total " . ( $this->stats_dashboard->total_contacts != 1 ? 'contacts' : 'contact' ) . "</label>";
            $contacts_chart .= "</div>";
        $contacts_chart .= "</div>";

        return $contacts_chart;
    }

     /**
     * Creates contacts chart content
     */
    function leadin_build_new_contacts_postbox () 
    {
        $new_contacts_postbox = "";

        if ( count($this->stats_dashboard->new_contacts) )
        {
            $new_contacts_postbox .= '<table class="leadin-postbox__table"><tbody>';
                $new_contacts_postbox .= '<tr>';
                    $new_contacts_postbox .= '<th>contact</th>';
                    $new_contacts_postbox .= '<th>pageviews</th>';
                    $new_contacts_postbox .= '<th>original source</th>';
                $new_contacts_postbox .= '</tr>';

                foreach ( $this->stats_dashboard->new_contacts as $contact )
                {
                    $new_contacts_postbox .= '<tr>';
                        $new_contacts_postbox .= '<td class="">';
                            $new_contacts_postbox .= '<a href="?page=leadin_contacts&action=view&lead=' . $contact->lead_id . '&stats_dashboard=1"><img class="lazy pull-left leadin-contact-avatar leadin-dynamic-avatar_' . substr($contact->lead_id, -1) .'" src="https://api.hubapi.com/socialintel/v1/avatars?email=' . $contact->lead_email . '" width="35" height="35"><b>' . $contact->lead_email . '</b></a>';
                        $new_contacts_postbox .= '</td>';
                        $new_contacts_postbox .= '<td class="">' . $contact->pageviews . '</td>';
                        $new_contacts_postbox .= '<td class="">' . $this->stats_dashboard->print_readable_source($this->stats_dashboard->check_lead_source($contact->lead_source, $contact->lead_origin_url)) . '</td>';
                    $new_contacts_postbox .= '</tr>';
                }

            $new_contacts_postbox .= '</tbody></table>';
        }
        else
            $new_contacts_postbox .= '<i>No new contacts today...</i>';

        return $new_contacts_postbox;
    }

    /**
     * Creates contacts chart content
     */
    function leadin_build_returning_contacts_postbox () 
    {
        $returning_contacts_postbox = "";

        if ( count($this->stats_dashboard->returning_contacts) )
        {
            $returning_contacts_postbox .= '<table class="leadin-postbox__table"><tbody>';
                $returning_contacts_postbox .= '<tr>';
                    $returning_contacts_postbox .= '<th>contact</th>';
                    $returning_contacts_postbox .= '<th>pageviews</th>';
                    $returning_contacts_postbox .= '<th>original source</th>';
                $returning_contacts_postbox .= '</tr>';

                foreach ( $this->stats_dashboard->returning_contacts as $contact )
                {
                    $returning_contacts_postbox .= '<tr>';
                        $returning_contacts_postbox .= '<td class="">';
                            $returning_contacts_postbox .= '<a href="?page=leadin_contacts&action=view&lead=' . $contact->lead_id . '&stats_dashboard=1"><img class="lazy pull-left leadin-contact-avatar leadin-dynamic-avatar_' . substr($contact->lead_id, -1) .'" src="https://api.hubapi.com/socialintel/v1/avatars?email=' . $contact->lead_email . '" width="35" height="35"><b>' . $contact->lead_email . '</b></a>';
                        $returning_contacts_postbox .= '</td>';
                        $returning_contacts_postbox .= '<td class="">' . $contact->pageviews . '</td>';
                        $returning_contacts_postbox .= '<td class="">' . $this->stats_dashboard->print_readable_source($this->stats_dashboard->check_lead_source($contact->lead_source, $contact->lead_origin_url)) . '</td>';
                    $returning_contacts_postbox .= '</tr>';
                }

            $returning_contacts_postbox .= '</tbody></table>';
        }
        else
            $returning_contacts_postbox .= '<i>No returning contacts today...</i>';

        return $returning_contacts_postbox;
    }

    /**
     * Creates contacts chart content
     */
    function leadin_build_sources_postbox () 
    {
        $sources_postbox = "";

        $sources_postbox .= '<table class="leadin-postbox__table"><tbody>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<th width="150">source</th>';
                $sources_postbox .= '<th width="20">Contacts</th>';
                $sources_postbox .= '<th></th>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Direct Traffic</td>';
                $sources_postbox .= '<td class="sources-contacts-num">' . $this->stats_dashboard->direct_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->direct_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Organic Search</td>';
                $sources_postbox .= '<td class="sources-contacts-num">' . $this->stats_dashboard->organic_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->organic_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Referrals</td>';
                $sources_postbox .= '<td class="sources-contacts-num">' . $this->stats_dashboard->referral_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->referral_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Social Media</td>';
                $sources_postbox .= '<td class="sources-contacts-num">' . $this->stats_dashboard->social_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->social_count/$this->stats_dashboard->max_source)*100) : '0' ). '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Email Marketing</td>';
                $sources_postbox .= '<td class="sources-contacts-num">' . $this->stats_dashboard->email_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->email_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Paid Search</td>';
                $sources_postbox .= '<td class="sources-contacts-num">' . $this->stats_dashboard->paid_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->paid_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
        $sources_postbox .= '</tbody></table>';

        return $sources_postbox;
    }

    /**
     * Creates settings options
     */
    function leadin_build_settings_page ()
    {
        global $leadin_contacts;

        //echo get_site_url();

        //print_r($_POST);
        
        // Show the settings popup on all pages except the settings page
        if ( isset($_GET['page']) && $_GET['page'] == 'leadin_settings' )
        {
            $options = get_option('leadin_options');
            if ( ! isset($options['ignore_settings_popup']) || ! $options['ignore_settings_popup'] )
                leadin_update_option('leadin_options', 'ignore_settings_popup', 1);
        }
        
        register_setting(
            'leadin_settings_options',
            'leadin_options',
            array($this, 'sanitize')
        );

        $visitor_tracking_icon = $leadin_contacts->icon_small;
        
        add_settings_section(
            'leadin_settings_section',
            $visitor_tracking_icon . 'Visitor Tracking',
            array($this, 'leadin_options_section_heading'),
            LEADIN_ADMIN_PATH
        );
        
        add_settings_field(
            'li_email',
            'Your email',
            array($this, 'li_email_callback'),
            LEADIN_ADMIN_PATH,
            'leadin_settings_section'
        );

        add_settings_field(
            'li_do_not_track',
            'Do not track logged in',
            array($this, 'li_do_not_track_callback'),
            LEADIN_ADMIN_PATH,
            'leadin_settings_section'
        );

        add_settings_field(
            'li_grant_access',
            'Grant Leadin access to',
            array($this, 'li_grant_access_callback'),
            LEADIN_ADMIN_PATH,
            'leadin_settings_section'
        );

        add_filter(
            'update_option_leadin_options',
            array($this, 'update_option_leadin_options_callback'),
            10,
            2
        );

    }

    function leadin_options_section_heading ( )
    {
        $this->print_hidden_settings_fields();

        if ( $this->has_leads() )
        {
            echo '<div class="leadin-section">';
                echo '<p style="color: #090; font-weight: bold;">Visitor tracking is installed and tracking visitors.</p>';
                echo '<p>The next time a visitor fills out a form on your WordPress site with an email address, Leadin will send you an email with the contact\'s referral source and page view history.</p>';
            echo '</div>';
        }
        else
        {
            echo '<div class="leadin-section">';
                echo '<p style="color: #f67d42; font-weight: bold;">Leadin is set up and waiting for a form submission...</p>';
                echo '<p>Can\'t wait to see Leadin in action? Go fill out a form on your site to see your first contact.</p>';
            echo '</div>';
        }
    }

    function print_hidden_settings_fields ()
    {
         // Hacky solution to solve the Settings API overwriting the default values
        $options = get_option('leadin_options');

        $li_installed               = ( isset($options['li_installed']) ? $options['li_installed'] : 1 );
        $li_db_version              = ( isset($options['li_db_version']) ? $options['li_db_version'] : LEADIN_DB_VERSION );
        $ignore_settings_popup      = ( isset($options['ignore_settings_popup']) ? $options['ignore_settings_popup'] : 0 );
        $onboarding_complete        = ( isset($options['onboarding_complete']) ? $options['onboarding_complete'] : 0 );
        $onboarding_step            = ( isset($options['onboarding_step']) ? $options['onboarding_step'] : 1 );
        $data_recovered             = ( isset($options['data_recovered']) ? $options['data_recovered'] : 0 );
        $delete_flags_fixed         = ( isset($options['delete_flags_fixed']) ? $options['delete_flags_fixed'] : 0 );
        $converted_to_tags          = ( isset($options['converted_to_tags']) ? $options['converted_to_tags'] : 0 );
        $names_added_to_contacts    = ( isset($options['names_added_to_contacts']) ? $options['names_added_to_contacts'] : 0 );
        $leadin_version             = ( isset($options['leadin_version']) ? $options['leadin_version'] : LEADIN_PLUGIN_VERSION );
        $pro                        = ( isset($options['pro']) ? $options['pro'] : 0 );
        $li_updates_subscription    = ( isset($options['li_updates_subscription']) ? $options['li_updates_subscription'] : 0 );
        
        printf(
            '<input id="li_installed" type="hidden" name="leadin_options[li_installed]" value="%d"/>',
            $li_installed
        );

        printf(
            '<input id="li_db_version" type="hidden" name="leadin_options[li_db_version]" value="%s"/>',
            $li_db_version
        );

        printf(
            '<input id="ignore_settings_popup" type="hidden" name="leadin_options[ignore_settings_popup]" value="%d"/>',
            $ignore_settings_popup
        );

        printf(
            '<input id="onboarding_step" type="hidden" name="leadin_options[onboarding_step]" value="%d"/>',
            $onboarding_step
        );

        printf(
            '<input id="onboarding_complete" type="hidden" name="leadin_options[onboarding_complete]" value="%d"/>',
            $onboarding_complete
        );

        printf(
            '<input id="data_recovered" type="hidden" name="leadin_options[data_recovered]" value="%d"/>',
            $data_recovered
        );

        printf(
            '<input id="delete_flags_fixed" type="hidden" name="leadin_options[delete_flags_fixed]" value="%d"/>',
            $delete_flags_fixed
        );

        printf(
            '<input id="converted_to_tags" type="hidden" name="leadin_options[converted_to_tags]" value="%d"/>',
            $converted_to_tags
        );

        printf(
            '<input id="names_added_to_contacts" type="hidden" name="leadin_options[names_added_to_contacts]" value="%d"/>',
            $names_added_to_contacts
        );

        printf(
            '<input id="leadin_version" type="hidden" name="leadin_options[leadin_version]" value="%s"/>',
            $leadin_version
        );

        printf(
            '<input id="pro" type="hidden" name="leadin_options[pro]" value="%d"/>',
            $pro
        );

        printf(
            '<input id="li_updates_subscription" type="hidden" name="leadin_options[li_updates_subscription]" value="%d"/>',
            $li_updates_subscription
        );
    }

    function has_leads ( )
    {
        global $wpdb;

        $q = "SELECT COUNT(hashkey) FROM $wpdb->li_leads WHERE lead_deleted = 0 AND hashkey != '' AND lead_email != ''";
        $num_contacts = $wpdb->get_var($q);

        if ( $num_contacts > 0 )
           return TRUE;
        else
            return FALSE;
    }

    function update_option_leadin_options_callback ( $old_value, $new_value )
    {
    }

    /**
     * Creates settings page
     */
    function leadin_plugin_options ()
    {
        $li_options = get_option('leadin_options');
        
        // Load onboarding if not complete
        if ( $li_options['onboarding_complete'] == 0 ) {
            $this->leadin_plugin_onboarding();
        }
        else {
            $this->leadin_plugin_settings();
        }
    }

    /**
     * Creates onboarding settings page
     */
    function leadin_plugin_onboarding ()
    {
        global  $wp_version;

        $li_options = get_option('leadin_options');

        echo '<div id="leadin" class="li-onboarding wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
    
        
        ?>
        
        <?php if ( ! isset($_GET['activate_popup']) ) : ?>
            
            <?php if ( $li_options['onboarding_step'] == 1 ) : ?>

                <?php $this->leadin_header('Leadin Setup', 'li_setup', 'Onboarding Step 2 - Get Contact Reports'); ?>

                <div class="oboarding-steps">
                    <ol class="oboarding-steps-names">
                        <li class="oboarding-step-name completed">Activate Leadin</li>
                        <li class="oboarding-step-name active">Get Contact Reports</li>
                        <li class="oboarding-step-name">Grow Your Contacts List</li>
                    </ol>
                    <div class="oboarding-step">
                        <h2 class="oboarding-step-title">Where should we send your contact reports?</h2>
                        <div class="oboarding-step-content">
                            <p class="oboarding-step-description">Leadin will help you get to know your website visitors by sending you a report including traffic source and pageview history each time a visitor fills out a form.</p>
                            <form id="li-onboarding-form" method="post" action="options.php">
                                <div>
                                    <?php settings_fields('leadin_settings_options'); ?>
                                    <?php $this->li_email_callback(); ?>
                                </div>
                                <?php $this->print_hidden_settings_fields();  ?>
                                <input type="hidden" id="next_onboarding_step" name="next_onboarding_step" value="2">
                                <input type="submit" name="submit" id="submit" class="button button-primary button-big" value="<?php esc_attr_e('Save Email'); ?>">
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Facebook Conversion Code for Installed plugin -->
                <script>(function() {
                  var _fbq = window._fbq || (window._fbq = []);
                  if (!_fbq.loaded) {
                    var fbds = document.createElement('script');
                    fbds.async = true;
                    fbds.src = '//connect.facebook.net/en_US/fbds.js';
                    var s = document.getElementsByTagName('script')[0];
                    s.parentNode.insertBefore(fbds, s);
                    _fbq.loaded = true;
                  }
                })();
                window._fbq = window._fbq || [];
                window._fbq.push(['track', '6024677413664', {'value':'0.00','currency':'USD'}]);
                </script>
                <noscript><img height="1" width="1" alt="" style="display:none" src="https://www.facebook.com/tr?ev=6024677413664&amp;cd[value]=0.00&amp;cd[currency]=USD&amp;noscript=1" /></noscript>

            <?php elseif ( $li_options['onboarding_step'] == 2 ) : ?>

                <?php $this->leadin_header('Leadin Setup', 'li_setup', 'Onboarding Step 3 - Grow Your Contact List'); ?>

                <div class="oboarding-steps">
                    <ol class="oboarding-steps-names">
                        <li class="oboarding-step-name completed">Activate Leadin</li>
                        <li class="oboarding-step-name completed">Get Contact Reports</li>
                        <li class="oboarding-step-name active">Grow Your Contacts List</li>
                    </ol>
                    <div class="oboarding-step">
                        <h2 class="oboarding-step-title">Grow your contacts list with our popup form<br><small>and start converting visitors on <?php echo get_bloginfo('wpurl') ?></small></h2>
                        <form id="li-onboarding-form" method="post" action="options.php">
                            <?php $this->print_hidden_settings_fields();  ?>
                            <div class="popup-options">
                                <label class="popup-option">
                                    <input type="radio" name="popup-position" value="slide_in" checked="checked" >Slide in
                                    <img src="<?php echo LEADIN_PATH ?>/images/popup-bottom.png">
                                </label>
                                <label class="popup-option">
                                    <input type="radio" name="popup-position" value="popup">Popup
                                    <img src="<?php echo LEADIN_PATH ?>/images/popup-over.png">
                                </label>
                                <label class="popup-option">
                                    <input type="radio" name="popup-position" value="top">Top
                                    <img src="<?php echo LEADIN_PATH ?>/images/popup-top.png">
                                </label>
                            </div>
                            <input type="hidden" id="next_onboarding_step" name="next_onboarding_step" value="3">
                            <a id="btn-activate-subscribe" href="<?php echo get_admin_url() .'admin.php?page=leadin_settings&leadin_action=activate&power_up=subscribe_widget&redirect_to=' . get_admin_url() . urlencode('admin.php?page=leadin_settings&activate_popup=true&popup_position=slide_in'); ?>" class="button button-primary button-big"><?php esc_attr_e('Activate the popup form');?></a>
                            <p><a href="<?php echo get_admin_url() .'admin.php?page=leadin_settings&activate_popup=false'; ?>">Don't activate the popup form right now</a></p>
                        </form>
                    </div>
                </div>

            <?php endif; ?>

        <?php else : ?>

            <?php
                // Set the popup position based on get URL 

                if ( isset($_GET['popup_position']) )
                {
                    $vex_class_option = '';
                    switch ( $_GET['popup_position'] )
                    {
                        case 'slide_in' :
                            $vex_class_option = 'vex-theme-bottom-right-corner';
                        break;

                        case 'popup' :
                            $vex_class_option = 'vex-theme-default';
                        break;

                        case 'top' :
                            $vex_class_option = 'vex-theme-top';
                        break;
                    }

                    leadin_update_option('leadin_subscribe_options', 'li_subscribe_vex_class', $vex_class_option);
                    leadin_track_plugin_activity('Onboarding Popup Activated');
                }
                else
                    leadin_track_plugin_activity('Onboarding Popup Not Activated');

                // Update the onboarding settings
                if ( ! isset($options['onboarding_complete']) || ! $options['onboarding_complete'] )
                {
                    leadin_update_option('leadin_options', 'onboarding_complete', 1);
                }
            ?>
            
            <?php $this->leadin_header('Leadin Setup', 'li_setup', 'Onboarding Complete'); ?>

            <div class="oboarding-steps">
                <ol class="oboarding-steps-names">
                    <li class="oboarding-step-name completed">Activate Leadin</li>
                    <li class="oboarding-step-name completed">Get Contact Reports</li>
                    <li class="oboarding-step-name completed">Grow Your Contacts List</li>
                </ol>
                <div class="oboarding-step">
                    <h2 class="oboarding-step-title">Setup Complete!<br>Leadin is waiting for your first form submission.</h2>
                    <div class="oboarding-step-content">
                        <p class="oboarding-step-description">Leadin is set up and waiting for a form submission. Once Leadin detects a form submission, a new contact will be added to your contacts list. We recommend filling out a form on your site to test that Leadin is working correctly.</p>
                        <form id="li-onboarding-form" method="post" action="options.php">
                            <?php $this->print_hidden_settings_fields();  ?>
                            <a href="<?php echo get_admin_url() . 'admin.php?page=leadin_settings'; ?>" class="button button-primary button-big"><?php esc_attr_e('Complete Setup'); ?></a>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <?php
        
        $this->leadin_footer();

        //end wrap
        echo '</div>';
    }

    /**
     * Creates default settings page
     */
    function leadin_plugin_settings ()
    {
        global  $wp_version;

        echo '<div id="leadin" class="li-settings wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
        
        $this->leadin_header('Leadin Settings', 'li_settings', 'Loaded Settings Page');
        
        ?>
            <div class="leadin-settings__content">
                <form method="POST" action="options.php">
                    <?php 
                        settings_fields('leadin_settings_options');
                        do_settings_sections(LEADIN_ADMIN_PATH);
                        submit_button('Save Settings');
                    ?>
                </form>
            </div>
            <?php if ( ! leadin_check_pro_user() ) : ?>
                <div class="leadin-settings__sidebar">
                    <a href="<?php echo admin_url(); ?>admin.php?page=leadin_pro_upgrade"><img class="pro-upgrade-cta" src="<?php echo LEADIN_PATH; ?>/images/pro-upgrade-cta.png"></a>
                </div>
            <?php endif; ?>
        <?php

        $this->leadin_footer();

        //end wrap
        echo '</div>';
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize ( $input )
    {
        $new_input = array();

        if ( isset($input['li_email']) )
            $new_input['li_email'] = sanitize_text_field( $input['li_email'] );

        if ( isset($input['li_installed']) )
            $new_input['li_installed'] = $input['li_installed'];

        if ( isset($input['li_db_version']) )
            $new_input['li_db_version'] = $input['li_db_version'];

        if ( isset($input['onboarding_step']) )
            $new_input['onboarding_step'] = ( $input['onboarding_step'] + 1 );

        if ( isset($input['onboarding_complete']) )
            $new_input['onboarding_complete'] = $input['onboarding_complete'];

        if ( isset($input['ignore_settings_popup']) )
            $new_input['ignore_settings_popup'] = $input['ignore_settings_popup'];

        if ( isset($input['data_recovered']) )
            $new_input['data_recovered'] = $input['data_recovered'];

        if ( isset($input['converted_to_tags']) )
            $new_input['converted_to_tags'] = $input['converted_to_tags'];

        if ( isset($input['names_added_to_contacts']) )
            $new_input['names_added_to_contacts'] = $input['names_added_to_contacts'];

        if ( isset($input['delete_flags_fixed']) )
            $new_input['delete_flags_fixed'] = $input['delete_flags_fixed'];

        if ( isset($input['leadin_version']) )
            $new_input['leadin_version'] = $input['leadin_version'];

        if ( isset($input['li_updates_subscription']) )
            $new_input['li_updates_subscription'] = $input['li_updates_subscription'];

        if ( isset($input['pro']) )
            $new_input['pro'] = $input['pro'];

        $user_roles = get_editable_roles();
        if ( count($user_roles) )
        {
            //print_r($user_roles);
            foreach ( $user_roles as $key => $role )
            {
                $role_id_tracking = 'li_do_not_track_' . $key;
                $role_id_access = 'li_grant_access_to_' . $key;

                if ( isset( $input[$role_id_tracking] ) )
                    $new_input[$role_id_tracking] = $input[$role_id_tracking];

                if ( isset( $input[$role_id_access] ) )
                    $new_input[$role_id_access] = $input[$role_id_access];
            }
        }

        if( isset( $input['li_subscribe_template_home'] ) )
            $new_input['li_subscribe_template_home'] = sanitize_text_field( $input['li_subscribe_template_home'] );

        return $new_input;
    }

    /**
     * Prints email input for settings page
     */
    function li_email_callback ()
    {
        $options = get_option('leadin_options');
        $li_email = ( isset($options['li_email']) && $options['li_email'] ? $options['li_email'] : '' ); // Get email from plugin settings, if none set, use admin email
     
        printf(
            '<input id="li_email" type="text" id="title" name="leadin_options[li_email]" value="%s" size="50"/><br/><span class="description">Separate multiple emails with commas. Leave blank to disable email notifications.</span>',
            $li_email
        );    
    }

   /**
     * Prints do not track checkboxes for settings page
     */
    function li_do_not_track_callback ()
    {
        $options = get_option('leadin_options');
     
        $user_roles = get_editable_roles();
        if ( count($user_roles) )
        {
            foreach ( $user_roles as $key => $role )
            {
                $role_id = 'li_do_not_track_' . $key;
                printf(
                    '<p><input id="' . $role_id . '" type="checkbox" name="leadin_options[' . $role_id . ']" value="1"' . checked( 1, ( isset($options[$role_id]) ? $options[$role_id] : '0' ), FALSE ) . '/>' . 
                    '<label for="' . $role_id . '">' . $role['name'] . 's' . '</label></p>'
                );
            }
        }
    }

    /**
     * Prints checkboxes for toggling Leadin access to specific user roles
     */
    function li_grant_access_callback ()
    {
        $options = get_option('leadin_options');
     
        $user_roles = get_editable_roles();

        // Show a disabled checkbox for administrative roles that always need to be enabled so users don't get locked out of the Leadin settings
        echo '<p><input id="li_grant_access_to_administrator" type="checkbox" value="1" checked disabled/>';
        echo '<label for="li_grant_access_to_administrator">Administrators</label></p>';

        if ( count($user_roles) )
        {
            foreach ( $user_roles as $key => $role )
            {
                $admin_role = FALSE;
                if ( isset($role['capabilities']['activate_plugins']) && $role['capabilities']['activate_plugins'] )
                    $admin_role = TRUE;

                $role_id = 'li_grant_access_to_' . $key;

                if ( ! $admin_role )
                {
                    printf(
                        '<p><input id="' . $role_id . '" type="checkbox" name="leadin_options[' . $role_id . ']" value="1"' . checked( 1, ( isset($options[$role_id]) ? $options[$role_id] : '0' ), FALSE ) . '/>' . 
                        '<label for="' . $role_id . '">' . $role['name'] . 's' . '</label></p>'
                    );
                }
            }
        }
    }

    /**
     * Creates power-up page
     */
    function leadin_power_ups_page ()
    {
        global  $wp_version;

        echo '<div id="leadin" class="li-settings wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
        
        $this->leadin_header('Leadin Power-ups', 'li_powerups', 'Loaded Power-ups Page');
        
        ?>

            <p>Get the most out of your Leadin install with these powerful marketing powerups.</p>
            
            <ul class="powerup-list">

                <?php $power_up_count = 0; ?>
                <?php foreach ( $this->admin_power_ups as $power_up ) : ?>
                    <?php 
                        // Skip displaying the power-up on the power-ups page if it's hidden
                        if ( $power_up->hidden )
                            continue;
                    ?>

                    <?php if ( $power_up_count == 2 ) : ?>
                        <!-- static content stats power-up - not a real powerup and this is a hack to put it second in the order -->
                        <li class="powerup activated">
                            <div class="img-containter">
                                <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-analytics@2x.png" height="80px" width="80px">
                            </div>
                            <h2>Content Stats</h2>
                            <p>See where all your conversions are coming from.</p>
                            <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_stats'; ?>" class="button button-large">View Stats</a>
                        </li>
                        <?php $power_up_count++; ?>
                    <?php endif; ?>

                    <li class="powerup <?php echo ( $power_up->activated ? 'activated' : ''); ?>">
                        <div class="img-containter">
                            <?php if ( strstr($power_up->icon, 'dashicon') ) : ?>
                                <span class="<?php echo $power_up->icon; ?>"></span>
                            <?php else : ?>
                                <img src="<?php echo LEADIN_PATH . '/images/' . $power_up->icon . '@2x.png'; ?>" height="80px" width="80px"/>
                            <?php endif; ?>
                        </div>
                        <h2><?php echo $power_up->power_up_name; ?></h2>
                        <p><?php echo $power_up->description; ?></p>

                        <?php if ( $power_up->activated ) : ?>
                            <?php if ( ! $power_up->permanent ) : ?>
                                <?php // SHOW DEACTIVATE POWER-UP BUTTON ?>
                                <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_power_ups&leadin_action=deactivate&power_up=' . $power_up->slug; ?>" class="button button-secondary button-large">Deactivate</a>
                            <?php endif; ?>
                        <?php else : ?>
                            <?php // SHOW DEACTIVATE POWER-UP BUTTON ?>
                            <?php if ( $power_up->pro_only ) : ?>
                                <?php if ( ! leadin_check_pro_user() ) : ?>
                                    <a href="<?php echo admin_url(); ?>admin.php?page=leadin_pro_upgrade">Upgrade to Pro</a> for free to unlock
                                <?php endif; ?>
                            <?php else : ?>
                                <?php if ( ( $power_up->curl_required && function_exists('curl_init') && function_exists('curl_setopt') ) || ! $power_up->curl_required ) : ?>
                                    <?php // SHOW ACTIVATE POWER-UP BUTTON ?>
                                    <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_power_ups&leadin_action=activate&power_up=' . $power_up->slug; ?>" class="button button-primary button-large">Activate</a>
                                <?php else : ?>
                                    <?php // SHOW CURL REQUIRED MESSAGE ?>
                                    <p><a href="http://stackoverflow.com/questions/2939820/how-to-enable-curl-installed-ubuntu-lamp-stack" target="_blank">Install cURL</a> to use this power-up.</p>
                                <?php endif; ?>
                            <?php endif; ?> 
                        <?php endif; ?>
                        
                        <?php if ( $power_up->activated || ( $power_up->permanent && $power_up->activated ) ) : ?>

                            <?php if ( $power_up->slug == 'contacts' || $power_up->slug == 'lookups' ) : ?>
                                <?php // SHOW VIEW CONTACTS / CONFIGURE BUTTON ?>
                                <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts'; ?>" class="button button-secondary button-large">View Contacts</a>
                                <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_settings'; ?>" class="button button-secondary button-large">Configure</a>
                            <?php else : ?>
                                <?php // SHOW CONFIGURE BUTTON ?>
                                <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_' . $power_up->menu_link; ?>" class="button button-secondary button-large">Configure</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </li>
                    <?php $power_up_count++; ?>
                <?php endforeach; ?>

                <li class="powerup">
                    <div class="img-containter">
                        <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-ideas@2x.png" height="80px" width="80px">
                    </div>
                    <h2>Your Idea</h2>
                    <p>Have an idea for a power-up? We'd love to hear it!</p>
                    <a href="mailto:support@leadin.com" target="_blank" class="button button-primary button-large">Suggest an idea</a>
                </li>

                <li class="powerup">
                    <div class="img-containter">
                        <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-vip@2x.png" height="80px" width="80px">
                    </div>
                    <h2>Leadin VIP Program</h2>
                    <p>Exclusive features and offers for consultants and agencies.</p>

                    <a href="http://leadin.com/vip" target="_blank" class="button button-primary button-large">Become a VIP</a>
                </li>

            </ul>

        <?php

        
        $this->leadin_footer();
        
        //end wrap
        echo '</div>';

    }

    function check_admin_action ( )
    {
        if ( isset( $_GET['leadin_action'] ) ) 
        {
            switch ( $_GET['leadin_action'] ) 
            {
                case 'activate' :

                    $power_up = stripslashes( $_GET['power_up'] );
                    
                    WPLeadIn::activate_power_up( $power_up, FALSE );
                    //ob_end_clean();
                    leadin_track_plugin_activity($power_up . " power-up activated");
                    
                    if ( isset($_GET['redirect_to']) )
                        wp_redirect($_GET['redirect_to']);
                    else
                        wp_redirect(get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_power_ups');
                    exit;

                    break;

                case 'deactivate' :

                    $power_up = stripslashes( $_GET['power_up'] );
                    
                    WPLeadIn::deactivate_power_up( $power_up, FALSE );
                    leadin_track_plugin_activity($power_up . " power-up deactivated");
                    wp_redirect(get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_power_ups');
                    exit;

                    break;
            }
        }
    }

    //=============================================
    // Admin Styles & Scripts
    //=============================================

    /**
     * Adds admin style sheets
     */
    function add_leadin_admin_styles ()
    {
        wp_register_style('leadin-admin-css', LEADIN_PATH . '/assets/css/build/leadin-admin.css');
        wp_enqueue_style('leadin-admin-css');

        wp_register_style('select2', LEADIN_PATH . '/assets/css/select2.css');
        wp_enqueue_style('select2');
    }

    //=============================================
    // Internal Class Functions
    //=============================================

    /**
     * Creates postbox for admin
     *
     * @param string
     * @param string
     * @param string
     * @param bool
     * @return string   HTML for postbox
     */
    function leadin_postbox ( $css_class, $title, $content, $handle = TRUE )
    {
        $postbox_wrap = "";
        
        $postbox_wrap .= '<div class="' . $css_class . ' leadin-postbox">';
            $postbox_wrap .= '<h3 class="leadin-postbox__header">' . $title . '</h3>';
            $postbox_wrap .= '<div class="leadin-postbox__content">' . $content . '</div>';
        $postbox_wrap .= '</div>';

        return $postbox_wrap;
    }

    /**
     * Prints the admin page title, icon and help notification
     *
     * @param string
     */
    function leadin_header ( $page_title = '', $css_class = '', $event_name = '' )
    {
        $options = get_option('li_options');
        $leadin_user = leadin_get_current_user();
        ?>

        <?php if ( leadin_check_pro_user() ) : ?>
            <?php // @TODO - we should really move this logic to the leadin-admin.js file... ?>
            <script type="text/javascript">
              !function(){var analytics=window.analytics=window.analytics||[];if(!analytics.initialize)if(analytics.invoked)window.console&&console.error&&console.error("Segment snippet included twice.");else{analytics.invoked=!0;analytics.methods=["trackSubmit","trackClick","trackLink","trackForm","pageview","identify","group","track","ready","alias","page","once","off","on"];analytics.factory=function(t){return function(){var e=Array.prototype.slice.call(arguments);e.unshift(t);analytics.push(e);return analytics}};for(var t=0;t<analytics.methods.length;t++){var e=analytics.methods[t];analytics[e]=analytics.factory(e)}analytics.load=function(t){var e=document.createElement("script");e.type="text/javascript";e.async=!0;e.src=("https:"===document.location.protocol?"https://":"http://")+"cdn.segment.com/analytics.js/v1/"+t+"/analytics.min.js";var n=document.getElementsByTagName("script")[0];n.parentNode.insertBefore(e,n)};analytics.SNIPPET_VERSION="3.0.1";
                analytics.load("<?php echo SEGMENT_WRITE_KEY ?>");
                analytics.identify("<?php echo $leadin_user['user_id']; ?>", {
                    "name"              : "<?php echo $leadin_user['alias']; ?>",
                    "email"             : "<?php echo $leadin_user['email']; ?>",
                    "wp-url"            : "<?php echo $leadin_user['wp_url']; ?>",
                    "wp-version"        : "<?php echo $leadin_user['wp_version']; ?>",
                    "li-version"        : "<?php echo $leadin_user['li_version']; ?>",
                    "li-source"         : "<?php echo LEADIN_SOURCE; ?>",
                    "createdAt"         : "<?php echo date('Y-m-d H:i:s'); ?>",
                    "website"           : "<?php echo $leadin_user['wp_url']; ?>",
                    "company"           : "<?php echo $leadin_user['wp_url']; ?>",
                    "contacts"          : <?php echo $leadin_user['total_contacts']; ?>,
                    "utm_source"        : "<?php echo $leadin_user['utm_source']; ?>",
                    "utm_medium"        : "<?php echo $leadin_user['utm_medium']; ?>",
                    "utm_term"          : "<?php echo $leadin_user['utm_term']; ?>",
                    "utm_content"       : "<?php echo $leadin_user['utm_content']; ?>",
                    "utm_campaign"      : "<?php echo $leadin_user['utm_campaign']; ?>",
                    "referral_source"   : "<?php echo $leadin_user['referral_source']; ?>"
                });
                
                jQuery(document).ready( function ( $ ) {
                    <?php  if ( $event_name == 'Loaded Contact List Page' ) : ?>
                        var num_tags = jQuery('.icon-tag').length;
                        analytics.track(<?php echo "'$event_name'"; ?>, {
                            num_tags: num_tags
                        });
                        analytics.page('');
                    <?php else : ?>
                        analytics.track(<?php echo "'$event_name'"; ?>);
                        analytics.page('');
                    <?php endif; ?>
                });
              }}();
            </script>
        <?php endif; ?>

        <?php screen_icon('leadin'); ?>

        <h2 class="<?php echo $css_class ?>"><?php echo $page_title; ?></h2>

        <?php if ( $options['onboarding_complete'] ) : ?>
            <?php if ( $this->has_leads() == FALSE ) : ?>
                <div id="message" class="updated">
                    <p>Leadin is set up and waiting for a form submission... Need help? <a href="http://wordpress.org/support/plugin/leadin">Contact Us</a>.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php $options = get_option('leadin_options'); ?>

        <?php if ( isset($_GET['settings-updated']) && $options['onboarding_complete'] ) : ?>
            <div id="message" class="updated">
                <p><strong><?php _e('Settings saved.') ?></strong></p>
            </div>
        <?php endif; ?>
        <?php if ( isset($options['onboarding_complete']) && $options['onboarding_complete'] && ! isset($_COOKIE['ignore_social_share']) ) : ?>
            <?php if ( leadin_check_first_pageview_data() ) : ?>  
                <div class="dialog-bottom-right">
                    <h1>Hey, do you like Leadin?</h1>
                    <a href="javscript:void(0);" id="close-share" class="close">&#10006;</a>
                    <p>Looks like you've been using Leadin for at least 30 days. Want to help support us by spreading the word?</p>
                    <p>We'd love it if you could tell your friends about us or leave us a review on WordPress.org.</p>
                    <a class="big-button--share fb" href="https://www.facebook.com/sharer/sharer.php?u=http://leadin.com/facebook-share" target="_blank"></a>
                    <a class="big-button--share tw" href="https://twitter.com/home?status=I've%20been%20using%20%40LeadinApp%20to%20track%20visitors%20on%20my%20Wordpress%20site.%20Try%20it%20for%20free!%20http://leadin.com/twitter-share" target="_blank"></a>
                    <a class="big-button--share wp" href="https://wordpress.org/support/view/plugin-reviews/leadin?rate=5#postform" target="_blank"></a>
                </div>
                <script type="text/javascript">
                jQuery(document).ready(function($){
                    var BR = $(".dialog-bottom-right");
                    var close = $(".close");
                        BR.animate({
                            "bottom" : 0
                        }, 
                        1000);
                        close.click(function(){
                        BR.animate({
                            "bottom" : -400
                        }, 
                        1000);
                        });
                });
                  </script>
              <?php endif; ?>
        <?php endif; ?>
        <?php
    }

    function leadin_footer ()
    {
        $li_options = get_option('leadin_options');
        global  $wp_version;
        

        ?>
        <div id="leadin-footer">
            <p class="support">            
                <a href="http://leadin.com">Leadin</a> <?php echo LEADIN_PLUGIN_VERSION; ?>
                <span style="padding: 0px 5px;">|</span><a href="<?php echo 'http://support.leadin.com?leadin_version=' . LEADIN_PLUGIN_VERSION . '&wp_version=' . $wp_version ?>" target="_blank">Support Docs</a> 
                <span style="padding: 0px 5px;">|</span><a href="http://leadin.com/dev-updates/">Get product &amp; security updates</a>
                <span style="padding: 0px 5px;">|</span><a href="http://wordpress.org/support/view/plugin-reviews/leadin?rate=5#postform">Leave us a review</a>
            </p>

            <p class="sharing"><a href="https://twitter.com/leadinapp" class="twitter-follow-button" data-show-count="false">Follow @leadinapp</a><p>
            <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script></p>
        </div>

        <?php
    }

    function build_contacts_chart ( )
    {
        ?>
        <script type="text/javascript">
            
            function create_weekends ( $ )
            {
                var $ = jQuery;

                series = chart.get('contacts');
                var in_between = Math.floor(series.data[1].barX - (Math.floor(series.data[0].barX) + Math.floor(series.data[0].pointWidth)))*2;

                $series = $('.highcharts-series').first();
                $series.find('rect').each ( function ( e ) {
                    var $this = $(this);
                    $this.attr('width', (Math.floor(series.data[0].pointWidth) + Math.floor(in_between/2)));
                    $this.attr('x', $this.attr('x') - Math.floor(in_between/4));
                    $this.css('opacity', 100);
                });
            }

            function hide_weekends ( $ )
            {
                var $ = jQuery;

                series = chart.get('contacts');

                $series = $('.highcharts-series').first();
                $series.find('rect').each ( function ( e ) {
                    var $this = $(this);
                    $this.css('opacity', 0);
                });
            }

            function create_chart ( $ )
            {
                var $ = jQuery;

                $('#contacts_chart').highcharts({
                    chart: {
                        type: 'column',
                        style: {
                            fontFamily: "Open-Sans"
                        }
                    },
                    credits: {
                        enabled: false
                    },
                    title: {
                        text: ''
                    },
                    xAxis: {
                        categories: [ <?php echo $this->stats_dashboard->x_axis_labels; ?> ],
                        tickInterval: 2,
                        tickmarkPlacement: 'on',
                        labels: {
                            style: {
                                color: '#aaa',
                                fontFamily: 'Open Sans'
                            }
                        }
                    },
                    yAxis: {
                        min: 0,
                        title: {
                            text: ''
                        },
                        gridLineColor: '#ddd',
                        labels: {
                            
                            style: {
                                color: '#aaa',
                                fontFamily: 'Open Sans'
                            }
                        },
                        minRange: 4
                    },
                    tooltip: {
                        enabled: true,
                        headerFormat: '<span style="font-size: 11px; font-weight: normal; text-transform: capitalize; font-family: \'Open Sans\'; color: #555;">{point.key}</span><br/>',
                        pointFormat: '<span style="font-size: 11px; font-weight: normal; font-family: \'Open Sans\'; color: #888;">Contacts: {point.y}</span>',
                        valueDecimals: 0,
                        borderColor: '#ccc',
                        borderRadius: 0,
                        shadow: false
                    },
                    plotOptions: {
                        column: {
                            borderWidth: 0, 
                            borderColor: 'rgba(0,0,0,0)',
                            showInLegend: false,
                            colorByPoint: true,
                            states: {
                                brightness: 0
                            }
                        },
                        line: {
                            enableMouseTracking: false,
                            linkedTo: ':previous',
                            dashStyle: 'ShortDash',
                            dataLabels: {
                                enabled: false
                            },
                            marker: {
                                enabled: false
                            },
                            color: '#4CA6CF',
                            tooltip: {
                                enabled: false
                            },
                            showInLegend: false
                        }
                    },
                    colors: [
                        <?php echo $this->stats_dashboard->column_colors; ?>
                    ],
                    series: [{
                        type: 'column',
                        name: 'Contacts',
                        id: 'contacts',
                        data: [ <?php echo $this->stats_dashboard->column_data; ?> ],
                        zIndex: 3,
                        index: 3
                    }, {
                        type: 'line',
                        name: 'Average',
                        animation: false,
                        data: [ <?php echo $this->stats_dashboard->average_data; ?> ],
                        zIndex: 2,
                        index: 2
                    },
                    {
                        type: 'column',
                        name: 'Weekends',
                        animation: false,
                        minPointLength: 500,
                        grouping: false,
                        tooltip: {
                            enabled: true
                        },
                        data: [ <?php echo $this->stats_dashboard->weekend_column_data; ?> ],
                        zIndex: 1,
                        index: 1,
                        id: 'weekends',
                        events: {
                            mouseOut: function ( event ) { event.preventDefault(); },
                            halo: false
                        },
                        states: {
                            hover: {
                                enabled: false
                            }
                        }
                    }]
            });
        }

        var $series;
        var chart;
        var $ = jQuery;

        $(document).ready( function ( e ) {
            
            create_chart();

            chart = $('#contacts_chart').highcharts();
            create_weekends();
        });

        var delay = (function(){
          var timer = 0;
          return function(callback, ms){
            clearTimeout (timer);
            timer = setTimeout(callback, ms);
          };
        })();

        // Takes care of figuring out the weekend widths based on the new column widths
        $(window).resize(function() {
            hide_weekends();
            height = chart.height
            width = $("#contacts_chart").width();
            chart.setSize(width, height);
            delay(function(){
                create_weekends();
            }, 500);
        });
        
        </script>
        <?php
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