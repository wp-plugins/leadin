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
    private $current_view;
    public $view_label;
    private $view_count;
    private $views;
    private $totals;
    private $total_filtered;

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

            $q = $wpdb->prepare("SELECT hashkey FROM li_leads WHERE lead_id IN ( " . $ids_to_delete . " )", "");
            $hashes = $wpdb->get_results($q);

            if ( count($hashes) )
            {
                for ( $i = 0; $i < count($hashes); $i++ )
                {
                     $hashes_to_delete .= "'". $hashes[$i]->hashkey. "'";

                   if ( $i != (count($hashes)-1) )
                        $hashes_to_delete .= ",";
                }

                //Detect when a bulk action is being triggered...
                if( 'delete' === $this->current_action() )
                {
                    $q = $wpdb->prepare("UPDATE li_pageviews SET pageview_deleted  = 1 WHERE lead_hashkey IN (" . $hashes_to_delete . ")", "");
                    $delete_pageviews = $wpdb->query($q);

                    $q = $wpdb->prepare("UPDATE li_submissions SET form_deleted  = 1 WHERE lead_hashkey IN (" . $hashes_to_delete . ")", "");
                    $delete_submissions = $wpdb->query($q);

                    $q = $wpdb->prepare("UPDATE li_leads SET lead_deleted  = 1 WHERE lead_id IN (" . $ids_to_delete . ")", "");
                    $delete_leads = $wpdb->query($q);
                }
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
        /*** 
            == FILTER ARGS ==
            - &visited          = visited a specific page url
            - &num_pageviews    = visited at least # pages
            - &submitted        = submitted a form on specific page url
        */

        global $wpdb;

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

            if ( count($filtered_contacts) )
            {
                $filtered_hashkeys = '';
                for ( $i = 0; $i < count($filtered_contacts); $i++ )
                    $filtered_hashkeys .= "'" . $filtered_contacts[$i]->lead_hashkey . "'" . ( $i != (count($filtered_contacts) - 1) ? ', ' : '' );
            
                $mysql_search_filter = " AND l.hashkey IN ( " . $filtered_hashkeys . " ) ";
            }
        }

        if ( ( isset($_GET['filter_action']) && $mysql_search_filter ) || !isset($_GET['filter_action']) )
        {
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
                WHERE l.lead_email != '' AND l.lead_deleted = 0 ", '%Y/%m/%d %l:%i%p', '%Y/%m/%d %l:%i%p');

            $q .= $mysql_contact_type_filter;
            $q .= ( $mysql_search_filter ? $mysql_search_filter : "" );
            $q .=  " GROUP BY l.lead_email";

            $leads = $wpdb->get_results($q);
        }
        else
        {
            $leads = array();
        }

        $all_leads = array();

        $contact_count = 0;

        if ( count($leads) )
        {
            foreach ( $leads as $key => $lead ) 
            {
                $lead_status = 'Lead';

                if ( $lead->lead_status == 'subscribe' )
                    $lead_status = 'Subscriber';
                else if ( $lead->lead_status == 'comment' )
                    $lead_status = 'Commenter';

                // filter for number of page views and skipping lead if it doesn't meet the minimum
                if ( isset($_GET['filter_action']) && $_GET['filter_action'] == 'num_pageviews' )
                {
                    if ( $lead->lead_pageviews < $_GET['filter_content'] )
                        continue;
                }

                $url = leadin_strip_params_from_url($lead->lead_source);

                $lead_array = array(
                    'ID' => $lead->lead_id,
                    'hashkey' => $lead->hashkey,
                    'email' => sprintf('<a href="?page=%s&action=%s&lead=%s">' . "<img class='pull-left leadin-contact-avatar leadin-dynamic-avatar_" . substr($lead->lead_id, -1) . "' src='https://app.getsignals.com/avatar/image/?emails=" . $lead->lead_email . "' width='35' height='35'/> " . '</a>', $_REQUEST['page'], 'view', $lead->lead_id) .  sprintf('<a href="?page=%s&action=%s&lead=%s"><b>' . $lead->lead_email . '</b></a>', $_REQUEST['page'], 'view', $lead->lead_id),
                    'status' => $lead_status,
                    'visits' => ( !isset($lead->visits) ? 1 : $lead->visits ),
                    'submissions' => $lead->lead_form_submissions,
                    'pageviews' => $lead->lead_pageviews,
                    'date' => $lead->lead_date,
                    'source' => ( $lead->pageview_source ? "<a title='Visit page' href='" . $lead->pageview_source . "' target='_blank'>" . leadin_strip_params_from_url($lead->pageview_source) . "</a>" : 'Direct' ),
                    'last_visit' => $lead->last_visit,
                    'source' => ( $lead->lead_source ? "<a title='Visit page' href='" . $lead->lead_source . "' target='_blank'>" . leadin_strip_params_from_url($lead->lead_source) . "</a>" : 'Direct' )
                );
                
                array_push($all_leads, $lead_array);
                $contact_count++;
            }
        }

        $this->total_filtered = count($all_leads);

        return $all_leads;
    }

    /**
     * Gets the total number of contacts, comments and subscribers for above the table
     */
    function get_contact_type_totals ()
    {
        global $wpdb;
        // @TODO Need to select distinct emails
        $q = "
            SELECT 
                COUNT(DISTINCT lead_email) AS total_contacts,
                ( SELECT COUNT(DISTINCT lead_email) FROM li_leads WHERE lead_status = 'lead' AND lead_email != '' AND lead_deleted = 0 ) AS total_leads,
                ( SELECT COUNT(DISTINCT lead_email) FROM li_leads WHERE lead_status = 'comment' AND lead_email != '' AND lead_deleted = 0 ) AS total_comments,
                ( SELECT COUNT(DISTINCT lead_email) FROM li_leads WHERE lead_status = 'subscribe' AND lead_email != '' AND lead_deleted = 0 ) AS total_subscribes
            FROM 
                li_leads
            WHERE
                lead_email != '' AND lead_deleted = 0";

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
        $current_contact_type = ( !empty($_GET['contact_type']) ? html_entity_decode($_GET['contact_type']) : 'contacts' );
        return $current_contact_type;
    }

    /**
     * Gets the current action filter based off $_GET['contact_type']
     *
     * @return  string
     */
    function get_filters ()
    {
        $current_filters = array();

        $current_filters['contact_type'] = ( !empty($_GET['contact_type']) ? html_entity_decode($_GET['contact_type']) : 'all' );
        $current_filters['action'] = ( !empty($_GET['filter_action']) ? html_entity_decode($_GET['filter_action']) : 'all' );
        $current_filters['content'] = ( !empty($_GET['filter_content']) ? html_entity_decode($_GET['filter_content']) : 'all' );

        return $current_filters;
    }
    
    /**
     * Get the view menus above the contacts table
     *
     * @return  string
     */
    function get_views ()
    {
       $views = array();
       $this->totals = $this->get_contact_type_totals();

       $current = ( !empty($_GET['contact_type']) ? html_entity_decode($_GET['contact_type']) : 'all' );
       $all_params = array( 'contact_type', 's', 'paged', '_wpnonce', '_wpreferrer', '_wp_http_referer', 'action', 'action2', 'filter_action', 'filter_content');
       $all_url = remove_query_arg($all_params);

       // All link
       $class = ( $current == 'all' ? ' class="current"' :'' );
       $views['all'] = "<a href='{$all_url }' {$class} >" . ( $this->totals->total_leads + $this->totals->total_comments + $this->totals->total_subscribes ) .  " total contacts</a>";

       // Leads link
       $leads_url = add_query_arg('contact_type','lead', $all_url);
       $class = ( $current == 'lead' ? ' class="current"' :'' );
       $views['contacts'] = "<a href='{$leads_url}' {$class} >" . leadin_single_plural_label($this->totals->total_leads, 'lead', 'leads') .  "</a>";

       // Commenters link
       
       $comments_url = add_query_arg('contact_type','comment', $all_url);
       $class = ( $current == 'comment' ? ' class="current"' :'' );
       $views['commenters'] = "<a href='{$comments_url}' {$class} >" . leadin_single_plural_label($this->totals->total_comments, 'commenter', 'commenters') .  "</a>";

       // Commenters link
       $subscribers_url = add_query_arg('contact_type','subscribe', $all_url);
       $class = ( $current == 'subscribe' ? ' class="current"' :'' );
       $views['subscribe'] = "<a href='{$subscribers_url}' {$class} >" . leadin_single_plural_label($this->totals->total_subscribes, 'subscriber', 'subscribers') .  "</a>";

       return $views;
    }

    /**
     * Prints contacts menu next to contacts table
     */
    function views ()
    {
        $this->views = $this->get_views();
        $this->views = apply_filters( 'views_' . $this->screen->id, $this->views );

        $this->current_view = $this->get_view();

        if ( $this->current_view == 'lead' )
        {
            $this->view_label = 'Leads';
            $this->view_count = $this->totals->total_leads;
        }
        else if ( $this->current_view == 'comment' )
        {
            $this->view_label = 'Commenters';
            $this->view_count = $this->totals->total_comments;
        }
        else if ( $this->current_view == 'subscribe' )
        {
            $this->view_label = 'Subscribers';
            $this->view_count = $this->totals->total_subscribes;
        }
        else
        {
            $this->view_label = 'Contacts';
            $this->view_count = $this->totals->total_leads + $this->totals->total_comments + $this->totals->total_subscribes;
        }

        if ( empty( $this->views ) )
            return;

        echo "<ul class='leadin-contacts__type-picker'>\n";
            foreach ( $this->views as $class => $view ) 
            {
                $this->views[ $class ] = "\t<li class='$class'>$view";
            }
            echo implode( "</li>\n", $this->views ) . "</li>\n";
        echo "</ul>";
    }


    /**
     * Prints contacts filter above contacts table
     */
    function filters ()
    {
        global $wpdb;

        $filters = $this->get_filters();

        ?>
            <form id="leadin-contacts-filter" class="leadin-contacts__filter" method="GET">

                <h3 class="leadin-contacts__filter-text">
                    Viewing <span class="leadin-contacts__filter-count"> <?php echo ( $this->total_filtered != $this->view_count ? $this->total_filtered . '/' : '' ) . strtolower(leadin_single_plural_label($this->view_count, rtrim($this->view_label, 's'), $this->view_label)); ?></span> who 
                    <select class="select2" name="filter_action" id="filter_action" style="width:200px">
                        <option value="visited" <?php echo ( $filters['action']=='visited' ? 'selected' : '' ) ?> >Viewed</option>
                        <option value="submitted" <?php echo ( $filters['action']=='submitted' ? 'selected' : '' ) ?> >Submitted a form on</option>
                    </select>

                    <input type="hidden" name="filter_content" class="bigdrop" id="filter_content" style="width:300px" value="<?php echo ( isset($_GET['filter_content']) ? stripslashes($_GET['filter_content']) : '' ); ?>"/>
                    <input type="submit" name="" id="leadin-contacts-filter-button" class="button action" value="Apply">

                    <?php if ( isset($_GET['filter_action']) || isset($_GET['filter_content']) ) : ?>
                        <a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=leadin_contacts' . ( isset($_GET['contact_type']) ? '&contact_type=' . $_GET['contact_type'] : '' ); ?>" id="clear-filter">clear filter</a>
                    <?php endif; ?>
                </h3>

                <?php if ( isset($_GET['contact_type']) ) : ?>
                    <input type="hidden" name="contact_type" value="<?php echo $_GET['contact_type']; ?>"/>
                <?php endif; ?>

                <input type="hidden" name="page" value="leadin_contacts"/>

            </form>
        <?php
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
        
        $orderby = ( !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'last_visit' );
        $order = ( !empty($_REQUEST['order']) ? $_REQUEST['order'] : 'desc' );

        usort($this->data, array($this, 'usort_reorder'));

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

    function usort_reorder ( $a, $b ) 
    {
        $orderby = ( !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'last_visit' );
        $order = ( !empty($_REQUEST['order']) ? $_REQUEST['order'] : 'desc' );

        if ( $a[$orderby] == $b[$orderby] )
            $result = 0;
        else if ( $a[$orderby] < $b[$orderby] )
            $result = -1;
        else
            $result = 1;

        return ( $order === 'asc' ? $result : -$result );
    }
    
}