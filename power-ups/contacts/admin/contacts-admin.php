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
        if ( isset($_GET['contact_status']) )
        {
            $this->update_contact_status($lead_id, $_GET['contact_status']);
        }

        $li_contact = new LI_Contact();
        $li_contact->set_hashkey_by_id($lead_id);
        $li_contact->get_contact_history();
        
        $lead_email = $li_contact->history->lead->lead_email;

        $lead_source = leadin_strip_params_from_url($li_contact->history->lead->lead_source);

        if ( isset($_GET['post_id']) )
            echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/post.php?post=' . $_GET['post_id'] . '&action=edit#li_analytics-meta">&larr; All Viewers</a>';
        else if ( isset($_GET['stats_dashboard']) )
            echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_stats">&larr; Stat Dashboard</a>';
        else
            echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts">&larr; All Contacts</a>';

        echo '<div class="contact-header-wrap">';
            echo '<img class="contact-header-avatar leadin-dynamic-avatar_' . substr($lead_id, -1) . '" height="76px" width="76px" src="https://app.getsignals.com/avatar/image/?emails=' . $lead_email . '"/>';
            echo '<div class="contact-header-info">';
                echo '<h1 class="contact-name">' . $lead_email . '</h1>';
                echo '<form id="contact-status" class="contact-status">';
                    echo '<input type="hidden" name="page" value="leadin_contacts">';
                    echo '<input type="hidden" name="action" value="view">';
                    echo '<input type="hidden" name="lead" value="' . $_GET['lead'] . '">';
                    echo '<label>contact status </label>';
                    echo '<select id="leadin-contact-status" name="contact_status">';
                        echo '<option value="lead" ' . ( $li_contact->history->lead->lead_status == 'Lead' ? 'selected="selected"' : '' ) . '>lead</option>';
                        echo '<option value="comment" ' . ( $li_contact->history->lead->lead_status == 'Commenter' ? 'selected="selected"' : '' ) . '>commenter</option>';
                        echo '<option value="subscribe" ' . ( $li_contact->history->lead->lead_status == 'Subscriber' ? 'selected="selected"' : '' ) . '>subscriber</option>';
                    echo '</select>';
                    echo '<input type="submit" name="" id="leadin-contact-status-button" class="button action" style="margin-left: 5px;" value="Apply">';
                echo '</form>';
            echo '</div>';
        echo '</div>';
        
        echo '<div id="col-container">';
            
            echo '<div id="col-right">';
            echo '<div class="col-wrap contact-history">';
                echo '<ul class="sessions">';
                $sessions = $li_contact->history->sessions;
                foreach ( $sessions as &$session )
                {
                    $first_event = end($session['events']);
                    $first_event_date = $first_event['event_date'];
                    $session_date = date('F j, Y, g:ia', strtotime($first_event['event_date']));
                    $session_start_time = date('g:ia', strtotime($first_event['event_date']));

                    $last_event = array_values($session['events']);
                    $session_end_time = date('g:ia', strtotime($last_event[0]['event_date']));

                    echo '<li class="session">';
                    echo '<h3 class="session-date">' . $session_date . ( $session_start_time != $session_end_time ? ' - ' . $session_end_time : '' ) . '</h3>';

                    echo '<ul class="events">';

                    //$events = array_reverse($session['events']);
                    $events = $session['events'];
                    foreach ( $events as &$event )
                    {
                        if ( $event['event_type'] == 'pageview' )
                        {
                            $pageview = $event['activities'][0];
                            
                            echo '<li class="event pageview">';
                                echo '<div class="event-time">' . date('g:ia', strtotime($pageview['event_date'])) . '</div>';
                                echo '<div class="event-content">';
                                    echo '<p class="event-title">' . $pageview['pageview_title'] . '</p>';
                                    echo '<a class="event-detail pageview-url" target="_blank" href="' . $pageview['pageview_url'] . '">' . leadin_strip_params_from_url($pageview['pageview_url']) . '</a>';
                                echo '</div>';
                            echo '</li>';

                            if ( $pageview['event_date'] == $first_event['event_date'] )
                            {
                                echo '<li class="event source">';
                                    echo '<div class="event-time">' . date('g:ia', strtotime($pageview['event_date'])) . '</div>';
                                    echo '<div class="event-content">';
                                        echo '<p class="event-title">Traffic Source: ' . ( $pageview['pageview_source'] ? '<a href="' . $pageview['pageview_source'] . '">' . leadin_strip_params_from_url($pageview['pageview_source']) : 'Direct' ) . '</a></p>';
                                    echo '</div>';
                                echo '</li>';
                            }
                        }
                        else if ( $event['event_type'] == 'form' )
                        {
                            $submission = $event['activities'][0];

                            $form_fields = json_decode(stripslashes($submission['form_fields']));
                            $num_form_fieds = count($form_fields);
                            
                            echo '<li class="event form-submission">';
                                echo '<div class="event-time">' . date('g:ia', strtotime($submission['event_date'])) . '</div>';
                                echo '<div class="event-content">';
                                    echo '<p class="event-title">Filled out form on page <a href="' . $submission['form_page_url'] . '">' . $submission['form_page_title']  . '</a></p>';
                                    echo '<ul class="event-detail fields">';
                                    if ( count($form_fields) )
                                    {
                                        foreach ( $form_fields as $field )
                                        {
                                            echo '<li class="field">';
                                                echo '<label class="field-label">' . $field->label . ':</label>';
                                                echo '<p class="field-value">' . $field->value . '</p>';
                                            echo '</li>';
                                        }
                                    }
                                    echo '</ul>';
                                echo '</div>';
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
                echo '<div class="contact-info leadin-postbox">';
                    echo '<div class="leadin-postbox__content">';
                        echo '<p><b>Email:</b> <a href="mailto:' . $lead_email . '">' . $lead_email . '</a></p>';
                        echo '<p><b>Status:</b> ' . $li_contact->history->lead->lead_status . '</p>';
                        echo '<p><b>Original referrer:</b> ' . ( $li_contact->history->lead->lead_source ? '<a href="' . $li_contact->history->lead->lead_source . '">' . $lead_source . '</a></p>' : 'Direct' );
                        echo '<p><b>First visit:</b> ' . self::date_format_contact_stat($li_contact->history->lead->first_visit) . '</p>';
                        echo '<p><b>Last Visit:</b> ' . self::date_format_contact_stat($li_contact->history->lead->last_visit) . '</p>';
                        echo '<p><b>Total Visits:</b> ' . $li_contact->history->lead->total_visits . '</p>';
                        echo '<p><b>Total Pageviews:</b> ' . $li_contact->history->lead->total_pageviews . '</p>';
                        echo '<p><b>First submission:</b> ' . self::date_format_contact_stat($li_contact->history->lead->first_submission) . '</p>';
                        echo '<p><b>Last submission:</b> ' . self::date_format_contact_stat($li_contact->history->lead->last_submission) . '</p>';
                        echo '<p><b>Total submissions:</b> ' . $li_contact->history->lead->total_submissions . '</p>';
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
        <div class="leadin-contacts">

            <form id="leadin-contacts-search" class="leadin-contacts__search" method="GET">
                <span class="table_search">
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                    <input type="search" id="leadin-contact-search-input" name="s" value="<?php echo print_submission_val('s')?>"/>
                    <input type="submit" name="" id="leadin-search-submit" class="button" value="Search all contacts">
                </span>
            </form>

            <?php

                $this->leadin_header('LeadIn Contacts', 'leadin-contacts__header');
            ?>

            <div class="leadin-contacts__nav">
                <?php $leadinListTable->views(); ?>
            </div>
            
            <div class="leadin-contacts__content">

                <div class="leadin-contacts__filter">
                    <?php $leadinListTable->filters(); ?>
                </div>

                <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
                <form id="leadin-contacts" method="GET">
                    
                    <!-- For plugins, we also need to ensure that the form posts back to our current page -->
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

                    <div class="leadin-contacts__table">
                        <!-- Now we can render the completed list table -->
                        <?php $leadinListTable->display() ?>
                    </div>
                
                </form>
                
            </div>

            <?php
                $export_button_labels = $leadinListTable->view_label;

                if ( isset($_GET['filter_action']) || isset($_GET['filter_content']) )
                    $export_button_labels = 'Filtered Contacts';
            ?>

            <form id="export-form" class="leadin-contacts__export-form" name="export-form" method="POST">
                <input type="submit" value="<?php esc_attr_e('Export All ' . $export_button_labels ); ?>" name="export-all" id="leadin-export-leads" class="button" <?php echo ( ! count($leadinListTable->data) ? 'disabled' : '' ); ?>>
                <input type="submit" value="<?php esc_attr_e('Export Selected ' . $export_button_labels ); ?>" name="export-selected" id="leadin-export-selected-leads" class="button" disabled>
                <input type="hidden" id="leadin-selected-contacts" name="leadin-selected-contacts" value=""/>
            </form>

        </div>

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

    /**
     * Updates the contact status
     *
     * @param   int
     * @param   string
     * @return  bool
     */
    function update_contact_status ( $lead_id, $contact_status )
    {
        global $wpdb;
        
        $q = $wpdb->prepare("UPDATE li_leads SET lead_status = %s WHERE lead_id = %d", $contact_status, $lead_id);
        $result = $wpdb->query($q);

        return $result;
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

        if ( ($pagenow == 'admin.php' && isset($_GET['page']) && strstr($_GET['page'], 'leadin')) ) 
        {
            wp_register_script('leadin-admin-js', LEADIN_PATH . '/assets/js/build/leadin-admin.min.js', array ( 'jquery' ), FALSE, TRUE);
            wp_enqueue_script('leadin-admin-js');
            wp_localize_script('leadin-admin-js', 'li_admin_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
        }

        if ( $pagenow == 'post.php' && isset($_GET['post']) && isset($_GET['action']) && strstr($_GET['action'], 'edit') )
        {
            wp_register_script('leadin-lazyload', LEADIN_PATH . '/assets/js/build/leadin-lazyload.min.js', array ( 'jquery' ), FALSE, TRUE);
            wp_enqueue_script('leadin-lazyload');
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
        return date('M j, Y g:ia', strtotime($timestamp));
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
        'lead_email', 'lead_source', 'lead_status', 'visits', 'lead_pageviews', 'lead_form_submissions', 'last_visit', 'lead_date'
    );

    $headers = array();
    foreach ( $column_headers as $key => $field )
    {
            $headers[] = '"' . $field . '"';
    }
    echo implode(',', $headers) . "\n";

    $mysql_search_filter = '';

    // search filter
    if ( isset($_GET['s']) )
    {
        $search_query = $_GET['s'];
        $mysql_search_filter = $wpdb->prepare(" AND ( l.lead_email LIKE '%%%s%%' OR l.lead_source LIKE '%%%s%%' ) ", like_escape($search_query), like_escape($search_query));
    }

    // contact type filter
    if ( isset($_GET['contact_type']) )
    {
        $mysql_contact_type_filter = $wpdb->prepare("AND l.lead_status = %s ", $_GET['contact_type']);
    }
    else 
    {
        $mysql_contact_type_filter = " AND ( l.lead_status = 'lead' OR l.lead_status = 'comment' OR l.lead_status = 'subscribe') ";
    }

    // filter for visiting a specific page
    if ( isset($_GET['filter_action']) && $_GET['filter_action'] == 'visited' )
    {
        $q = $wpdb->prepare("SELECT lead_hashkey FROM li_pageviews WHERE pageview_title LIKE '%%%s%%' GROUP BY lead_hashkey",  htmlspecialchars(urldecode($_GET['filter_content'])));
        $filtered_contacts = $wpdb->get_results($q);

        if ( count($filtered_contacts) )
        {
            $filtered_hashkeys = '';
            for ( $i = 0; $i < count($filtered_contacts); $i++ )
                $filtered_hashkeys .= "'" . $filtered_contacts[$i]->lead_hashkey . "'" . ( $i != (count($filtered_contacts) - 1) ? ', ' : '' );
        
            $mysql_search_filter = " AND l.hashkey IN ( " . $filtered_hashkeys . " ) ";
        }
    }
    
    // filter for a form submitted on a specific page
    if ( isset($_GET['filter_action']) && $_GET['filter_action'] == 'submitted' )
    {
        $q = $wpdb->prepare("SELECT lead_hashkey FROM li_submissions WHERE form_page_title LIKE '%%%s%%' GROUP BY lead_hashkey", htmlspecialchars(urldecode($_GET['filter_content'])));
        $filtered_contacts = $wpdb->get_results($q);

        $filtered_hashkeys = '';
        for ( $i = 0; $i < count($filtered_contacts); $i++ )
            $filtered_hashkeys .= "'" . $filtered_contacts[$i]->lead_hashkey . "'" . ( $i != (count($filtered_contacts) - 1) ? ', ' : '' );
    
        $mysql_search_filter = " AND l.hashkey IN ( " . $filtered_hashkeys . " ) ";
    }

    $q =  $wpdb->prepare("
        SELECT 
            l.lead_id AS lead_id, 
            LOWER(DATE_FORMAT(l.lead_date, %s)) AS lead_date, l.lead_ip, l.lead_source, l.lead_email, l.lead_status, l.hashkey,
            COUNT(DISTINCT s.form_id) AS lead_form_submissions,
            COUNT(DISTINCT p.pageview_id) AS lead_pageviews,
            LOWER(DATE_FORMAT(MAX(p.pageview_date), %s)) AS last_visit,
            ( SELECT COUNT(DISTINCT pageview_id) FROM li_pageviews WHERE lead_hashkey = l.hashkey AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS visits,
            ( SELECT MAX(pageview_source) AS pageview_source FROM li_pageviews WHERE lead_hashkey = l.hashkey AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS pageview_source 
        FROM 
            li_leads l
        LEFT JOIN li_submissions s ON l.hashkey = s.lead_hashkey
        LEFT JOIN li_pageviews p ON l.hashkey = p.lead_hashkey 
        WHERE l.lead_email != '' AND l.lead_deleted = 0 " .
        ( isset ($_POST['export-selected']) ? " AND l.lead_id IN ( " . $_POST['leadin-selected-contacts'] . " ) " : "" ), '%Y/%m/%d %l:%i%p', '%Y/%m/%d %l:%i%p');

    $q .= $mysql_contact_type_filter;
    $q .= ( $mysql_search_filter ? $mysql_search_filter : "" );
    $q .=  " GROUP BY l.lead_email";

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
