//TODO add NONCES to ajax

(function( $ ) {
	toggler();
	branch_set();
	history_processor();


	function toggler() {
		$( '.slider' ).hide();

		$( '.clicker' ).click( function() {
			var alt = $( this ).attr( 'alt' );
			var elem = $( '.slider[id="' + alt + '"]' );

			if ( ! elem.hasClass( 'up' ) && ! elem.hasClass( 'down' ) ) {
				elem.addClass('down');
			} else {
				if ( elem.hasClass( 'up' ) ) {
					elem.addClass( 'down' );
					elem.removeClass( 'up' );
				} else {
					if (elem.hasClass( 'down' ) ) {
						elem.addClass( 'up' );
						elem.removeClass( 'down' );
					}
				}
			}
			elem.slideToggle( 'slow', function() {} );
		});
	}



	function branch_set() {
		var ajax_loader = '<div class="ajax ajax-loader"></div>';

		$( '.resource_set_branch' ).change( function() {
			var id = $( this ).attr( 'resource_id' );
			var	branch = $( this ).val();

			$( this ).after( ajax_loader );

			$.ajax( ajaxurl, {
				type: 'post',
				async: true,
				data: { action: 'github2wp_set_branch', 'id': id, 'branch': branch },

				success: function( response ) {
					if ( response['success'] )
						response_message( id, 'ajax-success' );
					else
						response_message( id, 'ajax-fail' );
				},
				error: function( response ) {
					alert ( " Can't do because: " + response['error_message'] );
				},
				dataType: 'json'
			});
		});


		function response_message( id, newclass ) {
			var div = $( 'select.resource_set_branch[resource_id="'
				+ id + '"] + div.ajax-loader' ).removeClass( 'ajax-loader' ).addClass( newclass );

			custom_fadeout( div );
		}
	}



	function history_processor() {
		var ajax_fail = '<div class="ajax ajax-fail"></div>';
		var ajax_loader = '<div class="ajax ajax-loader"></div>';

		$( '.history-slider' ).click( function( e ) {
			var self = $(this);
			var	alt = self.attr( 'alt');
			var	container = $( '.slider[id="' + alt + '"]');

			if ( container.hasClass( 'down' ) ) {
				var res_id = alt.split( '-' )[2];

				fresh_append( container, ajax_loader )

				$.ajax( ajaxurl, {
					type: 'post',
					async: true,
					data: { action: 'github2wp_fetch_history', 'res_id': res_id },
					success: function( response ) {
						if( !response ) {
							fresh_append( container, ajax_fail );
							return;
						}

						downgrader( container, response );
						self.remove();
					},
					error: function( data, error ) {
						fresh_append( container, ajax_fail );
					},
					dataType: 'html'
				});
			}
		});

		function downgrader( container, response ) {
			fresh_append( container, response );

			container.on( 'click', '.downgrade', function( e ) {
				e.preventDefault();
				var self = $( this );
				var	array = $( this ).attr( 'id' ).split( '-' );
				var	res_id = array[2];
				var	commit_id = array[3];

				$( this ).attr( 'disabled', 'disabled' );

				$.ajax( ajaxurl, {
					type: 'post',
					async: true,
					data: { action: 'github2wp_downgrade', 'res_id': res_id, 'commit_id': commit_id },
					dataFilter: function ( rawresponse, type ) {
						if ( "json" == type )
							if ( -1 != rawresponse.indexOf( '</html>' ) )
								return rawresponse.split( '</html>' )[1];

						return rawresponse;
					},
					success: function( response ) {
						if ( response['success'] ) {
							if( response['notice_message'] )
								response_message( response['notice_message'], 'notice' );
							else
								response_message( response['success_message'] );
							self.removeAttr( 'disabled' );
						} else {
							response_message( response['error_message'], 'error' );
						}
					},
					error: function( data, error ) {
						response_message( error, 'error' );
					},
					dataType: 'json'
				});
			});


			function response_message( message, message_type ) {
				var color = 'green';

				if( message_type === 'error' )
					color = 'red';
				else if (message_type === 'notice' )
					color = 'orange';

				var elem = $( '<div style="color:' + color
					+ ';" class="updated">' + message + '</div>' ).appendTo('#github2wp_history_messages');

				custom_fadeout( elem );
			}
		}
	}



	function custom_fadeout( elem ) {
		setTimeout( function() {
			elem.fadeOut( 1000, function() {
				elem.remove();
			});
		}, 2500 );
	}


	function fresh_append( elem, html ) {
		elem.empty();
		elem.append( html );	
	}

})( jQuery );
