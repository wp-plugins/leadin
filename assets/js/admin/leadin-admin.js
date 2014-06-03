jQuery(document).ready( function ( $ ) {
	$('.show_all_faces').on('click', function ( e ) {
		var $this = $(this);
		$this.closest('td').find('.hidden_face').removeClass('hidden_face');
		$this.remove();
		$("html,body").trigger("scroll");
	});

	$("img.lazy").lazyload({
		effect : "fadeIn"
	});
});