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

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

//=============================================
// WPLeadInAdmin Class
//=============================================
class WPLeadInAdmin {
    
    var $admin_power_ups;

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

        foreach ( $this->admin_power_ups as $power_up )
        {
            if ( $power_up->activated )
            {
                $power_up->admin_init();

                if ( $power_up->menu_text == 'Contacts' )
                    add_menu_page('LeadIn', 'LeadIn', 'manage_categories', 'leadin_contacts', array($power_up, 'power_up_setup_callback'), LEADIN_PATH . '/images/' . ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php') ? 'leadin-icon-32x32.png' : 'leadin-svg-icon.svg'));
                else if ( $power_up->menu_text )
                    add_submenu_page('leadin_contacts', $power_up->menu_text, $power_up->menu_text, 'manage_categories', 'leadin_' . $power_up->menu_link, array($power_up, 'power_up_setup_callback'));    
            }
        }

        add_submenu_page('leadin_contacts', 'Settings', 'Settings', 'manage_categories', 'leadin_settings', array(&$this, 'leadin_plugin_options'));
        add_submenu_page('leadin_contacts', 'Power-ups', 'Power-ups', 'manage_categories', 'leadin_power_ups', array(&$this, 'leadin_power_ups_page'));
        $submenu['leadin_contacts'][0][0] = 'Contacts';

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
     * Creates settings options
     */
    function leadin_build_settings_page ()
    {
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
        add_settings_section('leadin_settings_section', 'Visitor Tracking', array($this, 'leadin_options_section_heading'), LEADIN_ADMIN_PATH);
        add_settings_field('li_email', 'Email', array($this, 'li_email_callback'), LEADIN_ADMIN_PATH, 'leadin_settings_section');
        
        add_filter( 'update_option_leadin_options', array($this, 'update_option_leadin_options_callback'), 10, 2 );
    }

    function leadin_options_section_heading ( )
    {
        ?>
        <p style='color: #090; font-weight: bold;'>Visitor tracking is installed and tracking visitors.</p>
        <p>The next time a visitor fills out a form on your WordPress site with an email address, LeadIn will send you an email with the contact's referral source and page view history.</p>
        <?php

       $this->print_hidden_settings_fields();        
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

                <?php foreach ( $this->admin_power_ups as $power_up ) : ?>
                    <li class="powerup <?php echo ( $power_up->activated ? 'activated' : ''); ?>">
                        <h2><?php echo $power_up->power_up_name; ?></h2>
                        <img src="<?php echo LEADIN_PATH . '/images/' . $power_up->icon . '@2x.png'; ?>" height="80px" width="80px"/>
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
                <?php endforeach; ?>

                <li class="powerup">
                    <h2>Content Analytics</h2>
                    <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-analytics@2x.png" height="80px" width="80px">
                    <p>See where all your conversions are coming from.</p>
                    <p><a href="http://leadin.com/content-analytics-plugin-wordpress/">Learn more</a></p>
                    <a disabled="true" class="button button-primary button-large">Coming soon</a>
                </li>
                <li class="powerup">
                    <h2>Your Idea</h2>
                    <img src="<?php echo LEADIN_PATH; ?>/images/powerup-icon-ideas@2x.png" height="80px" width="80px">
                    <p>Have an idea for a power-up? We'd love to hear it!</p>
                    <p>&nbsp;</p>
                    <a href="mailto:team@leadin.com" target="_blank" class="button button-primary button-large">Suggest an idea</a>
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
        wp_register_style('leadin-admin-css', LEADIN_PATH . '/admin/css/leadin-admin.css');
        wp_enqueue_style('leadin-admin-css');
    }

    //=============================================
    // Internal Class Functions
    //=============================================

    /**
     * Creates postbox for admin
     * @param string
     * @param string
     * @param string
     * @param bool
     * @return string   HTML for postbox
     */
    function leadin_postbox ( $id, $title, $content, $handle = TRUE )
    {
        $postbox_wrap = "";
        $postbox_wrap .= '<div id="' . $id . '" class="postbox leadin-admin-postbox">';
        $postbox_wrap .= ( $handle ? '<div class="handlediv" title="Click to toggle"><br /></div>' : '' );
        $postbox_wrap .= '<h3><span>' . $title . '</span></h3>';
        $postbox_wrap .= '<div class="inside">' . $content . '</div>';
        $postbox_wrap .= '</div>';
        return $postbox_wrap;
    }

    /**
     * Prints the admin page title, icon and help notification
     * @param string
     */
    function leadin_header ( $page_title = '' )
    {
        ?>
        <?php screen_icon('leadin'); ?>
        <h2><?php echo $page_title; ?></h2>

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
}

?>