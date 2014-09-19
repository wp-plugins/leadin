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
                    $synced_list = '';
                    if ( strstr($name, '_mailchimp') )
                        $synced_list = array('esp' => 'mailchimp', 'list_id' => str_replace('email_connect_mailchimp_', '', $name), 'list_name' => $value);
                    else if ( strstr($name, '_constant_contact') )
                        $synced_list = array('esp' => 'constant_contact', 'list_id' => str_replace('email_connect_constant_contact_', '', $name), 'list_name' => $value);

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

            if ( $this->action == 'manage_tags' || $this->action == 'delete_tag' ) {
                leadin_track_plugin_activity("Loaded Tag List");
                $this->leadin_render_tag_list_page();
            }
            else if ( $this->action == 'edit_tag' || $this->action == 'add_tag' ) {
                leadin_track_plugin_activity("Loaded Tag Editor");
                $this->leadin_render_tag_editor();
            }
            else if ( $this->action != 'view' ) {
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

        if ( isset($_GET['post_id']) )
            echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/post.php?post=' . $_GET['post_id'] . '&action=edit#li_analytics-meta">&larr; All Viewers</a>';
        else if ( isset($_GET['stats_dashboard']) )
            echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_stats">&larr; Stat Dashboard</a>';
        else
        {
            if ( isset($_GET['redirect_to']) )
            {
                if ( strstr($_GET['redirect_to'], 'contact_type') )
                {
                    $url_parts = parse_url(urldecode($_GET['redirect_to']));
                    parse_str($url_parts['query'], $url_vars);

                    if ( isset($url_vars['contact_type']) )
                        echo '<a href="' . $_GET['redirect_to'] . '">&larr; All ' . ucwords($url_vars['contact_type']) . '</a>';
                }
                else
                {
                    echo '<a href="' . $_GET['redirect_to'] . '">&larr; All Contacts</a>';
                }
                
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
                                        if ( $url_parts['query'] )
                                        {
                                            parse_str($url_parts['query'], $url_vars);
                                            if ( count($url_vars) )
                                            {
                                                echo '<ul class="event-detail fields">';
                                                    foreach ( $url_vars as $key => $value )
                                                    {
                                                        echo '<li class="field">';
                                                            echo '<label class="field-label">' . $key . ':</label>';
                                                            echo '<p class="field-value">' . nl2br($value) . '</p>';
                                                        echo '</li>';
                                                    }
                                                echo '</ul>';
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
            echo '<div class="col-wrap">';
                echo '<div class="contact-info leadin-postbox">';
                    echo '<div class="leadin-postbox__content">';
                        echo '<p><b>Email:</b> <a href="mailto:' . $lead_email . '">' . $lead_email . '</a></p>';
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
                
                echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts&action=manage_tags">&larr; Manage tags</a>';
                $this->leadin_header(( $this->action == 'edit_tag' ? 'Edit a tag' : 'Add a tag' ), 'leadin-contacts__header');
            ?>

            <div class="">
                <form id="leadin-tag-settings" action="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts&action=manage_tags'; ?>" method="POST">

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
                                        echo '<th scope="row">Push tagged contacts with these ' . $power_up_name . ' lists</th>';
                                        echo '<td>';
                                            echo '<fieldset>';
                                                echo '<legend class="screen-reader-text"><span>Push tagged contacts to with these ' . $power_up_name . ' email lists</span></legend>';
                                                //
                                                $esp_name_readable = ucwords(str_replace('_', ' ', $esp_name));
                                                $esp_url = str_replace('_', '', $esp_name) . '.com';

                                                switch ( $esp_name ) 
                                                {
                                                    case 'mailchimp' :
                                                        $esp_list_url = 'http://admin.mailchimp.com/lists/new-list/';
                                                        $settings_page_anchor_id = '#li_mls_api_key';
                                                    break;

                                                    case 'constant_contact' :
                                                        $esp_list_url = 'https://login.constantcontact.com/login/login.sdo?goto=https://ui.constantcontact.com/rnavmap/distui/contacts';
                                                        $settings_page_anchor_id = '#li_cc_email';
                                                    break;

                                                    default:
                                                        $esp_list_url = '';
                                                        $settings_page_anchor_id = '';
                                                    break;
                                                }

                                                if ( ! ${'leadin_' . $power_up_slug . '_wp'}->admin->authed )
                                                {
                                                    echo 'It looks like you haven\'t setup your ' . $esp_name_readable . ' integration yet...<br/><br/>';
                                                    echo '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_settings' . $settings_page_anchor_id . '">Setup your ' . $esp_name_readable . ' integration</a>';
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
                                                            $key = leadin_array_search_deep($list_id, $synced_lists, 'list_id');

                                                            if ( isset($key) )
                                                            {
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
                                                    echo 'It looks like you don\'t have any ' . $esp_name_readable . 'lists yet...<br/><br/>';
                                                    echo '<a href="' . $esp_list_url . '" target="_blank">Create a list on ' . $esp_url . '.com</a>';
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
                $this->leadin_header('Manage Leadin Tags <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts&action=add_tag" class="add-new-h2">Add New</a>', 'leadin-contacts__header');
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
    leadin_set_wpdb_tables();

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
        'lead_email', 'lead_source', 'visits', 'lead_pageviews', 'lead_form_submissions', 'last_visit', 'lead_date'
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
        $mysql_search_filter = $wpdb->prepare(" AND ( l.lead_email LIKE '%%%s%%' OR l.lead_source LIKE '%%%s%%' ) ", like_escape($search_query), like_escape($search_query));
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
                LOWER(DATE_FORMAT(l.lead_date, %s)) AS lead_date, l.lead_ip, l.lead_source, l.lead_email, l.hashkey,
                COUNT(DISTINCT s.form_id) AS lead_form_submissions,
                COUNT(DISTINCT p.pageview_id) AS lead_pageviews,
                LOWER(DATE_FORMAT(MAX(p.pageview_date), %s)) AS last_visit,
                ( SELECT COUNT(DISTINCT pageview_id) FROM $wpdb->li_pageviews WHERE lead_hashkey = l.hashkey AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS visits,
                ( SELECT MAX(pageview_source) AS pageview_source FROM $wpdb->li_pageviews WHERE lead_hashkey = l.hashkey AND pageview_session_start = 1 AND pageview_deleted = 0 ) AS pageview_source 
            FROM 
                $wpdb->li_leads l
            LEFT JOIN $wpdb->li_submissions s ON l.hashkey = s.lead_hashkey
            LEFT JOIN $wpdb->li_pageviews p ON l.hashkey = p.lead_hashkey 
            WHERE l.lead_email != '' AND l.lead_deleted = 0 " .
            ( isset ($_POST['export-selected']) ? " AND l.lead_id IN ( " . $_POST['leadin_selected_contacts'] . " ) " : "" ) . 
            " AND l.hashkey != '' ", '%Y/%m/%d %l:%i%p', '%Y/%m/%d %l:%i%p');

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
