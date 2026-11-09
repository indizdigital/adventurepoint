( function () {
	const header = document.querySelector( '.ap-header' );
	const toggle = header && header.querySelector( '.ap-menu-toggle' );
	if ( ! toggle ) {
		return;
	}
	toggle.addEventListener( 'click', () => {
		const open = header.classList.toggle( 'is-open' );
		toggle.setAttribute( 'aria-expanded', String( open ) );
	} );
} )();
