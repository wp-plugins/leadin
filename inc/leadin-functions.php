<?php

if ( !defined('LEADIN_PLUGIN_VERSION') ) 
{
    header('HTTP/1.0 403 Forbidden');
    die;
}

/**
 * Looks for a GET/POST value and echos if present. If nothing is set, echos blank
 *
 * @param   string
 * @return  null
 */
function print_submission_val ( $url_param ) 
{
    if ( isset($_GET[$url_param]) ) 
    {
        return $_GET[$url_param];
    }

    if ( isset($_POST[$url_param]) )
    {
        return $_POST[$url_param];
    }

    return '';
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

    return update_option($option, $options_array);
}

/**
 * Prints a number with a singular or plural label depending on number
 *
 * @param   int
 * @param   string
 * @param   string
 * @return  string 
 */
function leadin_single_plural_label ( $number, $singular_label, $plural_label ) 
{
    //Set number = 0 when the variable is blank
    $number = ( !is_numeric($number) ? 0 : $number );

    return ( $number != 1 ? $number . " $plural_label" : $number . " $singular_label" );
}

/**
 * Get LeadIn user
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
        'wp_version' => $wp_version
    );

    return $leadin_user;
}

/**
 * Register LeadIn user
 *
 * @return  bool
 */
function leadin_register_user ()
{
    $leadin_user = leadin_get_current_user();
    $mp = LeadIn\LI_Mixpanel::getInstance(MIXPANEL_PROJECT_TOKEN);
    
    // @push mixpanel event for updated email
    $mp->identify($leadin_user['user_id']);
    $mp->createAlias( $leadin_user['user_id'],  $leadin_user['alias']);
    $mp->people->set( $leadin_user['user_id'], array(
        '$email'            => $leadin_user['email'],
        '$wp-url'           => $leadin_user['wp_url'],
        '$wp-version'       => $leadin_user['wp_version'],
        '$li-version'       => $leadin_user['li_version']
    ));

    // @push contact to HubSpot

    $hs_context = array(
        'pageName' => 'Plugin Settings'
    );

    $hs_context_json = json_encode($hs_context);
    
    //Need to populate these varilables with values from the form.
    $str_post = "email=" . urlencode($leadin_user['email'])
        . "&li_version=" . urlencode($leadin_user['li_version'])
        . "&leadin_stage=Activated"
        . "&li_user_id=" . urlencode($leadin_user['user_id'])
        . "&website=" . urlencode($leadin_user['wp_url'])
        . "&wp_version=" . urlencode($leadin_user['wp_version'])
        . "&hs_context=" . urlencode($hs_context_json);
    
    $endpoint = 'https://forms.hubspot.com/uploads/form/v2/324680/d93719d5-e892-4137-98b0-913efffae204';
    
    $ch = @curl_init();
    @curl_setopt($ch, CURLOPT_POST, true);
    @curl_setopt($ch, CURLOPT_POSTFIELDS, $str_post);
    @curl_setopt($ch, CURLOPT_URL, $endpoint);
    @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = @curl_exec($ch);  //Log the response from HubSpot as needed.
    @curl_close($ch);
    echo $response;

    return true;
}

/**
 * Send Mixpanel event when plugin is activated/deactivated
 *
 * @param   bool
 *
 * @return  bool
 */
function leadin_track_plugin_registration_hook ( $activated )
{
    if ($activated) {
        leadin_register_user();
        leadin_track_plugin_activity("Activated Plugin");
    }
    else {
        leadin_track_plugin_activity("Deactivated Plugin");
    }

    return true;
}

/**
 * Track plugin activity in MixPanel
 *
 * @param   string
 *
 * @return  array
 */
function leadin_track_plugin_activity ( $activity_desc )
{
    $leadin_user = leadin_get_current_user();

    global $wp_version;
    global $current_user;
    get_currentuserinfo();
    $user_id = md5(get_bloginfo('wpurl'));

    $mp = LeadIn\LI_Mixpanel::getInstance(MIXPANEL_PROJECT_TOKEN);
    $mp->track($activity_desc, array("distinct_id" => $user_id, '$wp-url' => get_bloginfo('wpurl'), '$wp-version' => $wp_version, '$li-version' => LEADIN_PLUGIN_VERSION));

    return true;
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
 * Deletes an element or elements from an array
 *
 * @param   array
 * @param   wildcard
 * @return  array
 */
function leadin_array_delete ( $array, $element )
{
    if ( !is_array($element) )
        $element = array($element);

    return array_diff($array, $element);
}

/**
 * Deletes an element or elements from an array
 *
 * @param   array
 * @param   wildcard
 * @return  array
 */
function leadin_get_value_by_key ( $key_value, $array )
{
    foreach ( $array as $key => $value )
    {
        if ( is_array($value) && $value['label'] == $key_value )
            return $value['value'];
    }

    return null;
}

/** 
 * Data recovery algorithm for 0.7.2 upgrade
 *
 */
function leadin_recover_contact_data ()
{
    global $wpdb;

    $q = $wpdb->prepare("SELECT * FROM li_submissions WHERE form_fields LIKE '%%%s%%' AND form_fields LIKE '%%%s%%'", '@', '.');
    $submissions = $wpdb->get_results($q);

    if ( count($submissions) )
    {
        foreach ( $submissions as $submission )
        {
            $json = json_decode(stripslashes($submission->form_fields), TRUE);

            foreach ( $json as $object )
            {
                if ( strstr($object['value'], '@') && strstr($object['value'], '@') && strlen($object['value']) <= 254 )
                {
                    // check to see if the contact exists and if it does, skip the data recovery
                    $q = $wpdb->prepare("SELECT lead_email FROM li_leads WHERE lead_email = %s", $object['value']);
                    $exists = $wpdb->get_var($q);

                    if ( $exists )
                        continue;

                    // get the original data
                    $q = $wpdb->prepare("SELECT pageview_date, pageview_source FROM li_pageviews WHERE lead_hashkey = %s ORDER BY pageview_date ASC LIMIT 1", $submission->lead_hashkey);
                    $first_pageview = $wpdb->get_row($q);

                    // recreate the contact
                    $q = $wpdb->prepare("INSERT INTO li_leads ( lead_date, hashkey, lead_source, lead_email, lead_status ) VALUES ( %s, %s, %s, %s, %s )",
                        ( $first_pageview->pageview_date ? $first_pageview->pageview_date : $submission->form_date), 
                        $submission->lead_hashkey,
                        ( $first_pageview->pageview_source ? $first_pageview->pageview_source : ''),
                        $object['value'], 
                        $submission->form_type
                    );

                    $wpdb->query($q);
                }
            }
        }
    }

    leadin_update_option('leadin_options', 'data_recovered', 1);
}

function sort_power_ups ( $power_ups, $ordered_power_ups ) 
{ 
    $ordered = array();
    $i = 0;
    foreach ( $ordered_power_ups as $key )
    {
        if ( in_array($key, $power_ups) )
        {
            array_push($ordered, $key);
            $i++;
        }
    }

    return $ordered;
}
?>