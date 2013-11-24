/**
 * jQuery for plugin Password Changing Reminder
 * @version 2013-11-23
 */
jQuery( document ).ready(
	function($){
		/*
		 * guessing the url to admin-ajax.php. this is used if no object was created by the plugin
		 * and we are in frontend where no 'ajaxurl' is available.
		 */
		var ajax_url = '';
		var guessed_ajaxurl = location.protocol + '//' + location.host + location.pathname + 'wp-admin/admin-ajax.php';

		if( typeof ajaxurl === 'undefined' ) {

			if ( 'ajaxurl' in PwCR ) {
				ajax_url = PwCR.ajaxurl;
//console.debug( 'from object' );
			} else {
				ajax_url = guessed_ajaxurl;
//console.debug( 'from guessed url' );
			}
			
		} else {
//console.debug( 'from original var' );
			ajax_url = ajaxurl;
		}
		
//console.debug( ajax_url );
		$( '#pwcr_ignore_nag' ).live(
			'click',
			function() {
				
				$.post( ajax_url, { 'action' : 'ignore_nag' },
					
					function( result ){
						$( '#pwcr_nag' ).hide( 'slow' );
					}
				);
			}
		);
	}
);