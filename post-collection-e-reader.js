/**
 * E-reader actions in the Post Collection app.
 *
 * The app ships plain DOM, so this is deliberately dependency free: it picks up
 * the buttons rendered by Post_Collection_Integration and posts them to
 * admin-ajax. Which articles go into the ePub is either the one the button sits
 * on, or whatever is ticked off in the app's own selection.
 */
( function () {
	function getSelectedIds() {
		if ( window.postCollection && window.postCollection.getSelection ) {
			return window.postCollection.getSelection().map( function ( item ) {
				return item.id;
			} );
		}

		return [];
	}

	function resetButton( button, label ) {
		button.disabled = false;
		button.removeAttribute( 'aria-busy' );
		button.textContent = label;
	}

	function send( button ) {
		if ( button.disabled ) {
			return;
		}

		var data = new FormData();
		data.append( 'action', 'post-collection-send-to-e-reader' );
		data.append( '_ajax_nonce', button.dataset.nonce || '' );
		data.append( 'ereader', button.dataset.ereader || '' );

		if ( 'selected' === ( button.dataset.selection || '' ) ) {
			var ids = getSelectedIds();
			if ( ! ids.length ) {
				return;
			}

			ids.forEach( function ( id ) {
				data.append( 'ids[]', id );
			} );
		} else {
			data.append( 'ids[]', button.dataset.postId || '' );
		}

		if ( button.dataset.collection && '0' !== button.dataset.collection ) {
			data.append( 'collection', button.dataset.collection );
		}

		var label = button.textContent;
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		button.textContent = button.dataset.busyLabel || 'Sending...';

		fetch( button.dataset.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( result && result.data ? result.data : 'Could not send to the e-reader.' );
				}

				resetButton( button, button.dataset.doneLabel || 'Sent' );
				button.classList.remove( 'is-error' );
				button.classList.add( 'is-done' );

				if ( result.data && result.data.url ) {
					window.location.href = result.data.url;
				}

				window.setTimeout( function () {
					button.textContent = label;
				}, 4000 );
			} )
			.catch( function ( error ) {
				resetButton( button, label );
				button.classList.add( 'is-error' );
				button.title = error.message;
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-e-reader-send]' ) : null;
		if ( ! button ) {
			return;
		}

		event.preventDefault();
		send( button );
	} );
}() );
