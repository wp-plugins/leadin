<?php

//=============================================
// Include Needed Files
//=============================================

if ( !class_exists('WP_List_Table') )
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

require_once(LEADIN_PLUGIN_DIR . '/inc/leadin-functions.php');

//=============================================
// LI_List_Table Class
//=============================================
class LI_List_Table extends WP_List_Table {
    
    /**
     * Variables
     */
    public $data = array();

    /**
     * Class constructor
     */
    function __construct () 
    {
        global $status, $page;
                
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'contact',
            'plural'    => 'contacts',
            'ajax'      => false
        ));
    }

    /**
     * Prints text for no rows found in table
     */
    function no_items () 
    {
      _e('No contacts found.');
    }
    
    /**
     * Prints values for columns for which no column function has been defined
     *
     * @param   object
     * @param   string
     * @return  *           item value's type
     */
    function column_default ( $item, $column_name )
    {
        switch ( $column_name ) 
        {
            case 'email':

            case 'status':
                return $item[$column_name];
            case 'date':
                return $item[$column_name];
            case 'last_visit':
                return $item[$column_name];
            case 'submissions':
                return $item[$column_name];
            case 'pageviews':
                return $item[$column_name];
            case 'visits':
                return $item[$column_name];
            case 'source':
                return $item[$column_name];
            default:
                return print_r($item,true);
        }
    }
    
    /**
     * Prints text for email column
     *
     * @param   object
     * @return  string
     */
    function column_email ( $item )
    {
        //Build row actions
        $actions = array(
            'view'    => sprintf('<div style="clear:both;"></div><a href="?page=%s&action=%s&lead=%s">View</a>',$_REQUEST['page'],'view',$item['ID']),
            'delete'  => sprintf('<a href="?page=%s&action=%s&lead=%s">Delete</a>',$_REQUEST['page'],'delete',$item['ID'])
        );
        
        //Return the title contents
        return sprintf('%1$s<br/>%2$s',
            /*$1%s*/ $item['email'],
            /*$2%s*/ $this->row_actions($actions)
        );
    }
    
    /**
     * Prints checkbox column
     *
     * @param   object
     * @return  string
     */
    function column_cb ( $item )
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],
            /*$2%s*/ $item['ID']
        );
    }
    
    /**
     * Get all the columns for the list table
     *
     * @param   object
     * @param   string
     * @return  array           associative array of columns
     */
    function get_columns () 
    {
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'email'         => 'Email',
            'source'        => 'Original source',
            'status'        => 'Status',
            'visits'        => 'Visits',
            'pageviews'     => 'Page views',
            'submissions'   => 'Forms',
            'last_visit'    => 'Last visit',
            'date'          => 'Created on'
        );
        return $columns;
    }
    
    /**
     * Defines sortable columns for table
     *
     * @param   object
     * @param   string
     * @return  array           associative array of columns
     */
    function get_sortable_columns () 
    {
        $sortable_columns = array(
            'email'         => array('email',false),     //true means it's already sorted
            'status'        => array('status',false),
            'pageviews'     => array('pageviews',false),
            'visits'        => array('visits',false),
            'submissions'   => array('submissions',false),
            'date'          => array('date',true),
            'last_visit'    => array('last_visit',false),
            'source'        => array('source',false)
        );
        return $sortable_columns;
    }
    
    /**
     * Get the bulk actions
     *
     * @return  array           associative array of actions
     */
    function get_bulk_actions ()
    {
        $actions = array(
            'delete'    => 'Delete'
        );
        return $actions;
    }
    
    /**
     * Process bulk actions for deleting
     */
    function process_bulk_action ()
    {
        global $wpdb;
        $ids_to_delete = '';
        $hashes_to_delete = '';

        if ( isset ($_GET['contact']) )
        {
            for ( $i = 0; $i < count($_GET['contact']); $i++ )
            {
               $ids_to_delete .= $_GET['contact'][$i];;

               if ( $i != (count($_GET['contact'])-1) )
                    $ids_to_delete .= ',';
            }

           $q = $wpdb->prepare("SELECT hashkey FROM li_leads WHERE lead_id IN ( " . $ids_to_delete . " )", $ids_to_delete);
           $hashes = $wpdb->get_results($q);

            for ( $i = 0; $i < count($hashes); $i++ )
            {

                 $hashes_to_delete .= "'". $hashes[$i]->hashkey. "'";

               if ( $i != (count($hashes)-1) )
                    $hashes_to_delete .= ",";
            }

            //Detect when a bulk action is being triggered...
            if( 'delete' === $this->current_action() )
            {
                $q = $wpdb->prepare("DELETE FROM li_pageviews WHERE lead_hashkey IN (" . $hashes_to_delete . ")", "");
                $delete_pageviews = $wpdb->query($q);

                $q = $wpdb->prepare("DELETE FROM li_submissions WHERE lead_hashkey IN (" . $hashes_to_delete . ")", "");
                $delete_submissions = $wpdb->query($q);

                $q = $wpdb->prepare("DELETE FROM li_leads WHERE lead_id IN (" . $ids_to_delete . ")", "");
                $delete_leads = $wpdb->query($q);
            }
        }
        
    }

    /**
     * Get the leads for the contacts table based on $GET_['contact_type'] or $_GET['s'] (search)
     *
     * @return  array           associative array of all contacts
     */
    function get_leads ()
    {
        global $wpdb;

        $mysql_search_filter = '';

        if ( isset($_GET['s']) )
        {
            $search_query = $_GET['s'];
            $mysql_search_filter = $wpdb->prepare(" AND ( l.lead_email LIKE '%%%s%%' OR l.lead_source LIKE '%%%s%%' ) ", like_escape($search_query), like_escape($search_query));
        }

        if ( isset($_GET['contact_type']) )
        {
            $mysql_contact_type_filter = $wpdb->prepare("AND l.lead_status = %s ", $_GET['contact_type']);
        }
        else 
        {
            $mysql_contact_type_filter = " AND ( l.lead_status = 'lead' OR l.lead_status = 'comment' OR l.lead_status = 'subscribe') ";
        }

        $q =  $wpdb->prepare("
            SELECT 
                l.lead_id AS lead_id, 
                LOWER(DATE_FORMAT(l.lead_date, %s)) AS lead_date, l.lead_ip, l.lead_source, l.lead_email, l.lead_status, l.hashkey,
                COUNT(DISTINCT s.form_id) AS lead_form_submissions,
                COUNT(DISTINCT p.pageview_id) AS lead_pageviews,
                LOWER(DATE_FORMAT(MAX(p.pageview_date), %s)) AS last_visit
            FROM 
                li_leads l
            LEFT JOIN li_submissions s ON l.hashkey = s.lead_hashkey
            LEFT JOIN li_pageviews p ON l.hashkey = p.lead_hashkey 
            WHERE l.lead_email != ''", '%Y/%m/%d %l:%i%p', '%Y/%m/%d %l:%i%p');

        $q .= $mysql_contact_type_filter;
        $q .= ( $mysql_search_filter ? $mysql_search_filter : "" );
        $q .=  " GROUP BY l.lead_email";

        $leads = $wpdb->get_results($q);
        
        $all_leads = array();

        foreach ( $leads as  $lead ) 
        {
            $q = $wpdb->prepare("SELECT COUNT(DISTINCT pageview_id) FROM li_pageviews WHERE lead_hashkey = %s AND pageview_session_start = 1", $lead->hashkey);
            $pageviews = $wpdb->get_var($q);
            $lead->lead_visits = $pageviews;

            $lead_status = 'Lead';

            if ( $lead->lead_status == 'subscribe' )
                $lead_status = 'Subscriber';
            else if ( $lead->lead_status == 'comment' )
                $lead_status = 'Commenter';

            $lead_array = array(
                'ID' => $lead->lead_id,
                'email' => sprintf('<a href="?page=%s&action=%s&lead=%s">' . "<img class='pull-left leadin-contact-avatar' src='https://app.getsignals.com/avatar/image/?emails=" . $lead->lead_email . "' width='35' height='35'/> " . '</a>', $_REQUEST['page'], 'view', $lead->lead_id) .  sprintf('<a href="?page=%s&action=%s&lead=%s"><b>' . $lead->lead_email . '</b></a>', $_REQUEST['page'], 'view', $lead->lead_id),
                'status' => $lead_status,
                'visits' => ( !$lead->lead_visits ? 1 : $lead->lead_visits ),
                'submissions' => $lead->lead_form_submissions,
                'pageviews' => $lead->lead_pageviews,
                'date' => $lead->lead_date,
                'last_visit' => $lead->last_visit,
                'source' => ( $lead->lead_source ? "<a title='Visit page' href='" . $lead->lead_source . "' target='_blank'>" . $lead->lead_source . "</a>" : 'Direct' )
            );

            array_push($all_leads, $lead_array);
        }

        return $all_leads;
    }

    /**
     * Gets the total number of contacts, comments and subscribers for above the table
     */
    function get_contact_type_totals ()
    {
        global $wpdb;
        // @TODO Need to select distinct emails
        $q = $wpdb->prepare("
            SELECT 
                COUNT(DISTINCT lead_email) AS total_contacts,
                ( SELECT COUNT(DISTINCT lead_email) FROM li_leads WHERE lead_status = 'lead' AND lead_email != '' ) AS total_leads,
                ( SELECT COUNT(DISTINCT lead_email) FROM li_leads WHERE lead_status = 'comment' AND lead_email != '' ) AS total_comments,
                ( SELECT COUNT(DISTINCT lead_email) FROM li_leads WHERE lead_status = 'subscribe' AND lead_email != '' ) AS total_subscribes
            FROM 
                li_leads
            WHERE
                lead_email != ''", "");

        $totals = $wpdb->get_row($q);
        return $totals;
    }

    /**
     * Gets the current view based off $_GET['contact_type']
     *
     * @return  string
     */
    function get_view ()
    {
        $current = ( !empty($_GET['contact_type']) ? html_entity_decode($_GET['contact_type']) : 'all' );
        return $current;
    }
    
    /**
     * Get the view menus above the contacts table
     *
     * @return  string
     */
    function get_views ()
    {
       $views = array();
       $totals = $this->get_contact_type_totals();

       $current = ( !empty($_GET['contact_type']) ? html_entity_decode($_GET['contact_type']) : 'all' );

       // All link
       $class = ( $current == 'all' ? ' class="current"' :'' );
       $all_url = remove_query_arg('contact_type');
       $views['all'] = "<a href='{$all_url }' {$class} >" . ( $totals->total_leads + $totals->total_comments + $totals->total_subscribes ) .  " total</a>";

       // Leads link
       $leads_url = add_query_arg('contact_type','lead');
       $class = ( $current == 'lead' ? ' class="current"' :'' );
       $views['contacts'] = "<a href='{$leads_url}' {$class} >" . leadin_single_plural_label($totals->total_leads, 'lead', 'leads') .  "</a>";

       // Commenters link
       $comments_url = add_query_arg('contact_type','comment');
       $class = ( $current == 'comment' ? ' class="current"' :'' );
       $views['commenters'] = "<a href='{$comments_url}' {$class} >" . leadin_single_plural_label($totals->total_comments, 'commenter', 'commenters') .  "</a>";

       // Commenters link
       $subscribers_url = add_query_arg('contact_type','subscribe');
       $class = ( $current == 'subscribe' ? ' class="current"' :'' );
       $views['subscribe'] = "<a href='{$subscribers_url}' {$class} >" . leadin_single_plural_label($totals->total_subscribes, 'subscriber', 'subscribers') .  "</a>";

       return $views;
    }

    /**
     * Prints contacts menu above contacts table
     */
    function views ()
    {
        $views = $this->get_views();
        $views = apply_filters( 'views_' . $this->screen->id, $views );

        $current_view = $this->get_view();

        if ( $current_view == 'lead' )
            $view_label = 'Leads';
        else if ( $current_view == 'comment' )
            $view_label = 'Commenters';
        else if ( $current_view == 'subscribe' )
            $view_label = 'Subscribers';
        else
            $view_label = 'Contacts';

        if ( empty( $views ) )
            return;

        echo "<div class='top_table_controls'>\n";

            echo "<ul class='table_segment_picker'>\n";
                foreach ( $views as $class => $view ) {
                    $views[ $class ] = "\t<li class='$class'>$view";
                }
                echo implode( "</li>\n", $views ) . "</li>\n";
            echo "</ul>";
            
            echo "<span class='table_search'>\n";
                echo "<label class='screen-reader-text' for='post-search-input'>Search Contacts:</label>";
                echo "<input type='search' id='leadin-contact-search-input' name='s' value='" . print_submission_val('s') . "'/>";
                if ( isset ($_GET['contact_type']) ) {
                    echo "<input type='hidden' name='contact_type' value='" . print_submission_val('contact_type') . "'/>";
                }
                echo "<input type='submit' name='' id='leadin-search-submit' class='button' value='Search " . $view_label . "'>";
            echo "</span>";

        echo "</div>";
    }

    /**
     * Gets + prepares the contacts for the list table
     */
    function prepare_items ()
    {
        global $wpdb;

        $per_page = 10;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->process_bulk_action();
        $this->data = $this->get_leads();;

        $orderby = ( !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'date' );
        $order = ( !empty($_REQUEST['order']) ? $_REQUEST['order'] : 'desc' );

        function usort_reorder($a,$b) 
        {
            $orderby = ( !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'date' );
            $order = ( !empty($_REQUEST['order']) ? $_REQUEST['order'] : 'desc' );

            if ( $a[$orderby] == $b[$orderby] )
                $result = 0;
            else if ( $a[$orderby] < $b[$orderby] )
                $result = -1;
            else
                $result = 1;

            return ( $order === 'asc' ? $result : -$result );
        }

        usort($this->data, 'usort_reorder');
        
        $current_page = $this->get_pagenum();
        $total_items = count($this->data);
        $this->data = array_slice($this->data, (($current_page-1)*$per_page), $per_page);
        $this->items = $this->data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items/$per_page)
        ) );
    }
    
}