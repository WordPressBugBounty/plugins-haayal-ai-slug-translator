( function () {
	if ( typeof haayalLog === 'undefined' || typeof Swal === 'undefined' ) {
		return;
	}

	var btn = document.getElementById( 'haayal-clear-log-btn' );
	if ( ! btn ) {
		return;
	}

	var d = haayalLog;

	btn.addEventListener( 'click', function () {
		Swal.fire( {
			title:              d.title,
			text:               d.text,
			icon:               'warning',
			showCancelButton:   true,
			confirmButtonText:  d.confirmText,
			cancelButtonText:   d.cancelText,
			confirmButtonColor: '#d33',
		} ).then( function ( result ) {
			if ( result.isConfirmed ) {
				document.getElementById( 'haayal-clear-log-form' ).submit();
			}
		} );
	} );
} )();
