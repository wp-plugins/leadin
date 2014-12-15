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
    var $action;

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

        $this->action = $this->leadin_current_action();
        if ( $this->action == 'delete' )
        {
            $lead_id = ( isset($_GET['lead']) ? absint($_GET['lead']) : FALSE );
            $this->delete_lead($lead_id);
        }

        echo '<div id="leadin" class="wrap '. ( $wp_version < 3.8 && !is_plugin_active('mp6/mp6.php')  ? 'pre-mp6' : ''). '">';

            if ( $this->action != 'view' ) {
                $this->leadin_render_list_page();
            }
            else {
                $this->leadin_render_contact_detail($_GET['lead']);
            }


            $this->leadin_footer();

        echo '</div>';
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
        $lead_source = leadin_strip_params_from_url($li_contact->history->lead->lead_source);

        if ( isset($_POST['edit_tags']) )
        {
            $updated_tags = array();

            foreach ( $_POST as $name => $value )
            {
                if ( strstr($name, 'tag_slug_') ) 
                {
                    array_push($updated_tags, $value);
                }
            }

            $li_contact->update_contact_tags($lead_id, $updated_tags);
            $li_contact->history->tags = $li_contact->get_contact_tags($li_contact->hashkey);
        }

        if ( isset($_GET['stats_dashboard']) )
            echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_stats">&larr; Stat Dashboard</a>';
        else
        {
            if ( isset($_GET['redirect_to']) )
            {
                if ( strstr($_GET['redirect_to'], 'contact_type') )
                {
                    $url_parts = parse_url(urldecode($_GET['redirect_to']));
                    parse_str($url_parts['query'], $url_vars);

                    if ( isset($url_vars['contact_type']) && $url_vars['contact_type'] )
                        echo '<a href="' . $_GET['redirect_to'] . '">&larr; All ' . ucwords($url_vars['contact_type']) . '</a>';
                    else
                        echo '<a href="' . $_GET['redirect_to'] . '">&larr; All Contacts</a>';
                }
                else
                    echo '<a href="' . $_GET['redirect_to'] . '">&larr; All Contacts</a>';
                
            }
            else
                echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts">&larr; All Contacts</a>';
        }
        
        echo '<div class="contact-header-wrap">';
            echo '<img class="contact-header-avatar leadin-dynamic-avatar_' . substr($lead_id, -1) . '" height="76px" width="76px" src="https://api.hubapi.com/socialintel/v1/avatars?email=' . $lead_email . '"/>';
            echo '<div class="contact-header-info">';
                echo '<h1 class="contact-name">' . $lead_email . '</h1>';
                echo '<div class="contact-tags">';
                    foreach( $li_contact->history->tags as $tag ) {
                        if ($tag->tag_set)
                            echo '<a class="contact-tag" href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts&contact_type=' . $tag->tag_slug . '"><span class="icon-tag"></span>' . $tag->tag_text . '</a>';
                    }
                    ?>

                    <?php add_thickbox(); ?>
                    <div id="edit-contact-tags" style="display:none;">
                        <h2>Edit Tags - <?php echo $li_contact->history->lead->lead_email; ?></h2>
                        <form id="edit_tags" action="" method="POST">

                            <?php
                            
                            foreach( $li_contact->history->tags as $tag ) 
                            {
                                echo '<p>';
                                    echo '<label for="tag_slug_' . $tag->tag_slug . '">';
                                    echo '<input name="tag_slug_' . $tag->tag_slug . '" type="checkbox" id="tag_slug_' . $tag->tag_slug . '" value="' . $tag->tag_id . '" ' . ( $tag->tag_set ? ' checked' : '' ) . '>' . $tag->tag_text . '</label>';
                                echo '</p>';
                            }

                            ?>

                            <input type="hidden" name="edit_tags" value="1"/>
                            <p class="submit">
                                <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Tags">
                            </p>
                        </form>
                    </div>

                    <a class="thickbox contact-edit-tags" href="#TB_inline?width=400&height=400&inlineId=edit-contact-tags">edit tags</a>

                    <?php

                echo '</div>';
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
                                            $url_parts = parse_url($pageview['pageview_source']);
                                            if ( isset($url_parts['query']) )
                                            {
                                                if ( $url_parts['query'] )
                                                {
                                                    parse_str($url_parts['query'], $url_vars);
                                                    if ( count($url_vars) )
                                                    {
                                                        echo '<ul class="event-detail fields">';
                                                            foreach ( $url_vars as $key => $value )
                                                            {
                                                                if ( ! $value )
                                                                    continue;
                                                                
                                                                echo '<li class="field">';
                                                                    echo '<label class="field-label">' . $key . ':</label>';
                                                                    echo '<p class="field-value">' . nl2br($value) . '</p>';
                                                                echo '</li>';
                                                            }
                                                        echo '</ul>';
                                                    }
                                                }
                                            }
                                            
                                        echo '</div>';
                                    echo '</li>';
                                }
                            }
                            else if ( $event['event_type'] == 'form' )
                            {
                                $submission = $event['activities'][0];
                                $form_fields = json_decode($submission['form_fields']);
                                $num_form_fieds = count($form_fields);
                                $tag_text = '<a class="contact-tag" href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts&contact_type=' . $tag->tag_slug . '">' . $tag->tag_text . '</a>';

                                echo '<li class="event form-submission">';
                                    echo '<div class="event-time">' . date('g:ia', strtotime($submission['event_date'])) . '</div>';
                                    echo '<div class="event-content">';
                                        echo '<p class="event-title">';
                                            echo 'Filled out ' . $event['form_name'] . ' on page <a href="' . $submission['form_page_url'] . '">' . $submission['form_page_title']  . '</a>';
                                            if ( count($event['form_tags']) )
                                            {
                                                echo ' and tagged as ';
                                                for ( $i = 0; $i < count($event['form_tags']); $i++ )
                                                    echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts&contact_type=' . $event['form_tags'][$i]['tag_slug'] . '">' . $event['form_tags'][$i]['tag_text'] . '</a> ';
                                            }
                                        echo '</p>';
                                        echo '<ul class="event-detail fields">';
                                        if ( count($form_fields) )
                                        {
                                            foreach ( $form_fields as $field )
                                            {
                                                echo '<li class="field">';
                                                    echo '<label class="field-label">' . $field->label . ':</label>';
                                                    echo '<p class="field-value">' . nl2br($field->value) . '</p>';
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
                echo '<div class="leadin-meta-section">';
                    echo '<h4 class="leain-meta-header">Tracking Info</h4>';
                    echo '<table class="leadin-meta-table"><tbody>';

                        if ( $li_contact->history->lead->lead_first_name )
                        {
                            echo '<tr>';
                                echo '<th>Name</th>';
                                echo '<td>' . $li_contact->history->lead->lead_first_name . ' ' . $li_contact->history->lead->lead_last_name . '</td>';
                            echo '</tr>';
                        }
                        echo '<tr>';
                            echo '<th>Email</th>';
                            echo '<td> <a href="mailto:' . $lead_email . '">' . $lead_email . '</a></td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th>Original source</th>';
                            echo '<td>' . ( $li_contact->history->lead->lead_source ? '<a href="' . $li_contact->history->lead->lead_source . '">' . $lead_source . '</a>' : 'Direct' ) . '</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th>First visit</th>';
                            echo '<td>' . self::date_format_contact_stat($li_contact->history->lead->first_visit) . '</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th>Pageviews</th>';
                            echo '<td>' . $li_contact->history->lead->total_pageviews . '</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th>Form submissions</th>';
                            echo '<td>' . $li_contact->history->lead->total_submissions . '</td>';
                        echo '</tr>';
                    echo '</tbody></table>';
                echo '</div>'; // leadin-meta-section
                echo '<div class="leadin-meta-section">';
                    echo '<h4 class="leain-meta-header leadin-premium-tag">Social Info</h4>';
                    echo '<table class="leadin-meta-table"><tbody>';
                        echo '<tr>';
                            echo '<td><a href="http://leadin.com/pro-upgrade?utm_campaign=repo_plugin" target="_blank">Upgrade to Leadin Pro for free</a> to get social info</td>';
                        echo '</tr>';
                    echo '</tbody></table>';
                echo '</div>'; // leadin-meta-section
                echo '<div class="leadin-meta-section">';
                    echo '<h4 class="leain-meta-header leadin-premium-tag">Company Info</h4>';
                    echo '<table class="leadin-meta-table"><tbody>';
                        echo '<tr>';
                            echo '<td><a href="http://leadin.com/pro-upgrade?utm_campaign=repo_plugin" target="_blank">Upgrade to Leadin Pro for free</a> to get company info</td>';
                        echo '</tr>';
                    echo '</tbody></table>';
                echo '</div>'; // leadin-meta-section
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

        // Process any bulk actions before the contacts are grabbed from the database
        $leadinListTable->process_bulk_action();
        
        //Fetch, prepare, sort, and filter our data...
        $leadinListTable->data = $leadinListTable->get_contacts();
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

                $this->leadin_header('Leadin Contacts', 'leadin-contacts__header');
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

                    <input type="hidden" name="contact_type" value="<?php echo ( isset($_GET['contact_type']) ? $_GET['contact_type'] : '' ); ?>"/>
                   
                    <?php if ( isset($_GET['filter_content']) ) : ?>
                        <input type="hidden" name="filter_content" value="<?php echo ( isset($_GET['filter_content']) ? stripslashes($_GET['filter_content']) : '' ); ?>"/>
                    <?php endif; ?>

                    <?php if ( isset($_GET['filter_action']) ) : ?>
                        <input type="hidden" name="filter_action" value="<?php echo ( isset($_GET['filter_action']) ? $_GET['filter_action'] : '' ); ?>"/>
                    <?php endif; ?>

                </form>
                
            </div>

            <?php add_thickbox(); ?>
            <div id="bulk-edit-tags" style="display:none;">
                <h2>Select a tag to add to <span class="selected-contacts-count"></span> <?php echo strtolower($leadinListTable->view_label); ?></h2>
                <form id="bulk-edit-tags-form" action="" method="POST">
                    <?php
                    if ( count($leadinListTable->tags) ) 
                    {
                        echo '<select name="bulk_selected_tag">';
                            foreach( $leadinListTable->tags as $tag )
                                echo '<option value="' . $tag->tag_slug . '">' . $tag->tag_text . '</option>';
                        echo '</select>';
                    }
                    ?>

                    <input type="hidden" name="bulk_edit_tags" value="1"/>
                    <input type="hidden" id="bulk-edit-tag-action" name="bulk_edit_tag_action" value=""/>
                    <input type="hidden" class="leadin-selected-contacts"  name="leadin_selected_contacts" value=""/>

                    <p class="submit">
                        <input id="bulk-edit-button" type="submit" name="submit" id="submit" class="button button-primary" value="Add Tag">
                    </p>
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
                <input type="hidden" class="leadin-selected-contacts"  name="leadin_selected_contacts" value=""/>
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

        $q = $wpdb->prepare("SELECT hashkey FROM $wpdb->li_leads WHERE lead_id = %d", $lead_id);
        $lead_hash = $wpdb->get_var($q);

        $q = $wpdb->prepare("UPDATE $wpdb->li_pageviews SET pageview_deleted = 1 WHERE lead_hashkey = %s AND pageview_deleted = 0", $lead_hash);
        $delete_pageviews = $wpdb->query($q);

        $q = $wpdb->prepare("UPDATE $wpdb->li_submissions SET form_deleted = 1  WHERE lead_hashkey = %s AND form_deleted = 0", $lead_hash);
        $delete_submissions = $wpdb->query($q);

        $q = $wpdb->prepare("UPDATE $wpdb->li_leads SET lead_deleted = 1 WHERE lead_id = %d AND lead_deleted = 0", $lead_id);
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

        if ( ($pagenow == 'admin.php' && isset($_GET['page']) && strstr($_GET['page'], 'leadin')) ) 
        {
            wp_register_script('leadin-admin-js', LEADIN_PATH . '/assets/js/build/leadin-admin.min.js', array ( 'jquery' ), FALSE, TRUE);
            wp_enqueue_script('leadin-admin-js');
            wp_localize_script('leadin-admin-js', 'li_admin_ajax', array('ajax_url' => get_admin_url(NULL,'') . '/admin-ajax.php'));
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
    leadin_set_wpdb_tables();
    leadin_set_mysql_timezone_offset();

    $sitename = sanitize_key(get_bloginfo('name'));

    if ( ! empty($sitename) )
        $sitename .= '.';

    $filename = $sitename . '.contacts.' . date('Y-m-d-H-i-s') . '.csv';

    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Content-Type: text/csv; charset=' . get_option('blog_charset'), TRUE);

    $column_headers = array(
        'Email', 'First Name', 'Last Name', 'Original source', 'Visits', 'Page views', 'Forms',  'Last visit', 'Created on'
    );

    $fields = array(
        'lead_email', 'lead_first_name', 'lead_last_name', 'lead_source', 'visits', 'lead_pageviews', 'lead_form_submissions', 'last_visit', 'lead_date'
    );

    $headers = array();
    foreach ( $column_headers as $key => $field )
    {
            $headers[] = '"' . $field . '"';
    }
    echo implode(',', $headers) . "\n";

    $mysql_search_filter        = '';
    $mysql_contact_type_filter  = '';
    $mysql_action_filter        = '';
    $filter_action_set          = FALSE;

    // search filter
    if ( isset($_GET['s']) )
    {
        $search_query = $_GET['s'];
        $mysql_search_filter = $wpdb->prepare(" AND ( l.lead_email LIKE '%%%s%%' OR l.lead_source LIKE '%%%s%%' ) ", $wpdb->esc_like($search_query), $wpdb->esc_like($search_query));
    }

    // @TODO - need to modify the filters to pull down the form ID types
    
    $filtered_contacts = array();

    // contact type filter
    if ( isset($_GET['contact_type']) )
    {
        // Query for the tag_id, then find all hashkeys with that tag ID tied to them. Use those hashkeys to modify the query
        $q = $wpdb->prepare("
            SELECT 
                DISTINCT ltr.contact_hashkey as lead_hashkey 
            FROM 
                $wpdb->li_tag_relationships ltr, $wpdb->li_tags lt 
            WHERE 
                lt.tag_id = ltr.tag_id AND 
                ltr.tag_relationship_deleted = 0 AND  
                lt.tag_slug = %s GROUP BY ltr.contact_hashkey",  $_GET['contact_type']);

        $filtered_contacts = $wpdb->get_results($q, 'ARRAY_A');
        $num_contacts = count($filtered_contacts);
    }

    if ( isset($_GET['filter_action']) && $_GET['filter_action'] == 'visited' )
    {
        if ( isset($_GET['filter_content']) && $_GET['filter_content'] != 'any page' )
        {
            $q = $wpdb->prepare("SELECT lead_hashkey FROM $wpdb->li_pageviews WHERE pageview_title LIKE '%%%s%%' GROUP BY lead_hashkey",  htmlspecialchars(urldecode($_GET['filter_content'])));
            $filtered_contacts = leadin_merge_filtered_contacts($wpdb->get_results($q, 'ARRAY_A'), $filtered_contacts);
            $filter_action_set = TRUE;
        }
    }
    
    // filter for a form submitted on a specific page
    if ( isset($_GET['filter_action']) && $_GET['filter_action'] == 'submitted' )
    {
        $filter_form = '';
        if ( isset($_GET['filter_form']) && $_GET['filter_form'] && $_GET['filter_form'] != 'any form' )
        {
            $filter_form = str_replace(array('#', '.'), '', htmlspecialchars(urldecode($_GET['filter_form'])));
            $filter_form_query = $wpdb->prepare(" AND ( form_selector_id LIKE '%%%s%%' OR form_selector_classes LIKE '%%%s%%' )", $filter_form, $filter_form);
        }

        $q = $wpdb->prepare("SELECT lead_hashkey FROM $wpdb->li_submissions WHERE form_page_title LIKE '%%%s%%' ", ( $_GET['filter_content'] != 'any page' ? htmlspecialchars(urldecode($_GET['filter_content'])): '' ));
        $q .= ( $filter_form_query ? $filter_form_query : '' );
        $q .= " GROUP BY lead_hashkey";
        $filtered_contacts = leadin_merge_filtered_contacts($wpdb->get_results($q, 'ARRAY_A'), $filtered_contacts);
        $filter_action_set = TRUE;
    }        

    $filtered_hashkeys = leadin_explode_filtered_contacts($filtered_contacts);

    $mysql_action_filter = '';
    if ( $filter_action_set ) // If a filter action is set and there are no contacts, do a blank
        $mysql_action_filter = " AND l.hashkey IN ( " . ( $filtered_hashkeys ? $filtered_hashkeys : "''" ) . " ) ";
    else
        $mysql_action_filter = ( $filtered_hashkeys ? " AND l.hashkey IN ( " . $filtered_hashkeys . " ) " : '' ); // If a filter action isn't set, use the filtered hashkeys if they exist, else, don't include the statement

    // There's a filter and leads are in it
    if ( ( isset($_GET['contact_type']) && $num_contacts ) || ! isset($_GET['contact_type']) )
    {
        $q =  $wpdb->prepare("
            SELECT 
                l.lead_id AS lead_id, 
                LOWER(DATE_SUB(l.lead_date, INTERVAL %d HOUR)) AS lead_date, l.lead_ip, l.lead_source, l.lead_email, l.hashkey, l.lead_first_name, l.lead_last_name,
                COUNT(DISTINCT s.form_id) AS lead_form_submissions,
                COUNT(DISTINCT p.pageview_id) AS lead_pageviews,
                LOWER(DATE_SUB(MAX(p.pageview_date), INTERVAL %d HOUR)) AS last_visit,
                ( SELECT COUNT(DISTINCT pageview_id) FROM $wpdb->li_pageviews WHERE lead_hashkey = l.hashkey AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS visits,
                ( SELECT MIN(pageview_source) AS pageview_source FROM $wpdb->li_pageviews WHERE lead_hashkey = l.hashkey AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS pageview_source 
            FROM 
                $wpdb->li_leads l
            LEFT JOIN $wpdb->li_submissions s ON l.hashkey = s.lead_hashkey
            LEFT JOIN $wpdb->li_pageviews p ON l.hashkey = p.lead_hashkey 
            WHERE l.lead_email != '' AND l.lead_deleted = 0 AND l.hashkey != '' " .
            ( isset ($_POST['export-selected']) ? " AND l.lead_id IN ( " . $_POST['leadin_selected_contacts'] . " ) " : "" ), $wpdb->db_hour_offset, $wpdb->db_hour_offset);

        $q .= $mysql_contact_type_filter;
        $q .= ( $mysql_search_filter ? $mysql_search_filter : "" );
        $q .= ( $mysql_action_filter ? $mysql_action_filter : "" );
        $q .=  " GROUP BY l.hashkey";
        $leads = $wpdb->get_results($q);
    }
    else
    {
        $leads = array();
    }

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
