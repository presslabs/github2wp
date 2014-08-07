var j = jQuery.noConflict();

j( document ).ready( function( $ ) {
  //toggler
  j( '.slider' ).hide();

  j( '.clicker' ).click( function() {
		var alt = j( this ).attr( 'alt' ),
		elem = j( '.slider[id="' + alt + '"]' );

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

	//branch updater
	j( '.resource_set_branch' ).change( function() {
		var id = j( this ).attr( 'resource_id' ),
			branch = j( this ).val();

  	j( this ).after( '<div class="ajax-loader"></div>' );

  	j.ajax( ajaxurl, {
   		type: 'post',
			async: true,
			data: { action: 'github2wp_ajax', 'id': id, 'branch': branch, 'github2wp_action': 'set_branch' },

  		success: function( response ) {
				if ( response['success'] ) {
					var div = j( 'select.resource_set_branch[resource_id="'
						+ id + '"] + div.ajax-loader' ).removeClass( 'ajax-loader' ).addClass( 'ajax-success' );

					setTimeout( function() {
						div.fadeOut( 1000, function() {
							div.remove(); 
						});
					}, 2500);
  			} else {
  				var div = j( 'select.resource_set_branch[resource_id="'
						+ id + '"] + div.ajax-loader').removeClass( 'ajax-loader' ).addClass( 'ajax-fail' );

					setTimeout( function() {
						div.fadeOut( 1000, function() {
							div.remove();
						});
					}, 2500); 		
				}				
  		},
  		error: function( response ) {
 				alert ( " Can't do because: " + response['error_message'] );
			},
  		dataType: 'json'
		});
	})
		
		// Downgrade fetch
	j( '.history-slider' ).click( function( e ) {
		var self = j(this),
			alt = self.attr( 'alt'),
			container = j( '.slider[id="' + alt + '"]');

    if ( container.hasClass( 'down' ) ) {
			var res_id = alt.split( '-' )[2];

			container.empty();
			container.append( '<div class="ajax-loader"></div>' );

			j.ajax( ajaxurl, {
				type: 'post',
				async: true,
				data: { action: 'github2wp_ajax', 'res_id': res_id, 'github2wp_action': 'fetch_history' },
				success: function( response ) {
					if ( response ) {								
						container.empty();
						container.append( response );
						container.on( 'click', '.downgrade', function( e ) {
							//downgrader
							e.preventDefault();
							var self = j( this ),
								array = j( this ).attr( 'id' ).split( '-' ),
								res_id = array[2],
								commit_id = array[3];

							j( this ).attr( 'disabled', 'disabled' );

							j.ajax( ajaxurl, {
								type: 'post',
								async: true,
								data: { action: 'github2wp_ajax', 'res_id': res_id, 'commit_id': commit_id, 'github2wp_action': 'downgrade' },
								dataFilter: function ( rawresponse, type ) {
									if ( "json" == type )
										if ( -1 != rawresponse.indexOf( '</html>' ) )
											return rawresponse.split( '</html>' )[1];

									return rawresponse;
								},
								success: function( response ) {
									if ( response['success'] ) {
										var elem = j( '<div style="color: green;" class="updated" >'
											+ response['success_message'] + '</div>' ).appendTo( '#github2wp_history_messages' );
										self.removeAttr( 'disabled' );
										
										setTimeout( function() {
											elem.fadeOut( 1000, function() {
												elem.remove();
											});
										}, 2500 ); 		
									} else {
										var elem = j( '<div style="color: red;" class="updated">'
												+ response['error_message'] + '</div>' ).appendTo( '#github2wp_history_messages' );
										setTimeout( function() {
											elem.fadeOut( 1000, function() {
												elem.remove();
											});
											}, 2500); 		
									}
								},
								error: function( data, error ) {
									var elem = j( '<div style="color: red;" class="updated"> Ajax response error: '
										+ error + '</div>').appendTo( '#github2wp_history_messages');
									setTimeout( function() {
										elem.fadeOut( 1000, function() {
											elem.remove();
										});
										}, 2500 ); 	
								},
								dataType: 'json'
							});					
						});
					} else {
						container.empty();
						container.append( '<div class="ajax-fail"></div>' );
					}
				},
				error: function( data, error ) {
					container.empty();
					container.append( '<div class="ajax-fail"></div>' );
				},
				dataType: 'html'
			});
		}
	});
});
