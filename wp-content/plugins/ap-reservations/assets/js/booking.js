/* AP Reservations – booking wizard. No dependencies. */
( function () {
	'use strict';

	const cfg = window.apReservations;
	const root = document.getElementById( 'ap-reservation' );
	if ( ! cfg || ! root ) {
		return;
	}

	const t = cfg.i18n;
	const form = root.querySelector( '.apr-form' );
	const alertBox = root.querySelector( '.apr-alert' );
	const steps = [ ...root.querySelectorAll( '.apr-step' ) ];
	const progress = [ ...root.querySelectorAll( '[data-progress]' ) ];
	const btnPrev = root.querySelector( '[data-prev]' );
	const btnNext = root.querySelector( '[data-next]' );
	const btnSubmit = root.querySelector( '[data-submit]' );
	const summary = root.querySelector( '.apr-summary' );
	const canoeById = Object.fromEntries( cfg.canoes.map( ( c ) => [ c.id, c ] ) );
	const extraById = Object.fromEntries( cfg.extras.map( ( e ) => [ e.id, e ] ) );

	let step = 1;
	let lastQuote = null;
	let quoteTimer = null;
	let quoteSeq = 0;

	const fmt = ( n ) => {
		try {
			return new Intl.NumberFormat( cfg.locale, { style: 'currency', currency: cfg.currency } ).format( n );
		} catch ( e ) {
			return cfg.currency + ' ' + Number( n ).toFixed( 2 );
		}
	};
	const sprintf = ( s, n ) => s.replace( '%d', n ).replace( '%s', n );
	const toMin = ( hhmm ) => {
		const [ h, m ] = hhmm.split( ':' ).map( Number );
		return h * 60 + m;
	};

	/* ---------- state ---------- */

	const field = ( name ) => form.elements.namedItem( name );
	const mode = () => ( form.querySelector( 'input[name="mode"]:checked' ) || {} ).value || 'hour';

	function quantities( type ) {
		const out = {};
		root.querySelectorAll( `.apr-item[data-type="${ type }"]` ).forEach( ( el ) => {
			const qty = parseInt( el.querySelector( 'input' ).value, 10 ) || 0;
			if ( qty > 0 && ! el.classList.contains( 'is-disabled' ) ) {
				out[ el.dataset.id ] = qty;
			}
		} );
		return out;
	}

	function payload() {
		const period = { mode: mode(), date: field( 'date' ).value };
		if ( period.mode === 'hour' ) {
			period.start_time = field( 'start_time' ).value;
			period.hours = parseInt( field( 'hours' ).value, 10 );
		} else {
			period.end_date = field( 'end_date' ).value || field( 'date' ).value;
		}
		return { lang: cfg.lang, period, canoes: quantities( 'canoe' ), extras: quantities( 'extra' ) };
	}

	function persons() {
		return Object.entries( quantities( 'canoe' ) ).reduce( ( sum, [ id, qty ] ) => sum + qty * ( canoeById[ id ]?.capacity || 1 ), 0 );
	}

	/* ---------- UI helpers ---------- */

	function showError( msg ) {
		alertBox.textContent = msg || '';
		alertBox.hidden = ! msg;
	}

	function goTo( n ) {
		step = n;
		steps.forEach( ( s ) => {
			const active = Number( s.dataset.step ) === n;
			s.hidden = ! active;
			s.classList.toggle( 'is-active', active );
		} );
		progress.forEach( ( p ) => {
			const i = Number( p.dataset.progress );
			p.classList.toggle( 'is-active', i === n );
			p.classList.toggle( 'is-done', i < n );
		} );
		btnPrev.hidden = n === 1;
		btnNext.hidden = n === steps.length;
		btnSubmit.hidden = n !== steps.length;
		showError( '' );
		const heading = steps[ n - 1 ].querySelector( 'h2' );
		if ( heading ) {
			heading.setAttribute( 'tabindex', '-1' );
			heading.focus( { preventScroll: true } );
		}
		root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function applyMode() {
		const m = mode();
		root.querySelectorAll( '[data-mode]' ).forEach( ( el ) => ( el.hidden = el.dataset.mode !== m ) );
		root.querySelector( '[data-label-hour]' ).hidden = m !== 'hour';
		root.querySelector( '[data-label-day]' ).hidden = m !== 'day';

		root.querySelectorAll( '.apr-item[data-type="canoe"]' ).forEach( ( el ) => {
			const c = canoeById[ el.dataset.id ];
			const allowed = m === 'hour' ? c.allow_hour : c.allow_day;
			el.classList.toggle( 'is-disabled', ! allowed );
			el.querySelectorAll( 'input, button' ).forEach( ( b ) => ( b.disabled = ! allowed ) );
			if ( ! allowed ) {
				el.querySelector( '.apr-item__avail' ).textContent = m === 'hour' ? t.onlyDay : t.onlyHour;
			}
		} );
		syncHours();
	}

	function syncHours() {
		const start = toMin( field( 'start_time' ).value || cfg.openTime );
		const close = toMin( cfg.closeTime );
		const sel = field( 'hours' );
		[ ...sel.options ].forEach( ( o ) => ( o.disabled = start + Number( o.value ) * 60 > close ) );
		if ( sel.selectedOptions[ 0 ]?.disabled ) {
			const ok = [ ...sel.options ].filter( ( o ) => ! o.disabled ).pop();
			if ( ok ) {
				sel.value = ok.value;
			}
		}
	}

	function syncEndDate() {
		const d = field( 'date' ).value;
		const end = field( 'end_date' );
		if ( d ) {
			end.min = d;
			if ( ! end.value || end.value < d ) {
				end.value = d;
			}
		}
	}

	function updateSuggestions() {
		const p = persons();
		root.querySelectorAll( '.apr-item[data-per-person]' ).forEach( ( el ) => {
			const btn = el.querySelector( '[data-suggest]' );
			const input = el.querySelector( 'input' );
			const max = Math.min( p, parseInt( input.max, 10 ) || 99 );
			btn.hidden = p < 1 || ( parseInt( input.value, 10 ) || 0 ) === max;
			btn.textContent = sprintf( t.suggestPerson, max );
			btn.dataset.qty = max;
		} );
	}

	function renderAvailability( q ) {
		const avail = q.availability || { canoes: {}, extras: {} };
		root.querySelectorAll( '.apr-item' ).forEach( ( el ) => {
			if ( el.classList.contains( 'is-disabled' ) ) {
				return;
			}
			const map = el.dataset.type === 'canoe' ? avail.canoes : avail.extras;
			const label = el.querySelector( '.apr-item__avail' );
			const input = el.querySelector( 'input' );
			const plus = el.querySelector( '[data-delta="1"]' );
			if ( ! q.period || ! ( el.dataset.id in map ) ) {
				label.textContent = '';
				return;
			}
			const free = map[ el.dataset.id ];
			const qty = parseInt( input.value, 10 ) || 0;
			const hardMax = el.dataset.type === 'extra' ? extraById[ el.dataset.id ]?.max_per_booking || 99 : 99;
			const max = free === null ? hardMax : Math.min( free, hardMax );
			input.max = max;
			plus.disabled = qty >= max;
			el.classList.toggle( 'is-soldout', free === 0 );
			el.classList.toggle( 'is-over', qty > max );
			label.textContent = free === null ? t.unlimited : free === 0 ? t.soldOut : sprintf( t.available, free );

			if ( el.dataset.type === 'canoe' ) {
				const c = canoeById[ el.dataset.id ];
				if ( mode() === 'hour' && c.min_hours > 1 ) {
					label.textContent += ' · ' + sprintf( t.minHours, c.min_hours );
				}
			}
		} );
	}

	function renderSummary( q ) {
		const lines = summary.querySelector( '.apr-summary__lines' );
		const total = summary.querySelector( '.apr-summary__total' );
		const empty = summary.querySelector( '.apr-summary__empty' );
		summary.querySelector( '.apr-summary__period' ).textContent = q?.period?.label || '';
		lines.innerHTML = '';
		( q?.lines || [] ).forEach( ( l ) => {
			const li = document.createElement( 'li' );
			const name = document.createElement( 'span' );
			const price = document.createElement( 'span' );
			name.textContent = `${ l.qty } × ${ l.title }`;
			price.textContent = l.line_total > 0 ? fmt( l.line_total ) : '–';
			li.append( name, price );
			lines.append( li );
		} );
		const hasLines = !! q?.lines?.length;
		empty.hidden = hasLines;
		total.hidden = ! hasLines;
		total.querySelector( 'strong' ).textContent = hasLines ? fmt( q.total ) : '';
	}

	/* ---------- server ---------- */

	async function api( path, body, headers = {} ) {
		const res = await fetch( cfg.restUrl + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', ...headers },
			credentials: 'same-origin',
			body: JSON.stringify( body ),
		} );
		const data = await res.json().catch( () => ( {} ) );
		return { ok: res.ok, status: res.status, data };
	}

	async function refreshQuote() {
		const seq = ++quoteSeq;
		if ( ! field( 'date' ).value ) {
			lastQuote = null;
			renderSummary( null );
			return null;
		}
		summary.classList.add( 'is-loading' );
		try {
			const { data } = await api( 'quote', payload() );
			if ( seq !== quoteSeq ) {
				return lastQuote; // a newer request is in flight
			}
			lastQuote = data;
			renderAvailability( data );
			renderSummary( data );
			updateSuggestions();
			return data;
		} catch ( e ) {
			showError( t.networkError );
			return null;
		} finally {
			if ( seq === quoteSeq ) {
				summary.classList.remove( 'is-loading' );
			}
		}
	}

	function scheduleQuote() {
		clearTimeout( quoteTimer );
		quoteTimer = setTimeout( refreshQuote, 250 );
	}

	/* ---------- navigation ---------- */

	async function validateStep( n ) {
		if ( n === 1 ) {
			if ( ! field( 'date' ).value ) {
				field( 'date' ).focus();
				return t.fillRequired;
			}
			const q = await refreshQuote();
			if ( ! q ) {
				return t.networkError;
			}
			// Without canoes the quote is "invalid" but the period may still be fine.
			return q.period ? '' : ( q.errors || [] ).join( ' ' );
		}
		if ( n === 2 || n === 3 ) {
			if ( ! Object.keys( quantities( 'canoe' ) ).length ) {
				return t.chooseCanoe;
			}
			const q = await refreshQuote();
			return q && q.valid ? '' : ( q?.errors || [ t.networkError ] ).join( ' ' );
		}
		if ( n === 4 ) {
			const required = [ 'name', 'email', 'phone', 'terms' ].map( field );
			const invalid = required.find( ( el ) => ! el.checkValidity() );
			if ( invalid ) {
				invalid.focus();
				return t.fillRequired;
			}
		}
		return '';
	}

	btnNext.addEventListener( 'click', async () => {
		btnNext.disabled = true;
		const err = await validateStep( step );
		btnNext.disabled = false;
		if ( err ) {
			showError( err );
			return;
		}
		goTo( step + 1 );
	} );

	btnPrev.addEventListener( 'click', () => goTo( Math.max( 1, step - 1 ) ) );

	form.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		for ( let n = 1; n <= steps.length; n++ ) {
			const err = await validateStep( n );
			if ( err ) {
				if ( n !== step ) {
					goTo( n );
				}
				showError( err );
				return;
			}
		}
		btnSubmit.disabled = true;
		btnSubmit.dataset.label = btnSubmit.textContent;
		btnSubmit.textContent = t.submitting;
		try {
			const body = {
				...payload(),
				customer: {
					name: field( 'name' ).value,
					email: field( 'email' ).value,
					phone: field( 'phone' ).value,
					notes: field( 'notes' ).value,
				},
				terms: field( 'terms' ).checked,
				website: field( 'website' ).value,
			};
			const { ok, data } = await api( 'bookings', body, { 'X-AP-Nonce': cfg.nonce } );
			if ( ! ok ) {
				showError( data.message || t.networkError );
				if ( data.data?.quote ) {
					renderAvailability( data.data.quote );
					renderSummary( data.data.quote );
				}
				return;
			}
			if ( data.redirect_url ) {
				window.location.href = data.redirect_url;
				return;
			}
			showSuccess( data );
		} catch ( err ) {
			showError( t.networkError );
		} finally {
			btnSubmit.disabled = false;
			btnSubmit.textContent = btnSubmit.dataset.label;
		}
	} );

	function showSuccess( data ) {
		const box = root.querySelector( '.apr-success' );
		root.querySelector( '.apr-layout' ).hidden = true;
		root.querySelector( '.apr-progress' ).hidden = true;
		box.querySelector( '.apr-success__ref' ).innerHTML = '';
		const strong = document.createElement( 'strong' );
		strong.textContent = data.ref;
		box.querySelector( '.apr-success__ref' ).append( strong, ` · ${ data.status_label }` );
		box.querySelector( '.apr-success__details' ).textContent = `${ data.period } · ${ data.total }`;
		box.querySelector( '.apr-success__message' ).textContent = data.message || '';
		box.hidden = false;
		box.focus();
		box.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	/* ---------- events ---------- */

	root.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '.apr-qty__btn' );
		if ( btn ) {
			const input = btn.parentElement.querySelector( 'input' );
			const max = parseInt( input.max, 10 );
			let v = ( parseInt( input.value, 10 ) || 0 ) + Number( btn.dataset.delta );
			v = Math.max( 0, isNaN( max ) ? v : Math.min( max, v ) );
			input.value = v;
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			return;
		}
		const suggest = e.target.closest( '[data-suggest]' );
		if ( suggest ) {
			const input = suggest.closest( '.apr-item' ).querySelector( 'input' );
			input.value = suggest.dataset.qty;
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	} );

	form.addEventListener( 'change', ( e ) => {
		const name = e.target.name;
		if ( name === 'mode' ) {
			applyMode();
		}
		if ( name === 'start_time' ) {
			syncHours();
		}
		if ( name === 'date' ) {
			syncEndDate();
		}
		if ( e.target.closest( '.apr-item' ) ) {
			e.target.closest( '.apr-item' ).classList.toggle( 'is-selected', ( parseInt( e.target.value, 10 ) || 0 ) > 0 );
			updateSuggestions();
		}
		if ( [ 'mode', 'date', 'end_date', 'start_time', 'hours' ].includes( name ) || e.target.closest( '.apr-item' ) ) {
			scheduleQuote();
		}
	} );

	// init
	root.querySelectorAll( '.apr-item input' ).forEach( ( i ) => i.closest( '.apr-item' ).classList.toggle( 'is-selected', i.value > 0 ) );
	applyMode();
	renderSummary( null );
} )();
