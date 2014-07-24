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

        $this->admin_power_ups = $power_ups;

        if( is_admin() )
        {
            add_action('admin_menu', array(&$this, 'leadin_add_menu_items'));
            add_action('admin_init', array(&$this, 'leadin_build_settings_page'));
            add_action('admin_print_styles', array(&$this, 'add_leadin_admin_styles'));
            add_action('add_meta_boxes', array(&$this, 'add_li_analytics_meta_box' ));

            if ( isset($_GET['page']) && $_GET['page'] == 'leadin_stats' )
            {
                add_action('admin_footer', array($this, 'build_contacts_chart'));
            }
        }
    }
    
    //=============================================
    // Menus
    //=============================================

    /**
     * Adds LeadIn menu to /wp-admin sidebar
     */
    function leadin_add_menu_items ()
    {
        global $submenu;
        global  $wp_version;
        
        self::check_admin_action();

        add_menu_page('LeadIn', 'LeadIn', 'manage_categories', 'leadin_stats', array($this, 'leadin_build_stats_page'), LEADIN_PATH . '/images/' . ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'leadin-icon-32x32.png' : 'leadin-svg-icon.svg'));

        foreach ( $this->admin_power_ups as $power_up )
        {
            if ( $power_up->activated )
            {
                $power_up->admin_init();

                // Creates the menu icon for power-up if it's set. Overrides the main LeadIn menu to hit the contacts power-up
                //if ( $power_up->menu_text == 'Contacts' )
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
     * Adds setting link for LeadIn to plugins management page 
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
        
        $this->leadin_header('LeadIn Stats: ' . date('F j Y, g:ia', current_time('timestamp')), 'leadin-stats__header');

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
                            $new_contacts_postbox .= '<a href="?page=leadin_contacts&action=view&lead=' . $contact->lead_id . '&stats_dashboard=1"><img class="lazy pull-left leadin-contact-avatar leadin-dynamic-avatar_' . substr($contact->lead_id, -1) .'" src="https://app.getsignals.com/avatar/image/?emails=' . $contact->lead_email . '" width="35" height="35"><b>' . $contact->lead_email . '</b></a>';
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
                            $returning_contacts_postbox .= '<a href="?page=leadin_contacts&action=view&lead=' . $contact->lead_id . '&stats_dashboard=1"><img class="lazy pull-left leadin-contact-avatar leadin-dynamic-avatar_' . substr($contact->lead_id, -1) .'" src="https://app.getsignals.com/avatar/image/?emails=' . $contact->lead_email . '" width="35" height="35"><b>' . $contact->lead_email . '</b></a>';
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

        // Show the settings popup on all pages except the settings page
        if( isset($_GET['settings-updated']) )
        {
            $options = get_option('leadin_options');

            if ( !isset($options['onboarding_complete']) || !$options['onboarding_complete'] )
                leadin_update_option('leadin_options', 'onboarding_complete', 1);

            if ( !isset($options['ignore_settings_popup']) || !$options['ignore_settings_popup'] )
                leadin_update_option('leadin_options', 'ignore_settings_popup', 1);
        }
        
        register_setting('leadin_settings_options', 'leadin_options', array($this, 'sanitize'));

        $visitor_tracking_icon = $leadin_contacts->icon_small;
        add_settings_section('leadin_settings_section', $visitor_tracking_icon . 'Visitor Tracking', array($this, 'leadin_options_section_heading'), LEADIN_ADMIN_PATH);
        add_settings_field('li_email', 'Email', array($this, 'li_email_callback'), LEADIN_ADMIN_PATH, 'leadin_settings_section');
        
        add_filter( 'update_option_leadin_options', array($this, 'update_option_leadin_options_callback'), 10, 2 );
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
        $li_installed = ( $options['li_installed'] ? $options['li_installed'] : 1 );
        $li_db_version = ( $options['li_db_version'] ? $options['li_db_version'] : LEADIN_DB_VERSION );
        $ignore_settings_popup = ( $options['ignore_settings_popup'] ? $options['ignore_settings_popup'] : 0 );
        $onboarding_complete = ( $options['onboarding_complete'] ? $options['onboarding_complete'] : 0 );
        $data_recovered = ( $options['data_recovered'] ? $options['data_recovered'] : 0 );
        $delete_flags_fixed = ( $options['delete_flags_fixed'] ? $options['delete_flags_fixed'] : 0 );

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
    }

    function tracking_code_installed_message ( )
    {
        echo '<div class="leadin-section">';
            echo '<p style="color: #090; font-weight: bold;">Visitor tracking is installed and tracking visitors.</p>';
            echo '<p>The next time a visitor fills out a form on your WordPress site with an email address, LeadIn will send you an email with the contact\'s referral source and page view history.</p>';
        echo '</div>';
    }

    function update_option_leadin_options_callback ( $old_value, $new_value )
    {
        $user_email = $new_value["li_email"];
        leadin_register_user();
    }

    /**
     * Creates settings page
     */
    function leadin_plugin_options ()
    {
        global  $wp_version;

        if ( !current_user_can( 'manage_categories' ) )
        {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Update the settings popup flag when the settings page is visited for the first time
        $li_options = get_option('leadin_options');

        echo '<div id="leadin" class="li-settings wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'pre-mp6' : ''). '">';
        
        $this->leadin_header('LeadIn Settings');
        
        if ( !$li_options['onboarding_complete'] && !isset($_GET['settings-updated']) )
            $this->leadin_plugin_onboarding();
        else
            $this->leadin_plugin_settings();

        $this->leadin_footer();
        
        //end wrap
        echo '</div>';

    }

    /**
     * Creates onboarding settings page
     */
    function leadin_plugin_onboarding ()
    {
        leadin_track_plugin_activity("Loaded Onboarding Page");

        ?>
        
            <div class="steps">
                <ol class="step-names">
                    <li class="step-name completed">Downloaded</li>
                    <li class="step-name completed">Activated</li>
                    <li class="step-name active">Confirm Email</li>
                </ol>
                <ul class="step-content">
                    <li class="step active">
                        <h2>Confirm your email</h2>
                        <form method="post" action="options.php">
                            <?php settings_fields('leadin_settings_options'); ?>
                            <p>
                                <?php $this->li_email_callback(); ?>
                            </p>

                            <input type="hidden" name="onboarding-complete" value="true">
                            <?php $this->print_hidden_settings_fields();  ?>

                            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings'); ?>">
                        </form>
                    </li>
                </ul>
            </div>

        <?php
    }

    /**
     * Creates default settings page
     */
    function leadin_plugin_settings ()
    {
        leadin_track_plugin_activity("Loaded Settings Page");
        
        ?>
            <form method="post" action="options.php">
                <?php 
                    settings_fields('leadin_settings_options');
                    do_settings_sections(LEADIN_ADMIN_PATH);
                    submit_button('Save Settings');
                ?>
            </form>
        <?php
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

        if( isset( $input['onboarding_complete'] ) )
            $new_input['onboarding_complete'] = $input['onboarding_complete'];

        if( isset( $input['ignore_settings_popup'] ) )
            $new_input['ignore_settings_popup'] = $input['ignore_settings_popup'];

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
        
        $this->leadin_header('LeadIn Power-ups');
        
        ?>

            <p>Get the most out of your LeadIn install with these powerful marketing powerups.</p>
            
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
                            <h2>Content Stats</h2>
                            <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-analytics@2x.png" height="80px" width="80px">
                            <p>See where all your conversions are coming from.</p>
                            <p><a href="http://leadin.com/content-analytics-plugin-wordpress/" target="_blank">Learn more</a></p>
                            <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_stats'; ?>" class="button button-large">View Stats</a>
                        </li>
                        <?php $power_up_count++; ?>
                    <?php endif; ?>

                    <li class="powerup <?php echo ( $power_up->activated ? 'activated' : ''); ?>">
                        <h2><?php echo $power_up->power_up_name; ?></h2>
                        <?php if ( strstr($power_up->icon, 'dashicons') ) : ?>
                            <div class="power-up-icon dashicons <?php echo $power_up->icon; ?>"></div>
                        <?php else : ?>
                            <img src="<?php echo LEADIN_PATH . '/images/' . $power_up->icon . '@2x.png'; ?>" height="80px" width="80px"/>
                        <?php endif; ?>
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
                    <h2>Your Idea</h2>
                    <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-ideas@2x.png" height="80px" width="80px">
                    <p>Have an idea for a power-up? We'd love to hear it!</p>
                    <p>&nbsp;</p>
                    <a href="mailto:team@leadin.com" target="_blank" class="button button-primary button-large">Suggest an idea</a>
                </li>

                <li class="powerup">
                    <h2>LeadIn VIP Program</h2>
                    <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-vip@2x.png" height="80px" width="80px">
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

        <?php if ( isset($_GET['settings-updated']) ) : ?>
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
                <a href="http://leadin.com">LeadIn</a> <?php echo LEADIN_PLUGIN_VERSION?> 
                <span style="padding: 0px 5px;">|</span> Need help? <a href="#" onclick="return SnapEngage.startLink();">Contact us</a>
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

    /**
     * Adds the analytics meta box in the post editor
     */
    function add_li_analytics_meta_box ()
    {
        global $post;
        if ( ! in_array(get_post_status($post->ID), array('publish', 'private')) )
            return false;

        $post_types = get_post_types( array( 'public' => true ) );

        $permalink = get_permalink($post->ID);
        $this->li_viewers = new LI_Viewers();
        $this->li_viewers->get_identified_viewers($permalink);
        $this->li_viewers->get_submissions($permalink);

        if ( is_array( $post_types ) && $post_types !== array() ) {
            foreach ( $post_types as $post_type ) {
                add_meta_box( 'li_analytics-meta', 'LeadIn Analytics', array( $this, 'li_analytics_meta_box' ), $post_type, 'normal', 'high');
            }
        }
    }

    /**
     * Output the LeadIn Analytics meta box
     */
    function li_analytics_meta_box () 
    {
        global $post;
        $view_count         = 0;
        $submission_count   = 0;
        $max_faces          = 10;
        ?>
            <table class="form-table"><tbody>
                <tr>
                    <th scope="row">
                        <?php echo count($this->li_viewers->viewers) . ' ' . ( count($this->li_viewers->viewers) != 1 ? 'identified viewers:' : 'identified viewer:' ); ?>
                    </th>
                    <td>
                        <?php
                            if ( count($this->li_viewers->viewers) )
                            {
                                foreach ( $this->li_viewers->viewers as $viewer )
                                {
                                    $view_count++;
                                    $contact_view_url = get_bloginfo('wpurl') . "/wp-admin/admin.php?page=leadin_contacts&action=view&lead=" . $viewer->lead_id . '&post_id=' . $post->ID;
                                    echo '<a class="li-analytics-link ' . ( $view_count > $max_faces ? 'hidden_face' : '' ) . '" href="' . $contact_view_url . '" title="' . $viewer->lead_email . '"><img height="35px" width="35px" data-original="https://app.getsignals.com/avatar/image/?emails=' . $viewer->lead_email . '" class="lazy li-analytics__face leadin-dynamic-avatar_' . substr($viewer->lead_id, -1) . '"/></a>'; 
                                }
                            }

                            if ( $view_count > $max_faces )
                            {
                                echo '<div class="show-all-faces-container"><a class="show_all_faces" href="javascript:void(0)">+ Show ' . ( $view_count - $max_faces ) . ' more</a></div>';
                            }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo count($this->li_viewers->submissions) . ' ' . ( count($this->li_viewers->submissions) != 1 ? 'form submissions:' : 'form submission:' ); ?>
                    </th>
                    <td>
                        <?php 
                            foreach ( $this->li_viewers->submissions as $submission )
                            {
                                $submission_count++;
                                $contact_view_url = get_bloginfo('wpurl') . "/wp-admin/admin.php?page=leadin_contacts&action=view&lead=" . $submission->lead_id . '&post_id=' . $post->ID;
                                echo '<a class="li-analytics-link ' . ( $submission_count > $max_faces ? 'hidden_face' : '' ) . '" href="' . $contact_view_url . '" title="' . $submission->lead_email . '"><img height="35px" width="35px" data-original="https://app.getsignals.com/avatar/image/?emails=' . $submission->lead_email . '" class="lazy li-analytics__face leadin-dynamic-avatar_' . substr($submission->lead_id, -1) . '"/></a>';
                            }

                            if ( $submission_count > $max_faces )
                            {
                                echo '<div class="show-all-faces-container"><a class="show_all_faces" href="javascript:void(0)">+ Show ' . ( $submission_count - $max_faces ) . ' more</a></div>';
                            }
                        ?>
                    </td>
                </tr>
            </tbody></table>
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