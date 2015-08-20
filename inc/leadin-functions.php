<?php

if ( !defined('LEADIN_PLUGIN_VERSION') ) 
{
    header('HTTP/1.0 403 Forbidden');
    die;
}

if ( !defined('LEADIN_MIGRATION_OPTION_NAME') )
{
    DEFINE('LEADIN_MIGRATION_OPTION_NAME', 'leadin_migrationStatus');
}

if ( !defined('LEADIN_PORTAL_ID') )
{
    DEFINE('LEADIN_PORTAL_ID', intval(get_option('leadin_portalId')));
}

if ( !defined('LEADIN_HAPIKEY') )
{
    DEFINE('LEADIN_HAPIKEY', get_option('leadin_hapikey'));
}


function leadin_get_resource_url( $path )
{
    $resource_root = constant('LEADIN_ADMIN_ASSETS_BASE_URL');

    return $resource_root.$path;
}

function leadin_build_api_url_with_auth( $path )
{
    $auth_string = '?portalId=' . LEADIN_PORTAL_ID . '&hapikey=' . LEADIN_HAPIKEY;
    return LEADIN_API_BASE_URL.$path.$auth_string;
}

/**
 * Updates an option in the multi-dimensional option array
 *
 * @param   string   $option        option_name in wp_options
 * @param   string   $option_key    key for array
 * @param   string   $option        new value for array
 *
 * @return  bool            True if option value has changed, false if not or if update failed.
 */
function leadin_update_option ( $option, $option_key, $new_value ) 
{
    $options_array = get_option($option);

    if ( isset($options_array[$option_key]) )
    {
        if ( $options_array[$option_key] == $new_value )
            return false; // Don't update an option if it already is set to the value
    }

    if ( !is_array( $options_array ) ) {
        $options_array = array();
    }

    $options_array[$option_key] = $new_value;
    update_option($option, $options_array);

    $options_array = get_option($option);
    return update_option($option, $options_array);
}

/**
 * Get Leadin user
 *
 * @return  array
 */
function leadin_get_current_user ()
{
    global $wp_version;
    global $current_user;

    get_currentuserinfo();
    $li_user_id = md5(get_bloginfo('wpurl'));

    $li_options = get_option('leadin_options');
    $leadinPortalId = get_option('leadin_portalId');
    
    if ( isset($li_options['li_email']) ) {
        $li_user_email = $li_options['li_email'];
    } 
    else {
        $li_user_email = $current_user->user_email;
    }

    $leadin_user = array(
        'user_id' => $li_user_id,
        'email' => $li_user_email,
        'alias' => $current_user->display_name,
        'wp_url' => get_bloginfo('wpurl'),
        'li_version' => LEADIN_PLUGIN_VERSION,
        'wp_version' => $wp_version,
        'user_email' => $current_user->user_email
    );

    if ( defined('LEADIN_REFERRAL_SOURCE') )
        $leadin_user['referral_source'] = LEADIN_REFERRAL_SOURCE;
    else
        $leadin_user['referral_source'] = '';

    if ( defined('LEADIN_UTM_SOURCE') )
        $leadin_user['utm_source'] = LEADIN_UTM_SOURCE;
    else
        $leadin_user['utm_source'] = '';

    if ( defined('LEADIN_UTM_MEDIUM') )
        $leadin_user['utm_medium'] = LEADIN_UTM_MEDIUM;
    else
        $leadin_user['utm_medium'] = '';

    if ( defined('LEADIN_UTM_TERM') )
        $leadin_user['utm_term'] = LEADIN_UTM_TERM;
    else
        $leadin_user['utm_term'] = '';

    if ( defined('LEADIN_UTM_CONTENT') )
        $leadin_user['utm_content'] = LEADIN_UTM_CONTENT;
    else
        $leadin_user['utm_content'] = '';

    if ( defined('LEADIN_UTM_CAMPAIGN') )
        $leadin_user['utm_campaign'] = LEADIN_UTM_CAMPAIGN;
    else
        $leadin_user['utm_campaign'] = '';

    if ( !empty($leadinPortalId) ) {
        $leadin_user['portal_id'] = $leadinPortalId;
    }

    return $leadin_user;
}

