jQuery( function( $ ) {
	var $document = $( document );

	wp = wp || {};
	$document.on( 'click', 'a.friends-send-post-to-e-reader,a.friends-send-new-posts-to-ereader', function() {
		var $this = $(this);
		var search_indicator = $this.find( 'i' );
		if ( search_indicator.hasClass( 'loading' ) ) {
			return false;
		}

		var data = {
			ereader: $this.data( 'ereader' ),
			_ajax_nonce: send_to_ereader.nonce
		};

		var show_dialog = false;

		var dialog = document.getElementById( 'friends-ereader-multi-prompt' );
		var post_list = $( dialog ).find( 'ul' );

		if ( $this.hasClass( 'friends-send-new-posts-to-ereader' ) ) {
			data.unsent = $this.data( 'unsent' );
			data.query_vars = friends.query_vars;
			data.qv_sign = friends.qv_sign;
			post_list.hide();
			$( dialog ).find( 'h5' ).text( data.unsent + ' new posts selected' );
			show_dialog = true;
			search_indicator.addClass( 'pl-2' );
		} else {
			data.ids = [ $this.data( 'id' ) ];
			if ( $this.closest( 'ul' ).find( 'li.menu-item input[name=multi-entry]' ).is( ':checked' ) ) {
				$this.closest( 'article' ).prevAll().slice( -30 ).each( function( i, article ) {
					if ( 'post-' === article.id.substr( 0, 5 ) ) {
						data.ids.push( Number( article.id.substr( 5 ) ) );
						post_list.append( '<li>' + $( article ).find( 'h4.card-title' ).text().replace( /^Private: /, '' ) + ' by ' + $( article ).find( 'div.author' ).text() );
					}
				} );
			}
			$( dialog ).find( 'h5' ).text( data.ids.length + ' posts selected' );
			post_list.append( '<li>' + $this.closest( 'article' ).find( 'h4.card-title' ).text() + ' by ' + $this.closest( 'article' ).find( 'div.author' ).text() );
			show_dialog = data.ids.length > 1;

			$( '#ebook-title' ).prop( 'placeholder', $.trim( $( '#post-' + data.ids[0] + ' h4.card-title' ).text().replace( /\s+/, ' ' ) ) + ' & more' );
			$( '#ebook-author' ).prop( 'placeholder', $.trim( $.trim( $( '#post-' + data.ids[0] + ' div.author' ).text().replace( /\s+/, ' ' ) ) + ' et al' ) );
		}

		var send = function( data ) {
			wp.ajax.send( 'send-post-to-e-reader', {
				data: data,
				beforeSend: function() {
					search_indicator.addClass( 'form-icon loading' );
					if ( ! data.unread ) {
						setTimeout( function() { $this.closest( 'div' ).find( 'a.friends-dropdown-toggle' ).focus(); }, 100 );
					}
				},
				success: function( e ) {
					search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-saved' );
					if ( e.url ) {
						location.href = e.url;
					}
				},
				error: function( e ) {
					search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-warning' ).prop( 'title', e.responseText.replace( /<\/?[^>]+(>|$)/g, '' ) );
				}
			} );
		}

		if ( show_dialog && dialog ) {
			dialog.showModal();
			$( document ).on( 'click', 'dialog .close', function() {
				dialog.close();
				return false;
			} );
			$( document ).on( 'click', 'dialog button[name=ok]', function() {
				if ( $( '#ebook-title' ).val() ) {
					data.title = $( '#ebook-title' ).val();
				}
				if ( $( '#ebook-author' ).val() ) {
					data.author = $( '#ebook-author' ).val();
				}

				send( data );
				dialog.close();
				return false;
			} );
		} else {
			send( data );
		}

		return false;
	} );

	$document.on( 'click', 'a.friends-unmark-e-reader-send', function() {
		var $this = $(this);
		var search_indicator = $this.find( 'i' );
		if ( search_indicator.hasClass( 'loading' ) ) {
			return false;
		}

		wp.ajax.send( 'unmark-e-reader-send', {
			data: {
				_ajax_nonce: send_to_ereader.nonce,
				id: $this.data( 'id' )
			},
			beforeSend: function() {
				search_indicator.addClass( 'form-icon loading' );
			},
			success: function( e ) {
				search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-saved' );
				$this.closest( 'li' ).prevAll().filter( '.divider.ereader' ).attr( 'data-content', send_to_ereader.ereader );
			},
			error: function( e ) {
				search_indicator.removeClass( 'form-icon loading' ).addClass( 'dashicons dashicons-warning' ).prop( 'title', e.responseText.replace( /<\/?[^>]+(>|$)/g, '' ) );
			}
		} );
		return false;
	} );

	$document.on( 'click', 'a#add-reader', function() {
		$( 'tr.template' ).removeClass( 'hidden' ).find( 'input:visible:first' ).focus();
		$( this ).remove();
		return false;
	} );

	$document.on( 'click', '#friends-ereader-multi-prompt a.title', function() {
		$( '#ebook-title' ).val( $( this ).data( 'content' ) );
		return false;
	} );

	$document.on( 'click', '#friends-ereader-multi-prompt a.author', function() {
		$( '#ebook-author' ).val( $( this ).data( 'content' ) );
		return false;
	} );

	$document.on( 'change', 'select#ereader-class', function() {
		var td = $( '#' + $( this ).val().replace( '\\', '\\\\' ) );
		td.show().siblings().hide();
		var n = $( 'tr.template input.name' );
		if ( '' === n.val() ) {
			n.val( $( this ).find( 'option:selected' ).text() );
		}
		td.find( 'input:visible:first' ).focus();
	} );

	$document.on( 'click', 'a.delete-reader', function() {
		$( this ).closest('tr').html( '<td colspan=3>' + $( this ).data( 'delete-text' ) );
		return false;
	} );


	$document.on( 'click', 'span.download-preview', function() {
		const range = document.createRange();
		range.selectNodeContents( this );

		const selection = window.getSelection();
		selection.removeAllRanges();
		selection.addRange( range );
	} );

	$document.on( 'keyup', 'input#download_password', function() {
		$( 'tt.download_password_preview' ).text( $(this).val() );
		return false;
	} );

	$document.on( 'change', 'select#all-friends-preview', function() {
		$( 'tt.friends-sample-url' ).text( $(this).val() );
		return false;
	} );

} );
