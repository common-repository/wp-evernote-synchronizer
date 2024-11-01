var wpes;
( function( $ ) {

	//console.log( wpesRegisteredData );

	jQuery.fn.exists = function() { return Boolean( this.length > 0 ); }

	wpes = {

		// Init
			init: function() {

				// Update in Setting Page
					$( '#wpes-update-button' ).on( "click", function( e ) {

						$( this ).addClass( 'disabled' );
						$( '#wpes-save-button' ).addClass( 'disabled' );
						$( '#wpes-ajax-message' ).append( 'Updating...<br><br>' );

						$.ajax({
							type: "POST",
							url: ajaxurl,
							context: this,
							//dataType: "html",
							data: {
								"wpes_evernote_update_func": "wpes_evernote_update_func",
								action: "wpes_evernote_update_func"
							},
							error: function( jqHXR, textStatus, errorThrown ) {
								$( '#wpes-ajax-message' ).append( 'Error' );
								$( '#wpes-update-button' ).removeClass( 'disabled' );
								$( '#wpes-save-button' ).removeClass( 'disabled' );
							},
							success: function( data, textStatus, jqHXR ) {
								$( '#wpes-ajax-message' ).append( data );
							}
						}).done( function( data ) {
							$( '#wpes-update-button' ).removeClass( 'disabled' );
							$( '#wpes-save-button' ).removeClass( 'disabled' );
						});

					});

				// Update in User Profile in Admin
					$( '#wpes-update-user-button' ).on( "click", function( e ) {

						$( this ).addClass( 'disabled' );
						$( '#submit' ).addClass( 'disabled' );
						$( '#wpes-ajax-user-message' ).text( 'Updating...' );

						$.ajax({
							type: "POST",
							url: ajaxurl,
							context: this,
							//dataType: "html",
							data: {
								"wpes_user_id": $( '#user_id' ).val(),
								"wpes_user_token": $( '#wpes_user_evernote_token' ).val(),
								action: "wpes_common_evernote_update"
							},
							error: function( jqHXR, textStatus, errorThrown ) {
								$( '#wpes-ajax-user-message' ).text( 'Error' );
							},
							success: function( data, textStatus, jqHXR ) {
								$( '#wpes-ajax-user-message' ).text( 'Updated' );
							}
						}).done( function( data ) {
							$( '#wpes-update-user-button' ).removeClass( 'disabled' );
							$( '#submit' ).removeClass( 'disabled' );
						});

					});

			},

		// Get Noteboks

	};

	$( document ).ready( function() {
		$( 'body' ).append( '<style id="wpes-admin-styles"></style>' );
		wpes.init();
	});

}) ( jQuery );