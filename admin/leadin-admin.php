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

    /**
     * Class constructor
     */
    function __construct ( $power_ups )
    {
        //=============================================
        // Hooks & Filters
        //=============================================

        $options = get_option('leadin_options');

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

        if ( isset($options['beta_tester']) && $options['beta_tester'] )
            $li_wp_updater = new WPLeadInUpdater();
    }

    function leadin_update_check ( )
    {
        $options = get_option('leadin_options');

        // 0.5.1 upgrade - Create active power-ups option if it doesn't exist
        $leadin_active_power_ups = get_option('leadin_active_power_ups');

        if ( !$leadin_active_power_ups )
        {
            $auto_activate = array(
                'contacts',
                'beta_program'
            );

            update_option('leadin_active_power_ups', serialize($auto_activate));
        }
        else
        {
            // 0.9.2 upgrade - set beta program power-up to auto-activate
            $activated_power_ups = unserialize($leadin_active_power_ups);

            // 0.9.3 bug fix for duplicate beta_program values being stored in the active power-ups array
            if ( !in_array('beta_program', $activated_power_ups) )
            {
                $activated_power_ups[] = 'beta_program';
                update_option('leadin_active_power_ups', serialize($activated_power_ups));
            }
            else 
            {
                $tmp = array_count_values($activated_power_ups);
                $count = $tmp['beta_program'];

                if ( $count > 1 )
                {
                    $activated_power_ups = array_unique($activated_power_ups);
                    update_option('leadin_active_power_ups', serialize($activated_power_ups));
                }
            }

            // 2.0.1 upgrade - [plugin_slug]_list_sync changed to [plugin_slug]_connect
            $mailchimp_list_sync_key = array_search('mailchimp_list_sync', $activated_power_ups);
            if ( $mailchimp_list_sync_key !== FALSE )
            {
                unset($activated_power_ups[$mailchimp_list_sync_key]);
                $activated_power_ups[] = 'mailchimp_connect';
            }

            $constant_contact_list_sync_key = array_search('constant_contact_list_sync', $activated_power_ups);
            if ( $constant_contact_list_sync_key !== FALSE )
            {
                unset($activated_power_ups[$constant_contact_list_sync_key]);
                $activated_power_ups[] = 'constant_contact_connect';
            }

            update_option('leadin_active_power_ups', serialize($activated_power_ups));
        }

        // 0.7.2 bug fix - data recovery algorithm for deleted contacts
        if ( ! isset($options['data_recovered']) )
        {
            leadin_recover_contact_data();
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
        
        self::check_admin_action();

        add_menu_page('Leadin', 'Leadin', 'manage_categories', 'leadin_stats', array($this, 'leadin_build_stats_page'), LEADIN_PATH . '/images/' . ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'leadin-icon-32x32.png' : 'leadin-svg-icon.svg'), '25.100713');

        foreach ( $this->admin_power_ups as $power_up )
        {
            if ( $power_up->activated )
            {
                $power_up->admin_init();

                // Creates the menu icon for power-up if it's set. Overrides the main Leadin menu to hit the contacts power-up
                if ( $power_up->menu_text )
                    add_submenu_page('leadin_stats', $power_up->menu_text, $power_up->menu_text, 'manage_categories', 'leadin_' . $power_up->menu_link, array($power_up, 'power_up_setup_callback'));    
            }
        }

        add_submenu_page('leadin_stats', 'Settings', 'Settings', 'manage_categories', 'leadin_settings', array(&$this, 'leadin_plugin_options'));
        add_submenu_page('leadin_stats', 'Power-ups', 'Power-ups', 'manage_categories', 'leadin_power_ups', array(&$this, 'leadin_power_ups_page'));
        $submenu['leadin_stats'][0][0] = 'Stats';

        if ( !isset($_GET['page']) || $_GET['page'] != 'leadin_settings' )
        {
            $options = get_option('leadin_options');
            if ( !isset($options['ignore_settings_popup']) || !$options['ignore_settings_popup'] )
                $li_pointers = new LI_Pointers();
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

        leadin_track_plugin_activity("Loaded Stats Page");

        if ( !current_user_can( 'manage_categories' ) )
        {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        echo '<div id="leadin" class="li-stats wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
        
        $this->leadin_header('Leadin Stats: ' . date('F j Y, g:ia', current_time('timestamp')), 'leadin-stats__header');

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
                        $new_contacts_postbox .= '<td class="">' . $this->stats_dashboard->print_readable_source($this->stats_dashboard->check_lead_source($contact->lead_source)) . '</td>';
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
                        $returning_contacts_postbox .= '<td class="">' . $this->stats_dashboard->print_readable_source($this->stats_dashboard->check_lead_source($contact->lead_source)) . '</td>';
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
                $sources_postbox .= '<td class="">' . $this->stats_dashboard->direct_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->direct_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Organic Search</td>';
                $sources_postbox .= '<td class="">' . $this->stats_dashboard->organic_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->organic_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Referrals</td>';
                $sources_postbox .= '<td class="">' . $this->stats_dashboard->referral_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->referral_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Social Media</td>';
                $sources_postbox .= '<td class="">' . $this->stats_dashboard->social_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->social_count/$this->stats_dashboard->max_source)*100) : '0' ). '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Email Marketing</td>';
                $sources_postbox .= '<td class="">' . $this->stats_dashboard->email_count . '</td>';
                $sources_postbox .= '<td>';
                    $sources_postbox .= '<div style="background: #f0f0f0; padding: 0px; height: 20px !important;"><div style="background: #f16b18; height: 100%; width: ' . ( $this->stats_dashboard->max_source ? (($this->stats_dashboard->email_count/$this->stats_dashboard->max_source)*100) : '0' ) . '%;">&nbsp;</div></div>';
                $sources_postbox .= '</td>';
            $sources_postbox .= '</tr>';
            $sources_postbox .= '<tr>';
                $sources_postbox .= '<td class="">Paid Search</td>';
                $sources_postbox .= '<td class="">' . $this->stats_dashboard->paid_count . '</td>';
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
       $this->tracking_code_installed_message();     
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
        $leadin_version             = ( isset($options['leadin_version']) ? $options['leadin_version'] : LEADIN_PLUGIN_VERSION );
        $beta_tester                = ( isset($options['beta_tester']) ? $options['beta_tester'] : 0 );
        
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
            '<input id="leadin_version" type="hidden" name="leadin_options[leadin_version]" value="%s"/>',
            $leadin_version
        );

        printf(
            '<input id="beta_tester" type="hidden" name="leadin_options[beta_tester]" value="%d"/>',
            $beta_tester
        );
    }

    function tracking_code_installed_message ( )
    {
        global $wpdb;

        $q = "SELECT COUNT(hashkey) FROM $wpdb->li_leads WHERE lead_deleted = 0 AND hashkey != '' AND lead_email != ''";
        $num_contacts = $wpdb->get_var($q);

        if ( $num_contacts > 0 )
        {
            echo '<div class="leadin-section">';
                echo '<p style="color: #090; font-weight: bold;">Visitor tracking is installed and tracking visitors.</p>';
                echo '<p>The next time a visitor fills out a form on your WordPress site with an email address, Leadin will send you an email with the contact\'s referral source and page view history.</p>';
            echo '</div>';
        }
        else
        {
            echo '<div class="leadin-section">';
                echo '<p style="color: #f67d42; font-weight: bold;">Leadin is setup and waiting for a form submission...</p>';
                echo '<p>Can\'t wait to see Leadin in action? Go fill out a form on your site to see your first contact.</p>';
            echo '</div>';
        }
    }

    function update_option_leadin_options_callback ( $old_value, $new_value )
    {
        $user_email = $new_value["li_email"];

        if ( isset( $_POST['li_updates_subscription'] ) && $_POST['li_updates_subscription'] )
            leadin_subscribe_user_updates();

        leadin_register_user();
    }

    /**
     * Creates settings page
     */
    function leadin_plugin_options ()
    {
        if ( !current_user_can( 'manage_categories' ) ) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

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

        leadin_track_plugin_activity("Loaded Onboarding Page");
        $li_options = get_option('leadin_options');
        
        echo '<div id="leadin" class="li-onboarding wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
    
        $this->leadin_header('Leadin Setup');
        
        ?>
        
        <div class="oboarding-steps">

        <?php if ( ! isset($_GET['activate_popup']) ) : ?>
            
            <?php if ( $li_options['onboarding_step'] == 1 ) : ?>

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
                                <br>
                                <label for="li_updates_subscription"><input type="checkbox" id="li_updates_subscription" name="li_updates_subscription" checked/>Keep me up to date with security and feature updates</label>
                            </div>
                            <?php $this->print_hidden_settings_fields();  ?>
                            <input type="hidden" id="next_onboarding_step" name="next_onboarding_step" value="2">
                            <input type="submit" name="submit" id="submit" class="button button-primary button-big" value="<?php esc_attr_e('Save Email'); ?>">
                        </form>
                    </div>
                </div>

            <?php elseif ( $li_options['onboarding_step'] == 2 ) : ?>

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
                }

                // Update the onboarding settings
                if ( ! isset($options['onboarding_complete']) || ! $options['onboarding_complete'] )
                    leadin_update_option('leadin_options', 'onboarding_complete', 1);
            ?>

            <ol class="oboarding-steps-names">
                <li class="oboarding-step-name completed">Activate Leadin</li>
                <li class="oboarding-step-name completed">Get Contact Reports</li>
                <li class="oboarding-step-name completed">Grow Your Contacts List</li>
            </ol>
            <div class="oboarding-step">
                <h2 class="oboarding-step-title">Setup Complete!<br>Leadin is waiting for your first form submission.</h2>
                <div class="oboarding-step-content">
                    <p class="oboarding-step-description">Leadin is setup and waiting for a form submission. Once Leadin detects a from submission, a new contact will be added to your contacts list. We reccommend filling out a form on your site to test that Leadin is working correctly.</p>
                    <form id="li-onboarding-form" method="post" action="options.php">
                        <?php $this->print_hidden_settings_fields();  ?>
                        <a href="<?php echo get_admin_url() . 'admin.php?page=leadin_settings'; ?>" class="button button-primary button-big"><?php esc_attr_e('Complete Setup'); ?></a>
                    </form>
                </div>
            </div>

        <?php endif; ?>
        
        </div>

        <div class="oboarding-steps-help">
            <h4>Any questions?</h4>
            <p>Send us a message and we’re happy to help you get set up.</p>
            <a class="button" href="#" onclick="return SnapEngage.startLink();">Chat with us</a>
        </div>

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
        
        leadin_track_plugin_activity("Loaded Settings Page");

        echo '<div id="leadin" class="li-settings wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
        
        $this->leadin_header('Leadin Settings');
        
        ?>
            <form method="post" action="options.php">
                <?php 
                    settings_fields('leadin_settings_options');
                    do_settings_sections(LEADIN_ADMIN_PATH);
                    submit_button('Save Settings');
                ?>
            </form>
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

        if( isset( $input['li_email'] ) )
            $new_input['li_email'] = sanitize_text_field( $input['li_email'] );

        if( isset( $input['li_installed'] ) )
            $new_input['li_installed'] = $input['li_installed'];

        if( isset( $input['li_db_version'] ) )
            $new_input['li_db_version'] = $input['li_db_version'];

        if( isset( $input['onboarding_step'] ) )
            $new_input['onboarding_step'] = ( $input['onboarding_step'] + 1 );

        if( isset( $input['onboarding_complete'] ) )
            $new_input['onboarding_complete'] = $input['onboarding_complete'];

        if( isset( $input['ignore_settings_popup'] ) )
            $new_input['ignore_settings_popup'] = $input['ignore_settings_popup'];

        if( isset( $input['data_recovered'] ) )
            $new_input['data_recovered'] = $input['data_recovered'];

        if( isset( $input['converted_to_tags'] ) )
            $new_input['converted_to_tags'] = $input['converted_to_tags'];

        if( isset( $input['delete_flags_fixed'] ) )
            $new_input['delete_flags_fixed'] = $input['delete_flags_fixed'];

        if( isset( $input['leadin_version'] ) )
            $new_input['leadin_version'] = $input['leadin_version'];

        if( isset( $input['li_updates_subscription'] ) )
            $new_input['li_updates_subscription'] = $input['li_updates_subscription'];

        if( isset( $input['beta_tester'] ) )
        {
            $new_input['beta_tester'] = sanitize_text_field($input['beta_tester']);
            leadin_set_beta_tester_property(TRUE);
        }
        else
            leadin_set_beta_tester_property(FALSE);

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
     * Creates power-up page
     */
    function leadin_power_ups_page ()
    {
        global  $wp_version;
        
        leadin_track_plugin_activity("Loaded Power-ups Page");

        if ( !current_user_can( 'manage_categories' ) )
        {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        echo '<div id="leadin" class="li-settings wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
        
        $this->leadin_header('Leadin Power-ups');
        
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

                    <?php if ( $power_up_count == 1 ) : ?>
                        <!-- static content stats power-up - not a real powerup and this is a hack to put it second in the order -->
                        <li class="powerup activated">
                            <div class="img-containter">
                                <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-analytics@2x.png" height="80px" width="80px">
                            </div>
                            <h2>Content Stats</h2>
                            <p>See where all your conversions are coming from.</p>
                            <p><a href="http://leadin.com/content-analytics-plugin-wordpress/" target="_blank">Learn more</a></p>
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
                        <p><a href="<?php echo $power_up->link_uri; ?>" target="_blank">Learn more</a></p>
                        <?php if ( $power_up->activated ) : ?>
                            <?php if ( ! $power_up->permanent ) : ?>
                                <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_power_ups&leadin_action=deactivate&power_up=' . $power_up->slug; ?>" class="button button-secondary button-large">Deactivate</a>
                            <?php endif; ?>
                        <?php else : ?>
                            <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_power_ups&leadin_action=activate&power_up=' . $power_up->slug; ?>" class="button button-primary button-large">Activate</a>
                        <?php endif; ?>

                        <?php if ( $power_up->activated || $power_up->permanent ) : ?>
                            <?php if ( $power_up->menu_link == 'contacts' ) : ?>
                                <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_' . $power_up->menu_link; ?>" class="button button-secondary button-large">View Contacts</a>
                                <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_settings'; ?>" class="button button-secondary button-large">Configure</a>
                            <?php else : ?>
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
                    <p>&nbsp;</p>
                    <a href="mailto:support@leadin.com" target="_blank" class="button button-primary button-large">Suggest an idea</a>
                </li>

                <li class="powerup">
                    <div class="img-containter">
                        <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-vip@2x.png" height="80px" width="80px">
                    </div>
                    <h2>Leadin VIP Program</h2>
                    <p>Get access to exclusive features and offers for consultants and agencies.</p>
                    <p><a href="http://leadin.com/vip/" target="_blank">Learn more</a></p>
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
    function leadin_header ( $page_title = '', $css_class = '' )
    {
        ?>
        <?php screen_icon('leadin'); ?>
        <h2 class="<?php echo $css_class ?>"><?php echo $page_title; ?></h2>

        <?php $options = get_option('leadin_options'); ?>

        <?php if ( isset($_GET['settings-updated']) && $options['onboarding_complete'] ) : ?>
            <div id="message" class="updated">
                <p><strong><?php _e('Settings saved.') ?></strong></p>
            </div>
        <?php endif;
    }

    function leadin_footer ()
    {
        ?>
        <div id="leadin-footer">
            <p class="support">
                <a href="http://leadin.com">Leadin</a> <?php echo LEADIN_PLUGIN_VERSION?> 
                <span style="padding: 0px 5px;">|</span> Need help? <a href="#" onclick="return SnapEngage.startLink();">Contact us</a>
                <span style="padding: 0px 5px;">|</span> Stay up to date with <a href="http://leadin.com/dev-updates/">user updates</a>
                <span style="padding: 0px 5px;">|</span> Love Leadin? <a href="http://wordpress.org/support/view/plugin-reviews/leadin?rate=5#postform">Review us</a>
            </p>
            <p class="sharing"><a href="https://twitter.com/leadinapp" class="twitter-follow-button" data-show-count="false">Follow @leadinapp</a>

            <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script></p>
        </div>
        <!-- begin SnapEngage code -->
        <script type="text/javascript">
          (function() {
            var se = document.createElement('script'); se.type = 'text/javascript'; se.async = true;
            se.src = '//commondatastorage.googleapis.com/code.snapengage.com/js/b7667cce-a26d-4440-a716-7c4b9f086705.js';
            var done = false;
            se.onload = se.onreadystatechange = function() {
              if (!done&&(!this.readyState||this.readyState==='loaded'||this.readyState==='complete')) {
                done = true;
                // Place your SnapEngage JS API code below
                // SnapEngage.allowChatSound(true); // Example JS API: Enable sounds for Visitors. 
              }
            };
            var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(se, s);
          })();
        </script>
        <!-- end SnapEngage code -->
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
}

?>