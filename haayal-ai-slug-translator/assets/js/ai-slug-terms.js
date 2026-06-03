( function () {
	if ( typeof haayalTermBadge === 'undefined' ) {
		return;
	}

	var d          = haayalTermBadge;
	var slugInput  = document.getElementById( 'slug' );

	if ( ! slugInput ) {
		return;
	}

	var AI_ICON =
		'<svg aria-hidden="true" class="haayal-badge-icon" width="13" height="13" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
		'<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2m-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8z"/></svg>';

	var EDIT_ICON =
		'<svg aria-hidden="true" class="haayal-badge-icon" width="13" height="13" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
		'<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75z"/></svg>';

	var EXTERNAL_ICON =
		'<svg aria-hidden="true" class="haayal-badge-icon" width="12" height="12" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
		'<path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3z"/></svg>';

	var wrap = document.createElement( 'div' );
	wrap.className = 'haayal-term-badge-wrap';

	if ( d.showAiBadge ) {
		var aiBadge = document.createElement( 'span' );
		aiBadge.className = 'haayal-pill haayal-pill--ai';
		aiBadge.innerHTML = AI_ICON + d.aiBadgeLabel;
		wrap.appendChild( aiBadge );
	}

	if ( d.showEditedBadge ) {
		var editedBadge = document.createElement( 'span' );
		editedBadge.className = 'haayal-pill haayal-pill--edited';
		editedBadge.innerHTML = EDIT_ICON + d.editedBadgeLabel;
		wrap.appendChild( editedBadge );
	}

	if ( d.showBulkLink ) {
		var bulkLink = document.createElement( 'a' );
		bulkLink.href             = d.bulkUrl;
		bulkLink.target           = '_blank';
		bulkLink.rel              = 'noopener noreferrer';
		bulkLink.className        = 'haayal-pill haayal-pill--bulk';
		bulkLink.innerHTML        = d.bulkLabel + ' ' + EXTERNAL_ICON;
		wrap.appendChild( bulkLink );
	}

	if ( wrap.hasChildNodes() ) {
		slugInput.parentNode.insertBefore( wrap, slugInput.nextSibling );
	}
} )();