/**
 * Logs a debug statement to /wp-content/debug.log
 *
 * @param   string
 */
function leadin_log_debug ( $message )
{
    if ( WP_DEBUG === TRUE )
    {
        if ( is_array($message) || is_object($message) )
            error_log(print_r($message, TRUE));
        else 
            error_log($message);
    }
}

/**
 * Calculates the hour difference between MySQL timestamps and the current local WordPress time
 * 
 */
function leadin_set_mysql_timezone_offset ()
{
    global $wpdb;

    $mysql_timestamp = $wpdb->get_var("SELECT CURRENT_TIMESTAMP");
    $diff = strtotime($mysql_timestamp) - strtotime(current_time('mysql'));
    $hours = $diff / (60 * 60);

    $wpdb->db_hour_offset = $hours;
}

/**
 * Returns the user role for the current user
 * 
 */
function leadin_get_user_role ()
{
    global $current_user;

    $user_roles = $current_user->roles;
    $user_role = array_shift($user_roles);

    return $user_role;
}

function leadin_check_tables_exist ()
{
    global $wpdb;
    $check_tables_exist_query = "SELECT table_name FROM information_schema.tables WHERE table_name = '" . li_leads . "' OR table_name = '" . li_submissions . "' OR table_name = '" . li_pageviews . "' OR table_name = '" . $wpdb->li_tags . "' OR table_name = '" . $wpdb->li_tag_relationships . "'";
    $li_tables = $wpdb->get_results($check_tables_exist_query);
    return $li_tables;
}

function leadin_get_contacts_for_migration ()
{
    global $wpdb;

    $wpdb->li_submissions      = ( is_multisite() ? $wpdb->prefix . 'li_submissions'    : 'li_submissions' );
    $wpdb->li_pageviews        = ( is_multisite() ? $wpdb->prefix . 'li_pageviews'      : 'li_pageviews' );
    $wpdb->li_leads            = ( is_multisite() ? $wpdb->prefix . 'li_leads'          : 'li_leads' );

    if ( ! isset($wpdb->li_leads) )
        return 0;

    $get_contacts_for_migration_query = "
        SELECT 
            DISTINCT hashkey AS hashkey,
            lead_email
        FROM 
            $wpdb->li_leads
        WHERE
            lead_migrated = 0 AND 
            lead_email != '' AND 
            lead_deleted = 0 AND
            hashkey != '' 
            ORDER BY lead_email";

    $contacts = $wpdb->get_results($get_contacts_for_migration_query);

    if ( count($contacts) )
    {
        return $contacts;
    }
    else
    {
        $q = "
            SELECT 
                DISTINCT hashkey AS hashkey
            FROM 
                $wpdb->li_leads
            WHERE
                lead_migrated = 1 AND 
                lead_email != '' AND 
                lead_deleted = 0 
                AND hashkey != ''";

        $migrated_contacts = $wpdb->get_results($q);

        if ( count($migrated_contacts) )
        {
            return 'migration complete';
        }
        else
        {
            return 'no contacts';
        }
    }

    return FALSE;
}

function leadin_echo_contacts_for_migration ()
{
    $results = leadin_get_contacts_for_migration();

    if ( $results == 'migration complete' || $results == 'no contacts' || ! $results )
        echo $results;
    else
        echo json_encode($results);

    die();
}

