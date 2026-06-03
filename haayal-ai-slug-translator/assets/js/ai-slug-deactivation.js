( function () {
	if ( typeof haayalDeactivation === 'undefined' || typeof Swal === 'undefined' ) {
		return;
	}

	var d = haayalDeactivation;

	jQuery( function ( $ ) {
		var pluginRow = $( 'tr[data-plugin="' + d.pluginFile + '"]' );
		pluginRow.find( '.deactivate a' ).on( 'click', function ( e ) {
			e.preventDefault();
			var deactivateUrl = $( this ).attr( 'href' );
			Swal.fire( {
				title:              d.title,
				text:               d.message,
				icon:               'warning',
				showCancelButton:   true,
				confirmButtonColor: '#d33',
				cancelButtonColor:  '#3085d6',
				confirmButtonText:  d.confirmText,
				cancelButtonText:   d.cancelText,
			} ).then( function ( result ) {
				if ( result.isConfirmed ) {
					window.location.href = deactivateUrl;
				}
			} );
		} );
	} );
} )();
