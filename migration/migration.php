<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(-1);

$directory_path = rtrim(dirname(__FILE__),  '/\\');

include_once($directory_path . '/../../../../wp-load.php');

global $wpdb;

$wpdb->li_leads            = ( is_multisite() ? $wpdb->prefix . 'li_leads' : 'li_leads' );

$admin_url = admin_url('admin-ajax.php');

$leads_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$wpdb->li_leads'");

if ( $leads_table_exists )
{

	$leadin_nonce = ( isset($_REQUEST['leadin_wpnonce']) ? $_REQUEST['leadin_wpnonce'] : '' );

	if ( ! wp_verify_nonce($leadin_nonce, 'leadin-migration-nonce') )
	{
		echo "You do not have permission to access Leadin Migration. Please log in to your portal and access it through the Migration submenu in Leadin.";
	}
	else
	{
	    include_once($directory_path . '/migration.html');
	}
}
else
{
	// don't show the migration links in the menu of the plugin
}

/*
	- Migrate all the form submissions as a batch
	- Loop through all the analytics events for the contact
	- Migrate all the settings
	- Migrate the email service provider connectors (can we actually do this?)
*/


?>