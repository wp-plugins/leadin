jQuery(document).ready( function ( $ ) {
	
	$("#filter_action").select2();

	$("#filter_content").select2({
	    query: function( query ) {
	    	var key = query.term;
	    	
	    	$.ajax({
				type: 'POST',
				url: li_admin_ajax.ajax_url,
				data: {
					"action": "leadin_get_posts_and_pages", 
					"search_term": key
				},
				success: function(data){
					// Force override the current tracking with the merged value
					var json_data = jQuery.parseJSON(data);
                    var data_test = {results: []}, i, j, s;
			        for ( i = 0; i < json_data.length; i++ ) 
			        {
			            data_test.results.push({id: json_data[i].post_title, text: json_data[i].post_title});
			        }

			        query.callback(data_test);
				}
			})
	    
	    },
	    initSelection: function(element, callback) {
	    	if ( $('#filter_content').val() )
	    	{
	    		$('#filter_content').select2("data", {id: $('#filter_content').val(), text: $('#filter_content').val()});
	    	}
	    	else
	    	{
	    		$('#filter_content').select2("data", {id: '', text: 'Any Page'});
	    	}
	    }
	});

	$('#leadin-contact-status').change( function ( e ) {
		$('#leadin-contact-status-button').addClass('button-primary');
	});
});