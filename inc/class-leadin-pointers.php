<?php

if ( !defined('LEADIN_PLUGIN_VERSION') ) 
{
    header('HTTP/1.0 403 Forbidden');
    die;
}

/**
 * This class handles the pointers used in the introduction tour.
 *
 * @todo Add an introdutory pointer on the edit post page too.
 */
class LI_Pointers {

	/**
	 * Class constructor.
	 */
	function __construct ( $new_install = FALSE ) 
	{
		//=============================================
		// Hooks & Filters
		//=============================================

		if ( $new_install )
		{
			add_action('admin_enqueue_scripts', array($this, 'enqueue_new_install_pointer'));
		}
		else
		{
			add_action('admin_enqueue_scripts', array($this, 'enqueue_migration_pointer'));
		}
	}

	/**
	 * Enqueue styles and scripts needed for the pointers.
	 */
	function enqueue_new_install_pointer () 
	{
		if ( ! current_user_can('manage_options') )
			return;

		wp_enqueue_style('wp-pointer');
		wp_enqueue_script('jquery-ui');
		wp_enqueue_script('wp-pointer');
		wp_enqueue_script('utils');

		add_action('admin_print_footer_scripts', array($this, 'li_settings_popup_new'));
	}

	/**
	 * Enqueue styles and scripts needed for the pointers.
	 */
	function enqueue_migration_pointer () 
	{
		if ( ! current_user_can('manage_options') )
			return;

		wp_enqueue_style('wp-pointer');
		wp_enqueue_script('jquery-ui');
		wp_enqueue_script('wp-pointer');
		wp_enqueue_script('utils');

		add_action('admin_print_footer_scripts', array($this, 'li_settings_popup_migration'));
	}

	/**
	 * Loads in the required scripts for the pointer
	 */
	function enqueue_pointer_scripts ()
	{
		wp_enqueue_style('wp-pointer');
		wp_enqueue_script('jquery-ui');
		wp_enqueue_script('wp-pointer');
		wp_enqueue_script('utils');
	}

	/**
	 * Shows a popup that asks for permission to allow tracking.
	 */
	function li_settings_popup_new ()
	{
		$id    = '#toplevel_page_leadin';

		$content = '<h3>' . __('So close...', 'leadin') . '</h3>';
		$content .= '<p>' . __('Leadin needs just a bit more info to get up and running. Click on \'Complete Setup\' to complete the setup.', 'leadin') . '</p>';
		
		$opt_arr = array(
			'content'  => $content,
			'position' => array( 'edge' => 'left', 'align' => 'center' )
		);

		$function2 = 'li_redirect_to_settings()';

		$this->print_scripts($id, $opt_arr, 'Complete Setup', FALSE, '', $function2);
	}

	/**
	 * Shows a popup that asks for permission to allow tracking.
	 */
	function li_settings_popup_migration ()
	{
		$id    = '#toplevel_page_leadin';

		$content = '<h3>' . __('So close...', 'leadin' ) . '</h3>';
		$content .= '<p>' . __('Welcome to the new version of Leadin. We\'ve got some big changes in store. We need your quick attention to get the latest version up and running correctly. Click on \'Complete Setup\' to complete the setup - it seriously takes less than 60 seconds.', 'leadin' ) . '</p>';
		
		$opt_arr = array(
			'content'  => $content,
			'position' => array( 'edge' => 'left', 'align' => 'center' )
		);

		$function2 = 'li_redirect_to_settings()';

		$this->print_scripts($id, $opt_arr, 'Complete Setup', FALSE, '', $function2);
	}

	/**
	 * Prints the pointer script
	 *
	 * @param string      $selector         The CSS selector the pointer is attached to.
	 * @param array       $options          The options for the pointer.
	 * @param string      $button1          Text for button 1
	 * @param string|bool $button2          Text for button 2 (or false to not show it, defaults to false) 
	 * @param string      $button2_function The JavaScript function to attach to button 2
	 * @param string      $button1_function The JavaScript function to attach to button 1
	 */
	function print_scripts( $selector, $options, $button1, $button2 = FALSE, $button2_function = '', $button1_function = '' ) 
	{
		?>
		<script type="text/javascript">
			//<![CDATA[
			(function ($) {

				var li_pointer_options = <?php echo json_encode( $options ); ?>, setup;

				function li_redirect_to_settings() {
					window.location.href = "<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=leadin_settings";
				}
 
				li_pointer_options = $.extend(li_pointer_options, {
					buttons: function (event, t) {
						button = jQuery('<a id="pointer-close" style="margin-left:5px" class="button-secondary">' + '<?php echo $button1; ?>' + '</a>');
						button.bind('click.pointer', function () {
							window.location.href = "<?php echo get_bloginfo('wpurl'); ?>/wp-admin/admin.php?page=leadin_settings";
							//t.element.pointer('close');
						});
						return button;
					},
					close  : function () {
					}
				});

				setup = function () {
					$('<?php echo $selector; ?>').pointer(li_pointer_options).pointer('open');
				};

				if ( li_pointer_options.position && li_pointer_options.position.defer_loading )
					$(window).bind('load.wp-pointers', setup);
				else
					$(document).ready(setup);
			})(jQuery);
			//]]>

		</script>
	<?php
	}
}