add_action('wp_ajax_leadin_echo_contacts_for_migration', 'leadin_echo_contacts_for_migration'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_echo_contacts_for_migration', 'leadin_echo_contacts_for_migration'); // Call when user is not logged in

function leadin_migrate_contact ()
{
    global $wpdb;
    
    $wpdb->li_submissions      = ( is_multisite() ? $wpdb->prefix . 'li_submissions' : 'li_submissions' );
    $wpdb->li_leads            = ( is_multisite() ? $wpdb->prefix . 'li_leads' : 'li_leads' );
    $wpdb->li_pageviews        = ( is_multisite() ? $wpdb->prefix . 'li_pageviews' : 'li_pageviews' );

    $hashkey = $_POST['hashkey'];

    // debug code
    $debug_query = $wpdb->prepare("
        SELECT
            lead_email
        FROM 
            $wpdb->li_leads
        WHERE 
            hashkey = %s
        LIMIT
            1", $hashkey);

    $contact = $wpdb->get_row($debug_query);

    $utk = md5($hashkey);

    // using this variable to convert the mysql datetime to GMT
    $gmt_offset = $wpdb->get_var("SELECT TIMESTAMPDIFF(HOUR, NOW(), UTC_TIMESTAMP())");

    // Get the contact page views
    $analytics_query = $wpdb->prepare("
        SELECT 
            pageview_id,
            pageview_date, 
            DATE_FORMAT(DATE_ADD(pageview_date, INTERVAL %d HOUR), %s) AS pageview_date_formatted, 
            lead_hashkey, pageview_title, pageview_url, pageview_source, pageview_session_start 
        FROM 
            $wpdb->li_pageviews 
        WHERE 
            pageview_migrated = 0 AND 
            pageview_deleted = 0 AND
            lead_hashkey = %s",  $gmt_offset, '%Y-%m-%d %k:%i:%s', $hashkey);
    
    $pageviews = $wpdb->get_results($analytics_query);

    // let's batch analytics events
    if ( count($pageviews) )
    {
        $batchedPageviews = array();
        foreach ($pageviews as $pageview) {
            $timestamp = strtotime($pageview->pageview_date_formatted) * 1000;
            
            $this_query_string =  'k=1' // Activity Type
                                . '&w=' . number_format($timestamp, 0, '', '')
                                . '&a=' . LEADIN_PORTAL_ID
                                . '&vi=' . $utk
                                . '&t=' . urlencode($pageview->pageview_title)
                                . '&r=' . urlencode($pageview->pageview_source); // Referer to the page
            $thisPageView = array(
                'query'         => $this_query_string,
                'pageUrl'      => $pageview->pageview_url
            );
            $batchedPageviews[] = $thisPageView;
        }

        leadin_migrate_analytics_events( LEADIN_PORTAL_ID, LEADIN_HAPIKEY, $batchedPageviews );
        $mark_analytics_event_migration_complete_query = $wpdb->prepare("UPDATE $wpdb->li_pageviews SET pageview_migrated = 1 where lead_hashkey = %s", $hashkey);
        $updated = $wpdb->query($mark_analytics_event_migration_complete_query);
    }


    // get all the form submissions for the contact
    $form_submission_query = $wpdb->prepare("
        SELECT
            form_id,
            form_date, 
            DATE_FORMAT(DATE_ADD(form_date, INTERVAL %d HOUR), %s) AS form_date_formatted, 
            form_page_title, 
            form_hashkey,
            form_page_url, 
            form_fields,
            form_selector_id,
            form_selector_classes
        FROM 
            $wpdb->li_submissions 
        WHERE 
            form_migrated = 0 AND 
            form_deleted = 0 AND 
            lead_hashkey = %s ORDER BY form_date", $gmt_offset, '%Y-%m-%d %k:%i:%s', $hashkey);

    $submissions = $wpdb->get_results($form_submission_query);
    $form_submissions = array();
    if ( count($submissions) )
    {
        foreach ( $submissions as $submission )
        {
            $form_fields = json_decode(stripslashes($submission->form_fields), TRUE);
            $form_fields_formatted = array();
            $contact_fields = array();
            if ( count($form_fields) )
            {
                foreach ( $form_fields as $form_field )
                {
                    $key    = strtolower($form_field['label']);
                    $value  = $form_field['value'];
                    if ( strstr($value, '@') && strstr($value, '.') )
                    {
                        $key = 'email';
                    }
                    if ( $key == 'first' || $key == 'name' || $key == 'your name' )
                    {
                        $key = 'firstName';
                    }
                    if ( $key == 'last' || $key == 'your last name' || $key == 'surname' )
                    {
                        $key = 'lastName';
                    }
                    if ( $key == 'phone' )
                    {
                        $key = 'phone';
                    }

                    if ( $key == 'email' || $key == 'firstName' || $key == 'lastName' || $key == 'phone' )
                    {
                        $contact_fields[$key] = $form_field['value'];
                    }
                    else 
                    {
                        $form_fields_formatted[$key] = $form_field['value'];    
                    }      
                }
            }
            $form_submission = leadin_create_form_submission_array(LEADIN_PORTAL_ID, $utk, $submission->form_selector_id, $submission->form_selector_classes, $submission->form_page_url, $submission->form_page_title, (strtotime($submission->form_date_formatted) * 1000), $form_fields_formatted, $contact_fields);
            $form_submissions[] = $form_submission;

        }
    }

    if (count($form_submissions) > 0 )
    {
        $resp = leadin_migrate_form_submissions(LEADIN_PORTAL_ID, LEADIN_HAPIKEY, $form_submissions);
        $mark_form_as_migrated_query = $wpdb->prepare("UPDATE $wpdb->li_submissions SET form_migrated = 1 WHERE lead_hashkey = %s", $hashkey);
        $wpdb->query($mark_form_as_migrated_query);
    }

    // Afterwards, update the contact as migrated
    $mark_contact_as_migrated_query = $wpdb->prepare("UPDATE $wpdb->li_leads SET lead_migrated = 1 WHERE hashkey = %s", $hashkey);
    $updated = $wpdb->query($mark_contact_as_migrated_query);

    // update the most recent migration
    update_option('leadin_most_recent_migration_timestamp', time());

    die();
}

add_action('wp_ajax_leadin_migrate_contact', 'leadin_migrate_contact'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_migrate_contact', 'leadin_migrate_contact'); // Call when user is not logged in

function leadin_migrate_esp_syncs ()
{
    global $wpdb;
    
    $wpdb->li_tags = ( is_multisite() ? $wpdb->prefix . 'li_tags' : 'li_tags' );

    $active_power_ups   = unserialize(get_option('leadin_active_power_ups'));
    $activated_esps     = array();

    if ( in_array('mailchimp_connect', $active_power_ups) )
    {
        $activated_esps['mailchimp'] = 0;
    }
    
    if ( in_array('aweber_connect', $active_power_ups) )
    {
        $activated_esps['aweber'] = 0;
    }
    
    if ( in_array('campaign_monitor_connect', $active_power_ups) )
    {
        $activated_esps['campaign_monitor'] = 0;
    }
    
    if ( in_array('getresponse_connect', $active_power_ups) )
    {
        $activated_esps['get_response'] = 0;
    }
    
    if ( in_array('constant_contact_connect', $active_power_ups) )
    {
        $activated_esps['constant_contact'] = 0;
    }

    $esp_syncs = array();

    if ( count($activated_esps) )
    {
        foreach ( $activated_esps as $esp => $esp_count )
        {
            $activated_esp_query = $wpdb->prepare("SELECT * FROM $wpdb->li_tags WHERE tag_synced_lists LIKE '%%%s%%' AND tag_form_selectors != '' AND tag_synced_lists != '' AND tag_deleted = 0 GROUP BY tag_form_selectors, tag_synced_lists", $esp);
            $tags = $wpdb->get_results($activated_esp_query);

            if ( count($tags) )
            {
                foreach ( $tags as $tag )
                {
                    $tag_form_selectors = explode(',', $tag->tag_form_selectors);
                    $tag_synced_lists   = unserialize($tag->tag_synced_lists);

                    if ( count($tag_form_selectors) )
                    {
                        foreach ( $tag_form_selectors as $key => $selector )
                        {
                            $selector = trim($selector);
                            if ( strstr($selector, '#') )
                            {
                                if ( count($tag_synced_lists) )
                                {
                                    foreach ( $tag_synced_lists as $list )
                                    {
                                        if ( ! leadin_selector_in_array($selector, $esp_syncs, $list['list_id']) )
                                        {
                                            // get all the lists that this tag is synced too and add them all into the array
                                            $esp_sync = leadin_create_esp_sync($selector, '', $list['list_id']);
                                            $esp_syncs[] = $esp_sync;
                                            $activated_esps[$esp]++;
                                        }
                                    }
                                }

                                unset($tag_form_selectors[$key]);
                            }
                        }

                        if ( count($tag_form_selectors) && count($tag_synced_lists) )
                        {
                            foreach ( $tag_synced_lists as $list )
                            {
                                // sort all the classes alphabetically so they much up if duplicates
                                sort($tag_form_selectors, SORT_STRING);
                                $classes = trim(implode(',', $tag_form_selectors));

                                if ( ! leadin_selector_in_array($classes, $esp_syncs, $list['list_id']) )
                                {
                                    $esp_sync = leadin_create_esp_sync('', $classes, $list['list_id']);
                                    $esp_syncs[] = $esp_sync;
                                    $activated_esps[$esp]++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ( count($activated_esps) >= 1 )
    {
        $esp_api_key = '';
        $esp         = '';

        reset(sort($activated_esps));

        if ( key($activated_esps) == 'mailchimp' )
        {
            $esp_mc_options     = get_option('leadin_mls_options');
            $esp_api_key = ( isset($esp_mc_options['li_mls_api_key']) ? $esp_mc_options['li_mls_api_key'] : '' );
            $esp = 'MAILCHIMP';
        }
        else if ( key($activated_esps) == 'campaign_monitor' )
        {
            $esp_cm_options = get_option('leadin_campaign_monitor_connect_options');
            $esp_api_key = ( isset($esp_cm_options['li_cm_api_key']) ? $esp_cm_options['li_cm_api_key'] : '' );
            $esp = 'CAMPAIGN_MONITOR';
        }
        else if ( key($activated_esps) == 'constant_contact' )
        {
            $esp = 'CONSTANT_CONTACT';
        }
        else if ( key($activated_esps) == 'get_response' )
        {
            $esp_gr_options = get_option('leadin_getresponse_connect_options');
            $esp_api_key = ( isset($esp_cm_options['li_gr_api_key']) ? $esp_cm_options['li_gr_api_key'] : '' );
            $esp = 'GET_RESPONSE';
        }
        else if ( key($activated_esps) == 'aweber' )
        {
            $esp = 'AWEBER';
        }

        // push up a settings request
        $request = leadin_build_api_url_with_auth('/leadin/v1/settings');
        $params = array(
            'espApiKey'                     => $esp_api_key,
            'emailServiceProvider'          => $esp
        );

        $response = leadin_make_curl_request($request, $params, 'PATCH');
    }

    if ( $esp_syncs )
    {
        $request = leadin_build_api_url_with_auth('/leadin/v1/migrate/espSync');
        
        $params = array('espSyncs' => $esp_syncs);
        
        $response = leadin_make_curl_request($request, $params);
    }

}

add_action('wp_ajax_leadin_migrate_esp_syncs', 'leadin_migrate_esp_syncs'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_migrate_esp_syncs', 'leadin_migrate_esp_syncs'); // Call when user is not logged in

function leadin_selector_in_array ( $needle, $esps, $esp_list_id )
{
    $in_array = FALSE;

    if ( count($esps) ) 
    {  
        $keys_found = array_keys($esps, $needle);

        if ( $keys_found )
        {
            foreach ( $keys_found as $key )
            {
                if ( $esps[$key]['espListId'] == $esp_list_id )
                {
                    return TRUE;
                }
                else
                {
                    return FALSE;
                }
            }
        }
    }
    
}


function leadin_create_form_submission_array ( $portal_id, $utk, $form_selector_id, $form_selector_classes, $page_url, $page_title, $timestamp, $form_values, $contact_fields )
{
    # Of the form_selector_class is 'vex-dialog-form' (the leadin form pre-glob) we need to migrate that over to the Leadin Popup Form post glob.
    $final_form_selector_id = $form_selector_classes == '.vex-dialog-form' ? '#LeadinPopupForm' : $form_selector_id;
    $form_submission = array();
    $form_submission['portalId']            = $portal_id;
    $form_submission['utk']                 = $utk;
    $form_submission['formSelectorId']      = $final_form_selector_id; // this is a hack until we get the endpoint to accept form submissions with blank IDs + classes
    $form_submission['formSelectorClasses'] = $form_selector_classes;
    $form_submission['pageUrl']             = $page_url;
    $form_submission['pageTitle']           = $page_title;
    $form_submission['timestamp']           = number_format($timestamp, 0, '', '');
    $form_submission['contactFields']       = $contact_fields;

    if (count((array)$form_values) > 0)
        $form_submission['formValues']      = $form_values;

    return $form_submission;
}

function leadin_migrate_form_submissions ( $portal_id, $hapikey, $form_submissions )
{
    $request = leadin_build_api_url_with_auth('/leadin/v1/migrate/forms');
    
    $params = array('formSubmissions' => $form_submissions);
    
    $response = leadin_make_curl_request($request, $params);
    return $response;
}

function leadin_migrate_analytics_events ( $portal_id, $hapikey, $analytics_events)
{
    $request = leadin_build_api_url_with_auth('/leadin/v1/migrate/analytics');

    $response = leadin_make_curl_request( $request, $analytics_events );
    return $response;
}

function leadin_create_esp_sync ( $form_selector_id, $form_selector_classes, $esp_list_id )
{
    $esp_sync = array();
    $esp_sync['formSelectorId']         = substr($form_selector_id, 0, 1) == '#' ? substr($form_selector_id, 1) : $form_selector_id;
    $esp_sync['formSelectorClasses']    = substr($form_selector_classes, 0, 1) == '.' ? substr($form_selector_classes, 1) : $form_selector_classes;
    $esp_sync['espListId']              = $esp_list_id;
    return $esp_sync;
}



function leadin_migrate_settings ()
{
    $leadin_options     = get_option('leadin_options');
    $active_power_ups   = unserialize(get_option('leadin_active_power_ups'));
    $subscribe_options  = get_option('leadin_subscribe_options');
    $params = array();
    $popup_enabled = FALSE;

    update_option( LEADIN_MIGRATION_OPTION_NAME, 'started');
    
    if ( in_array('subscribe_widget', $active_power_ups) && $subscribe_options )
    {
        $popup_enabled      = TRUE;
        $popup_heading      = ( isset($subscribe_options['li_subscribe_heading']) ? $subscribe_options['li_subscribe_heading'] : '' );
        $popup_desc         = ( isset($subscribe_options['li_subscribe_text']) ? $subscribe_options['li_subscribe_text'] : '' );
        $popup_button_text  = ( isset($subscribe_options['li_subscribe_btn_label']) ? $subscribe_options['li_subscribe_btn_label'] : '' );
        $popup_show_names   = ( isset($subscribe_options['li_subscribe_name_fields']) ? $subscribe_options['li_subscribe_name_fields'] : '' );
        $popup_show_phone   = ( isset($subscribe_options['li_subscribe_phone_field']) ? $subscribe_options['li_subscribe_phone_field'] : '' );
        
        if ( isset($subscribe_options['li_subscribe_vex_class']) )
        {
            switch ( $subscribe_options['li_subscribe_vex_class'] )
            {
                case 'vex-theme-bottom-right-corner' :
                    $popup_position = 'BOTTOM_RIGHT';
                break;

                case 'vex-theme-bottom-left-corner' :
                    $popup_position = 'BOTTOM_LEFT';
                break;

                case 'vex-theme-top' :
                    $popup_position = 'TOP';
                break;

                case 'vex-theme-default' :
                    $popup_position = 'POP_OVER';
                break;
            }  
        }

        if ( isset($subscribe_options['li_subscribe_btn_color']) )
        {
            switch ( $subscribe_options['li_subscribe_btn_color'] )
            {
                case 'leadin-popup-color-blue' :
                    $popup_color = 'BLUE';
                break;

                case 'leadin-popup-color-red' :
                    $popup_color = 'RED';
                break;

                case 'leadin-popup-color-green' :
                    $popup_color = 'GREEN';
                break;

                case 'leadin-popup-color-yellow' :
                    $popup_color = 'YELLOW';
                break;

                case 'leadin-popup-color-purple' :
                    $popup_color = 'PURPLE';
                break;

                case 'leadin-popup-color-orange' :
                    $popup_color = 'ORANGE';
                break;
            }  
        }

        $popup_page_types = array();

        if ( isset($subscribe_options['li_subscribe_template_pages']) )
            array_push($popup_page_types, 'page');
        
        if ( isset($subscribe_options['li_subscribe_template_posts']) )
            array_push($popup_page_types, 'post');
        
        if ( isset($subscribe_options['li_subscribe_template_home']) )
            array_push($popup_page_types, 'home');
        
        if ( isset($subscribe_options['li_subscribe_template_archives']) )
            array_push($popup_page_types, 'archive');
        
        $popup_show_mobile = FALSE;
        
        if ( isset($subscribe_options['li_subscribe_mobile_popup']) )
            $popup_show_mobile = TRUE;

        $params['popupHeading']         = $popup_heading;
        $params['popupDescription']     = $popup_desc;
        $params['popupButtonText']      = $popup_button_text;
        $params['popupShowNames']       = ( $popup_show_names ? TRUE : FALSE );
        $params['popupShowPhone']       = ( $popup_show_phone ? TRUE : FALSE );
        $params['popupPosition']        = $popup_position;
        $params['popupColor']           = $popup_color;
        $params['popupPageTypes']       = implode(",", $popup_page_types);
        $params['popupShowOnMobile']    = $popup_show_mobile;
    }

    $user_roles = get_editable_roles();
    $ignored_user_roles = array();
    
    if ( count($user_roles) )
    {
        foreach ( $user_roles as $key => $role )
        {
            $role_id_tracking = 'li_do_not_track_' . $key;
            if ( isset($leadin_options[$role_id_tracking]) )
                array_push($ignored_user_roles, $key);
        }
    }
    // @TODO (need to discuss more...) - we never built the grant access to Leadin for specific user roles features - shoudld probably put that into the migrator once it's done if we do it
        // https://git.hubteam.com/HubSpot/Leadin/issues/387
    $notification_email = ( isset($leadin_options['li_email']) ? $leadin_options['li_email'] : '' );
    $site_name = get_bloginfo('name');
    $domain = get_bloginfo('wpurl');

    $request = leadin_build_api_url_with_auth('/leadin/v1/settings');

    $params['popupEnabled']                 = $popup_enabled;
    $params['ignoredUserRoles']             = implode(",", $ignored_user_roles);
    $params['notificationEmailAddresses']   = $notification_email;
    $params['siteName']                     = $site_name;
    $params['domain']                       = $domain;

    $response = leadin_make_curl_request($request, $params, 'PATCH');
    return $response;
}

add_action('wp_ajax_leadin_migrate_settings', 'leadin_migrate_settings'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_migrate_settings', 'leadin_migrate_settings'); // Call when user is not logged in

function leadin_punt_migration ()
{
    $option_updated = update_option('leadin_puntMigration', 'true');
    return $option_updated;
}

add_action('wp_ajax_leadin_punt_migration', 'leadin_punt_migration'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_punt_migration', 'leadin_punt_migration'); // Call when user is not logged in

function leadin_set_migration_complete_flag ( )
{
    if (get_option(LEADIN_MIGRATION_OPTION_NAME) != 'completed')
    {
        $request = leadin_build_api_url_with_auth('/leadin/v1/migrate/complete');
        
        $response = leadin_make_curl_request($request);
        update_option(LEADIN_MIGRATION_OPTION_NAME, 'completed');
    }
}

add_action('wp_ajax_leadin_set_migration_complete_flag', 'leadin_set_migration_complete_flag'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_set_migration_complete_flag', 'leadin_set_migration_complete_flag'); // Call when user is not logged in

function leadin_set_migration_complete_option ()
{
    update_option(LEADIN_MIGRATION_OPTION_NAME, 'completed');
}

add_action('wp_ajax_leadin_set_migration_complete_option', 'leadin_set_migration_complete_option'); // Call when user logged in
add_action('wp_ajax_nopriv_leadin_set_migration_complete_option', 'leadin_set_migration_complete_option'); // Call when user is not logged in


function leadin_make_curl_request ( $request, $params = array(), $request_type = 'POST' )
{
    // remove NULL values from params
    $notNullParams = array_filter($params);

    if ( $request_type == 'GET' )
    {
        $request = $request . "?" . http_build_query($notNullParams);
    }

    $ch = curl_init($request);
    
    if ( $request_type == 'POST' )
    {
        curl_setopt($ch, CURLOPT_POST, TRUE);
    }
    else if ( $request_type == 'PATCH' )
    {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    }

    if ( $request_type != 'GET' )
    {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notNullParams)); 
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $response = curl_exec($ch);

    $info = curl_getinfo($ch);

    curl_close($ch);
    return $response;
}

//=============================================
// Unpunt Migration
//=============================================
function leadin_unpunt_migration ()
{
    update_option('leadin_puntMigration', 'false');
}

add_action('wp_ajax_leadin_unpunt_migration', 'leadin_unpunt_migration'); // Call when user logged in


function leadin_check_migration_status()
{

    global $wpdb; 
    $wpdb->li_leads = ( is_multisite() ? $wpdb->prefix . 'li_leads' : 'li_leads' );

    $curentMigrationStatus = get_option(LEADIN_MIGRATION_OPTION_NAME);
    if (!$curentMigrationStatus)
    {
        # we don't know where we are on migration - we need to perform our first check
        # If we don't have a portalID but have leadin tables, then we set migration to 'register'
        $leads_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$wpdb->li_leads'") == 'li_leads';
        if (!LEADIN_PORTAL_ID && $leads_table_exists)
        {
            return "register";
        }
        else if (! function_exists('curl_init') )
        {
            return 'noCurl';
        }
        else if (LEADIN_PORTAL_ID && $leads_table_exists && is_array(leadin_get_contacts_for_migration()))
        {
            $newValue = 'migrateContacts';
            update_option(LEADIN_MIGRATION_OPTION_NAME, $newValue);
            return $newValue;
        }
        else if (!$leads_table_exists)
        {
            $newValue = 'false';
            update_option(LEADIN_MIGRATION_OPTION_NAME, $newValue);
            return $newValue;
        }
    }
    else
    {
        return $curentMigrationStatus;
    }

}

/**
 * Adds the migration columns to a pre-existing leadin database
 *
 */

function leadin_maybe_add_migration_db_columns ()
{
    global $wpdb;

    $wpdb->li_submissions      = ( is_multisite() ? $wpdb->prefix . 'li_submissions' : 'li_submissions' );
    $wpdb->li_pageviews        = ( is_multisite() ? $wpdb->prefix . 'li_pageviews' : 'li_pageviews' );
    $wpdb->li_leads            = ( is_multisite() ? $wpdb->prefix . 'li_leads' : 'li_leads' );

    $leads_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$wpdb->li_leads'");

    if ($leads_table_exists)
    {

        $leads_migrated_flag_exists = $wpdb->get_row("
            SELECT * 
            FROM information_schema.COLUMNS 
            WHERE 
            TABLE_SCHEMA = '$wpdb->dbname' AND
            TABLE_NAME = '$wpdb->li_leads' 
            AND COLUMN_NAME = 'lead_migrated';");

        if ( ! count($leads_migrated_flag_exists) )
        {
            $q = "ALTER TABLE $wpdb->li_leads ADD lead_migrated INT(1) NOT NULL";
            $wpdb->query($q);
        }

        $analytics_migrated_flag_exists = $wpdb->get_row("
            SELECT * 
            FROM information_schema.COLUMNS 
            WHERE 
            TABLE_SCHEMA = '$wpdb->dbname' AND
            TABLE_NAME = '$wpdb->li_pageviews' 
            AND COLUMN_NAME = 'pageview_migrated';");

        if ( ! count($analytics_migrated_flag_exists) )
        {
            $q = "ALTER TABLE $wpdb->li_pageviews ADD pageview_migrated INT(1) NOT NULL";
            $wpdb->query($q);
        }

        $submissions_migrated_flag_exists = $wpdb->get_row("
            SELECT * 
            FROM information_schema.COLUMNS 
            WHERE 
            TABLE_SCHEMA = '$wpdb->dbname' AND
            TABLE_NAME = '$wpdb->li_submissions' 
            AND COLUMN_NAME = 'form_migrated';");

        if ( ! count($submissions_migrated_flag_exists) )
        {
            $q = "ALTER TABLE $wpdb->li_submissions ADD form_migrated INT(1) NOT NULL";
            $wpdb->query($q);
        }

    }
}

?>
