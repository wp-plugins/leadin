jQuery(document).ready( function ( $ ) {
	$('#filter_action, #filter_content').change(function() {
		$('#leadin-contacts-filter-button').addClass('button-primary');
	});
});