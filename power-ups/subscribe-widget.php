<?php
/**
	* Power-up Name: Subscribe Pop-up
	* Power-up Class: WPLeadInSubscribe
	* Power-up Menu Text: 
	* Power-up Slug: subscribe_widget
	* Power-up Menu Link: settings
	* Power-up URI: http://leadin.com/pop-subscribe-form-plugin-wordpress
	* Power-up Description: Convert more email subscribers with our pop-up.
	* Power-up Icon: powerup-icon-subscribe
	* First Introduced: 0.4.7
	* Power-up Tags: Lead Generation
	* Auto Activate: Yes
*/

//=============================================
// Define Constants
//=============================================

if ( !defined('LEADIN_SUBSCRIBE_WIDGET_PATH') )
    define('LEADIN_SUBSCRIBE_WIDGET_PATH', LEADIN_PATH . '/power-ups/subscribe-widget');

if ( !defined('LEADIN_SUBSCRIBE_WIDGET_PLUGIN_DIR') )
	define('LEADIN_SUBSCRIBE_WIDGET_PLUGIN_DIR', LEADIN_PLUGIN_DIR . '/power-ups/subscribe-widget');

if ( !defined('LEADIN_SUBSCRIBE_WIDGET_PLUGIN_SLUG') )
	define('LEADIN_SUBSCRIBE_PLUGIN_SLUG', basename(dirname(__FILE__)));

//=============================================
// Include Needed Files
//=============================================
require_once(LEADIN_SUBSCRIBE_WIDGET_PLUGIN_DIR . '/admin/subscribe-widget-admin.php');

//=============================================
// WPLeadIn Class
//=============================================
class WPLeadInSubscribe extends WPLeadIn {
	
	var $admin;

	/**
	 * Class constructor
	 */
	function __construct ( $activated )
	{
		//=============================================
		// Hooks & Filters
		//=============================================

		if ( ! $activated )
			return false;

		add_filter('init', array($this, 'add_leadin_subscribe_frontend_scripts_and_styles'));

		add_action('get_footer', array(&$this, 'append_leadin_subscribe_heading'));
	}

	public function admin_init ( )
	{
		$admin_class = get_class($this) . 'Admin';
		$this->admin = new $admin_class();
	}

	function power_up_setup_callback ( )
	{
		$this->admin->power_up_setup_callback();
	}

	/**
	 * Activate the power-up
	 */
	function add_leadin_subscribe_defaults ()
	{
		$lis_options = get_option('leadin_subscribe_options');

		if ( ($lis_options['li_susbscibe_installed'] != 1) || (!is_array($lis_options)) )
		{
			$opt = array(
				'li_susbscibe_installed' => '1',
				'li_subscribe_heading' => 'Sign up for my newsletter to get new posts by email'
			);

			update_option('leadin_subscribe_options', $opt);
		}
	}

	/**
	 * Adds a hidden input at the end of the content containing the ouput of the heading options
	 *
	 * @return 
	 */
	function append_leadin_subscribe_heading ()
	{
		$lis_options = get_option('leadin_subscribe_options');

	    // Heading for the subscribe plugin
	    echo '<input id="leadin-subscribe-heading" value="' . ( isset($lis_options['li_subscribe_heading']) ? $lis_options['li_subscribe_heading'] : 'Sign up for my newsletter to get new posts by email' )  . '" type="hidden"/>';

	    // Div checked by media query for mobile
	    echo '<span id="leadin-subscribe-mobile-check"></span>';
	}

	//=============================================
	// Scripts & Styles
	//=============================================

	/**
	 * Adds front end javascript + initializes ajax object
	 */
	function add_leadin_subscribe_frontend_scripts_and_styles ()
	{
		global $pagenow;

		if ( !is_admin() && $pagenow != 'wp-login.php' )
		{
			wp_register_script('leadin-subscribe', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/js/leadin-subscribe.js', array ('jquery', 'leadin'), false, true);
			wp_register_script('vex', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/js/vex.js', array ('jquery', 'leadin'), false, true);
			wp_register_script('vex-dialog', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/js/vex.dialog.js', array ('jquery', 'leadin'), false, true);

			wp_enqueue_script('leadin-subscribe');
			wp_enqueue_script('vex');
			wp_enqueue_script('vex-dialog');

			wp_register_style('leadin-subscribe-css', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/css/leadin-subscribe.css');
			wp_register_style('leadin-subscribe-vex-css', LEADIN_SUBSCRIBE_WIDGET_PATH . '/frontend/css/vex.css');
			
			wp_enqueue_style('leadin-subscribe-vex-css');
			wp_enqueue_style('leadin-subscribe-css');


			//wp_localize_script('leadin', 'li_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
		}
	}

	function notify_new_post($post_id) {
		if( ( $_POST['post_status'] == 'publish' ) && ( $_POST['original_post_status'] != 'publish' ) ) {
		    $headers = "From: LeadIn <team@leadin.com>\r\n";
		    $headers.= "Reply-To: LeadIn <team@leadin.com>\r\n";
		    $headers.= "X-Mailer: PHP/" . phpversion()."\r\n";
		    $headers.= "MIME-Version: 1.0\r\n";
		    $headers.= "Content-type: text/html; charset=utf-8\r\n";
		    
		    $post = get_post($post_id);
		    $author = get_userdata($post->post_author);
		    $author_email = $author->user_email;
		    $email_subject = "Your post has been published.";

		    ob_start(); ?>

		    <html>
		        <head>
		            <title>New post at <?php bloginfo( 'name' ) ?></title>
		        </head>
		        <body>
		            <p>
		                Hi <?php echo $author->user_firstname ?>,
		            </p>
		            <p>
		                Your post <a href="<?php echo get_permalink($post->ID) ?>"><?php the_title_attribute() ?></a> has been published.
		            </p>
		        </body>
		    </html>

		    <?php

		    $message = ob_get_contents();

		    ob_end_clean();

		    wp_mail( 'andy@leadin.com', $email_subject, $message );
		}
	}
}

//=============================================
// Subscribe Widget Init
//=============================================

global $leadin_subscribe_wp;
//$leadin_subscribe_wp = new WPLeadInSubscribe();

?>