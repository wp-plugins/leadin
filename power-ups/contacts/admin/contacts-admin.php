<?php

//=============================================
// Include Needed Files
//=============================================


//=============================================
// WPLeadInAdmin Class
//=============================================
class WPLeadInContactsAdmin extends WPLeadInAdmin {
    
    /**
     * Class constructor
     */
    function __construct ()
    {
        //=============================================
        // Hooks & Filters
        //=============================================

        if ( is_admin() )
        {
            add_action('admin_print_scripts', array(&$this, 'add_leadin_admin_scripts'));
        }
    }

    //=============================================
    // Settings Page
    //=============================================

    /**
     * Creates settings page
     */
    function power_up_setup_callback ()
    {
        WPLeadInContactsAdmin::leadin_contacts_page();
    }


    //=============================================
    // Contacts Page
    //=============================================

    /**
     * Shared functionality between contact views 
     */
    function leadin_contacts_page ()
    {
        global  $wp_version;

        $action = $this->leadin_current_action();
        if ( $action == 'delete' )
        {
            $lead_id = ( isset($_GET['lead']) ? absint($_GET['lead']) : FALSE );
            $this->delete_lead($lead_id);
        }

        echo '<div id="leadin" class="wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php')  ? 'pre-mp6' : ''). '">';

            if ( $action != 'view' ) {
                leadin_track_plugin_activity("Loaded Contact List Page");
                $this->leadin_render_list_page();
            }
            else {
                leadin_track_plugin_activity("Loaded Contact Detail Page");
                $this->leadin_render_contact_detail($_GET['lead']);
            }

            $this->leadin_footer();

        echo '</div>';
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

    /**
     * Creates view a contact's deteails + timeline history
     *
     * @param   int
     */
    function leadin_render_contact_detail ( $lead_id )
    {
        $li_contact = new LI_Contact();
        $li_contact->set_hashkey_by_id($lead_id);
        $li_contact->get_contact_history();
        
        $lead_email = $li_contact->history->lead->lead_email;
        $url_parts = parse_url($lead->lead_source);
        $lead_source = urldecode(rtrim($url_parts['host'] . '/' . $url_parts['path'], '/'));

        if ( isset($_GET['post_id']) )
            echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/post.php?post=' . $_GET['post_id'] . '&action=edit#li_analytics-meta">&larr; All Viewers</a>';
        else
            echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts">&larr; All Contacts</a>';


        echo '<div class="header-wrap">';
            echo '<img height="40px" width="40px" src="https://app.getsignals.com/avatar/image/?emails=' . $lead_email . '" class="leadin-dynamic-avatar_' . substr($lead_id, -1) . '"/>';
            echo '<h1 class="contact-name">' . $lead_email . '</h1>';
        echo '</div>';
        
        echo '<div id="col-container">';
            
            echo '<div id="col-right">';
            echo '<h2>Contact History</h2>';
            echo '<div class="col-wrap contact-history">';
                echo '<ul class="sessions">';
                $sessions = array_reverse($li_contact->history->sessions);
                foreach ( $sessions as &$session )
                {
                    $first_event = array_values($session['events']);
                    $first_event_date = $first_event[0]['activities'][0]['event_date'];
                    $session_date = date('M j', strtotime($first_event_date));
                    $session_start_time = date('g:i a', strtotime($first_event_date));

                    $last_event = end($session['events']);
                    $last_activity = end($last_event['activities']);
                    $session_end_time = date('g:i a', strtotime($last_activity['event_date']));

                    if ( $session_end_time != $session_start_time )
                        $session_time_range = $session_start_time . ' - ' . $session_end_time;
                    else
                        $session_time_range = $session_start_time;

                    echo '<li class="session">';
                    echo '<h3 class="session-date">' . $session_date . '<span class="event-time-range">' . $session_time_range . '</span></h3>';

                    echo '<ul class="events">';

                    $events = $session['events'];
                    foreach ( $events as &$event )
                    {
                        if ( $event['event_type'] == 'pageview' )
                        {
                            $pageview = $event['activities'][0];
                            
                            if ( $pageview['event_date'] == $first_event_date )
                            {
                                echo '<li class="event source">';
                                    echo '<p class="event-title">Traffic Source: ' . ( $pageview['pageview_source'] ? '<a href="' . $pageview['pageview_source'] . '">' . $pageview['pageview_source'] : 'Direct' ) . '</a></p>';
                                echo '</li>';
                            }
                            
                            
                            echo '<li class="event pageview">';
                                echo '<p class="event-title">' . $pageview['pageview_title'] . '<span class="event-time-range">' . date('g:ia', strtotime($pageview['event_date'])) . '</span></p>';
                                echo '<a class="pageview-url" target="_blank" href="' . $pageview['pageview_url'] . '">' . $pageview['pageview_url'] . '</a>';
                            echo '</li>';
                        }
                        else if ( $event['event_type'] == 'form' )
                        {
                            $submission = $event['activities'][0];

                            $form_fields = json_decode(stripslashes($submission['form_fields']));
                            $num_form_fieds = count($form_fields);
                            
                            echo '<li class="event form-submission">';
                                echo '<p class="event-title">Filled out form on page <a href="' . $submission['form_page_url'] . '">' . $submission['form_page_title']  . '</a><span class="event-time-range">' . date('g:ia', strtotime($submission['event_date'])) . '</span></p>';
                                echo '<ul class="event-detail fields">';
                                foreach ( $form_fields as $field )
                                {
                                    echo '<li class="field">';
                                        echo '<label class="field-label">' . $field->label . ':</label>';
                                        echo '<p class="field-value">' . $field->value . '</p>';
                                    echo '</li>';
                                }
                                echo '</ul>';
                            echo '</li>';
                        }
                    }
                    echo '</ul>';
                    echo '</li>';
                }
                echo '</ul>';
            echo '</div>';
            echo '</div>';

            echo '<div id="col-left" class="metabox-holder">';
            echo '<div class="col-wrap">';
                echo '<div class="contact-info postbox">';
                    echo '<h3>Contact Information</h3>';
                    echo '<div class="inside">';
                        echo '<p><label>Email:</label> <a href="mailto:' . $lead_email . '">' . $lead_email . '</a></p>';
                        echo '<p><label>Status:</label> ' . $li_contact->history->lead->lead_status . '</p>';
                        echo '<p><label>Original referrer:</label> <a href="' . $li_contact->history->lead->lead_source . '">' . $li_contact->history->lead->lead_source . '</a></p>';
                        echo '<p><label>First visit:</label> ' . self::date_format_contact_stat($li_contact->history->lead->first_visit) . '</p>';
                        echo '<p><label>Last Visit:</label> ' . self::date_format_contact_stat($li_contact->history->lead->last_visit) . '</p>';
                        echo '<p><label>Total Visits:</label> ' . $li_contact->history->lead->total_visits . '</p>';
                        echo '<p><label>Total Pageviews:</label> ' . $li_contact->history->lead->total_pageviews . '</p>';
                        echo '<p><label>First submission:</label> ' . self::date_format_contact_stat($li_contact->history->lead->first_submission) . '</p>';
                        echo '<p><label>Last submission:</label> ' . self::date_format_contact_stat($li_contact->history->lead->last_submission) . '</p>';
                        echo '<p><label>Total submissions:</label> ' . $li_contact->history->lead->total_submissions . '</p>';
                    echo '</div>';
                echo '</div>';
            echo '</div>';
            echo '</div>';

        echo '</div>';
    }


    /**
     * Creates list table for Contacts page
     *
     */
    function leadin_render_list_page ()
    {
        global $wp_version;

        //Create an instance of our package class...
        $leadinListTable = new LI_List_table();
        
        //Fetch, prepare, sort, and filter our data...
        $leadinListTable->data = $leadinListTable->get_leads();
        $leadinListTable->prepare_items();

        ?>
            
        <?php 
            $this->leadin_header('LeadIn Contacts');

            $current_view = $leadinListTable->get_view();

            if ( $current_view == 'lead' )
                $view_label = 'Leads';
            else if ( $current_view == 'comment' )
                $view_label = 'Commenters';
            else if ( $current_view == 'subscribe' )
                $view_label = 'Subscribers';
            else
                $view_label = 'Contacts';
        ?>

        <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
        <form id="leadin-contacts" method="GET">
            <?php $leadinListTable->views(); ?>
            
            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
            
            <!-- Now we can render the completed list table -->
            <?php $leadinListTable->display() ?>
        </form>

        <form id="export-form" name="export-form" method="POST">
            <p class="submit">
                <?php if ( !isset($_GET['contact_type']) ) : ?>
                    <input type="submit" value="<?php esc_attr_e('Export All Contacts'); ?>" name="export-all" id="leadin-export-leads" class="button button-primary">
                <?php endif; ?>
                <input type="submit" value="<?php esc_attr_e('Export Selected ' . $view_label ); ?>" name="export-selected" id="leadin-export-selected-leads" class="button button-primary" disabled>
                <input type="hidden" id="leadin-selected-contacts" name="leadin-selected-contacts" value=""/>
            </p>
        </form>

        <?php
    }

    /**
     * Deletes all rows from li_leads, li_pageviews and li_submissions for a given lead
     *
     * @param   int
     * @return  bool
     */
    function delete_lead ( $lead_id )
    {
        global $wpdb;

        $q = $wpdb->prepare("SELECT hashkey FROM li_leads WHERE lead_id = %d", $lead_id);
        $lead_hash = $wpdb->get_var($q);

        $q = $wpdb->prepare("UPDATE li_pageviews SET pageview_deleted = 1 WHERE lead_hashkey = %s AND pageview_deleted = 0", $lead_hash);
        $delete_pageviews = $wpdb->query($q);

        $q = $wpdb->prepare("UPDATE li_submissions SET form_deleted = 1  WHERE lead_hashkey = %s AND form_deleted = 0", $lead_hash);
        $delete_submissions = $wpdb->query($q);

        $q = $wpdb->prepare("UPDATE li_leads SET lead_deleted = 1 WHERE lead_id = %d AND lead_deleted = 0", $lead_id);
        $delete_lead = $wpdb->query($q);

        return $delete_lead;
    }


    //=============================================
    // Admin Styles & Scripts
    //=============================================

    /**
     * Adds admin javascript
     */
    function add_leadin_admin_scripts ()
    {
        global $pagenow;

        if ( ($pagenow == 'admin.php' && isset($_GET['page']) && strstr($_GET['page'], 'leadin')) || ( $pagenow == 'post.php' && isset($_GET['post']) && isset($_GET['action']) && strstr($_GET['action'], 'edit') ) ) 
        {
            wp_register_script('leadin-admin-js', LEADIN_PATH . '/admin/js/leadin-admin.js', array ( 'jquery' ), FALSE, TRUE);
            wp_enqueue_script('leadin-admin-js');

            wp_register_script('lazyload-js', LEADIN_PATH . '/admin/js/jquery.lazyload.min.js', array ( 'jquery' ), FALSE, TRUE);
            wp_enqueue_script('lazyload-js');
        }
    }

    /**
     * Formats any timestamp to format like Feb 4 8:43pm
     *
     * @param   string
     * @return  string
     */
    function date_format_contact_stat ( $timestamp )
    {
        return date('M j, Y g:i:a', strtotime($timestamp));
    }

}

