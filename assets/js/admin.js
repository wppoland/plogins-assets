/* Plogins Assets - add/remove repeatable rule rows. No build step, no deps. */
(function () {
	'use strict';

	var rows = document.getElementById( 'plogins-assets-rows' );
	var tpl = document.getElementById( 'plogins-assets-template' );
	var addBtn = document.getElementById( 'plogins-assets-add' );

	if ( ! rows || ! tpl || ! addBtn ) {
		return;
	}

	var counter = rows.querySelectorAll( '.plogins-assets-row' ).length;

	addBtn.addEventListener( 'click', function () {
		var html = tpl.innerHTML.replace( /__INDEX__/g, String( counter++ ) );
		var tmp = document.createElement( 'tbody' );
		tmp.innerHTML = html.trim();
		var row = tmp.querySelector( '.plogins-assets-row' );
		if ( row ) {
			rows.appendChild( row );
		}
	} );

	rows.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.plogins-assets-remove' );
		if ( ! btn ) {
			return;
		}
		var row = btn.closest( '.plogins-assets-row' );
		if ( row && rows.querySelectorAll( '.plogins-assets-row' ).length > 1 ) {
			row.remove();
		} else if ( row ) {
			// Keep at least one row; just clear the handle field.
			var handle = row.querySelector( 'input[name$="[handle]"]' );
			if ( handle ) {
				handle.value = '';
			}
		}
	} );
})();
