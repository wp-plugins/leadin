jQuery(document).ready( function ( $ ) {
	$('#leadin-contacts input:checkbox').not('thead input:checkbox, tfoot input:checkbox').bind('change', function ( e  ){
		var cb_count = 0;
		var selected_vals = '';
		var $btn_selected = $('#leadin-export-selected-leads');
		var $input_selected_vals = $('#leadin-selected-contacts');
		var $cb_selected = $('#leadin-contacts input:checkbox:checked').not('thead input:checkbox, tfoot input:checkbox');

		if ( $cb_selected.length > 0 )
		{
			$btn_selected.attr('disabled', false);
		}
		else
		{
			$btn_selected.attr('disabled', true);
		}

		$cb_selected.each( function ( e ) {
			selected_vals += $(this).val();
			
			if ( cb_count != ($cb_selected.length-1) )
				selected_vals += ',';

			cb_count++;
		});

		$input_selected_vals.val(selected_vals);
	});

	$('#cb-select-all-1, #cb-select-all-2').bind('change', function ( e ) { 
		var cb_count = 0;
		var selected_vals = '';
		var $this = $(this);
		var $btn_selected = $('#leadin-export-selected-leads');
		var $cb_selected = $('#leadin-contacts input:checkbox').not('thead input:checkbox, tfoot input:checkbox');
		var $input_selected_vals = $('#leadin-selected-contacts');

		$cb_selected.each( function ( e ) {
			selected_vals += $(this).val();
			
			if ( cb_count != ($cb_selected.length-1) )
				selected_vals += ',';

			cb_count++;
		});

		$input_selected_vals.val(selected_vals);

		if ( !$this.is(':checked') )
		{
			$btn_selected.attr('disabled', true);
		}
		else
		{
			$btn_selected.attr('disabled', false);
		}
	});

	$('.postbox .handlediv').bind('click', function ( e  ) {
		var $postbox = $(this).parent();
		
		if ( $postbox.hasClass('closed') )
		{
			$postbox.removeClass('closed');
		}
		else
		{
			$postbox.addClass('closed');
		}

	});
});