/** Export functionality for the contacts list */
if ( isset($_POST['export-all']) || isset($_POST['export-selected']) )
{
    global $wpdb;

    $sitename = sanitize_key(get_bloginfo('name'));
    
    if ( ! empty($sitename) )
        $sitename .= '.';
    
    $filename = $sitename . '.contacts.' . date('Y-m-d-H-i-s') . '.csv';

    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Content-Type: text/csv; charset=' . get_option('blog_charset'), TRUE);

    $column_headers = array(
        'Email', 'Original source', 'Status', 'Visits', 'Page views', 'Forms',  'Last visit', 'Created on'
    );

    $fields = array(
        'lead_email', 'lead_source', 'lead_status', 'lead_visits', 'lead_pageviews', 'lead_form_submissions', 'last_visit', 'lead_date'
    );

    $headers = array();
    foreach ( $column_headers as $key => $field )
    {
            $headers[] = '"' . $field . '"';
    }
    echo implode(',', $headers) . "\n";

    $q = $wpdb->prepare("
        SELECT 
            l.lead_id, LOWER(DATE_FORMAT(l.lead_date, %s)) AS lead_date, l.lead_ip, l.lead_source, l.lead_email, l.lead_status,
            COUNT(DISTINCT s.form_id) AS lead_form_submissions,
            COUNT(DISTINCT p.pageview_id) AS lead_pageviews,
            (SELECT COUNT(DISTINCT p.pageview_id) FROM li_pageviews p WHERE l.hashkey = p.lead_hashkey AND p.pageview_session_start = 1) AS lead_visits,
            LOWER(DATE_FORMAT(MAX(p.pageview_date), %s)) AS last_visit
        FROM li_leads l
        LEFT JOIN li_submissions s ON l.hashkey = s.lead_hashkey
        LEFT JOIN li_pageviews p ON l.hashkey = p.lead_hashkey 
        WHERE l.lead_email != '' " .
        ( isset ($_POST['export-selected']) ? " AND l.lead_id IN ( " . $_POST['leadin-selected-contacts'] . " ) " : "" ) .
        "GROUP BY l.lead_email ORDER BY l.lead_date DESC", '%Y/%m/%d %l:%i%p', '%Y/%m/%d %l:%i%p');

    $leads = $wpdb->get_results($q);

    foreach ( $leads as $contacts )
    {
        $data = array();
        foreach ( $fields as $field )
        {
            $value = ( isset($contacts->{$field}) ? $contacts->{$field} : '' );
            $value = ( is_array($value) ? serialize($value) : $value );
            $data[] = '"' . str_replace('"', '""', $value) . '"';
        }
        echo implode(',', $data) . "\n";
    }

    exit;
}

?>
