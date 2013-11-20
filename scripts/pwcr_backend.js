/**
 * jQuery for plugin Password Changing Reminder
 * @version 2013-11-16
 */
jQuery( document ).ready(
	function($){
		$( '#pwcr_ignore_nag' ).live(
			'click',
			function() {
				$.post( ajaxurl, { 'action' : 'ignore_nag' },
					
					function( result ){
						$( '#pwcr_nag' ).hide( 'slow' );
					}
				);
			}
		);
	}
);