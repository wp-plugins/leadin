<?php

if ( !defined('LEADIN_PLUGIN_VERSION') ) 
{
	header('HTTP/1.0 403 Forbidden');
	die;
}

//=============================================
// WPLeadInUpdater Class
//=============================================
class WPLeadInUpdater {
	
	var $api_url = '';
	var $plugin_slug = '';

	/**
	 * Class constructor
	 */
	function __construct ( $update_type = 'beta' )
	{
		if ( $update_type == 'beta' )
			$this->api_url = 'http://leadin.com/plugins/index.php';
		else if ( $update_type == 'pro' )
			$this->api_url = 'http://leadin.com/pro/index.php';

		$this->plugin_slug = LEADIN_PLUGIN_SLUG;

		//=============================================
		// Hooks & Filters
		//=============================================

		if( is_admin() )
		{
			add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_plugin_update'));
			add_filter('plugins_api', array($this, 'plugin_api_call'), 10, 3);
		}
	}
	
	//=============================================
	// Update API
	//=============================================

	/**
	 * Adds setting link for Leadin to plugins management page 
	 *
	 * @param 	array $transient_data		plugins that have updates
	 * @return	array
	 */
	function check_for_plugin_update ( $transient_data ) 
	{
		global $wp_version;

		//Comment out these two lines during testing.
		if ( empty($transient_data->checked) )
			return $transient_data;

		$args = array(
			'slug' => $this->plugin_slug,
			'version' => $transient_data->checked[$this->plugin_slug . '/' . $this->plugin_slug . '.php'],
		);

		$request_string = array(
			'body' => array(
				'action' => 'basic_check', 
				'request' => serialize($args),
				'api-key' => md5(get_bloginfo('url'))
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
		);
		
		// Start checking for an update
		$raw_response = wp_remote_post($this->api_url, $request_string);

		if ( !is_wp_error($raw_response) && ($raw_response['response']['code'] == 200) )
		{
			$response = unserialize($raw_response['body']);
		}

		if ( is_object($response) && !empty($response) ) // Feed the update data into WP updater
			$transient_data->response[$this->plugin_slug . '/' . $this->plugin_slug . '.php'] = $response;

		$plugin_file = $this->plugin_slug .'/'. $this->plugin_slug .'.php';

		add_action( "after_plugin_row_$plugin_file", 'wp_plugin_update_row', 10, 2 );

		return $transient_data;
	}

	/**
	 * Adds setting link for Leadin to plugins management page 
	 *
	 * @param 	string $checked_data		plugins that have updates
	 * @return	object on success, WP_Error on failure.
	 */
	function plugin_api_call ( $def, $action, $args ) 
	{
		global $wp_version;

		if ( $args->slug != $this->plugin_slug )
			return false;
		
		// Get the current version
		$plugin_info = get_site_transient('update_plugins');
		$current_version = $plugin_info->checked[$this->plugin_slug . '/' . $this->plugin_slug . '.php'];
		$args->version = $current_version;
		
		$request_string = array(
			'body' => array(
				'action' => $action, 
				'request' => serialize($args),
				'api-key' => md5(get_bloginfo('url'))
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
		);
		
		$request = wp_remote_post($this->api_url, $request_string);
		
		if ( is_wp_error($request) ) 
		{
			$res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
		} 
		else 
		{
			$res = unserialize($request['body']);

			if ( $res === false )
				$res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
		}
		
		return $res;
	}
}

?>