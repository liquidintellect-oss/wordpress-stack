<?php
/**
 * Plugin Name: Hold Meta Signals For Anonymous Cacheable Requests
 * Description: Prevents "Meta for WooCommerce" from emitting a server-side
 *              Set-Cookie header (_fbp/_fbc, via its CAPI ParamBuilder) on
 *              anonymous, cacheable page views, so FastCGI caching can work.
 *              Backs off immediately for any request carrying a logged-in
 *              or WooCommerce session cookie, so login/cart/checkout cookie
 *              flows are completely unaffected.
 *
 * Background
 * ----------
 * nginx's fastcgi_cache module refuses to store any response that carries a
 * Set-Cookie header (unless fastcgi_ignore_headers Set-Cookie is set, which
 * would blindly cache/replay that header for every visitor — not what we
 * want). "Meta for WooCommerce" (facebook-for-woocommerce) sets a Set-Cookie
 * header on *every* anonymous pageview to seed _fbp/_fbc for its
 * Conversions API integration, which silently defeats page caching on a
 * high-traffic, ad-driven storefront.
 *
 * The plugin already ships a "signals held" mechanism
 * (WooCommerce\Facebook\Events\FacebookSignalsState, filter
 * `facebook_signals_held`) intended for a CMP/theme integration to
 * suppress cookie writes. Nothing wires it up out of the box, so we do it
 * here for the one case where suppressing it is provably safe: a plain GET
 * request with no logged-in or WooCommerce session cookie present yet.
 *
 * This only stops the *server-side* CAPI cookie write for that pageview —
 * the client-side Facebook Pixel JS (fbq()) is untouched, and this filter
 * defers to the plugin's normal behavior the instant a real session
 * (login, cart, checkout) exists.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'facebook_signals_held',
	function ( $held ) {
		// Already held for some other reason (e.g. a CMP consent gate) — leave it.
		if ( $held ) {
			return $held;
		}

		// Never touch wp-admin, AJAX, REST, or non-GET requests (form posts,
		// add-to-cart, checkout submission, etc.) — let the plugin behave
		// exactly as it does today for anything but a plain page view.
		if ( is_admin() ) {
			return $held;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method ) {
			return $held;
		}

		// If a real session is already in progress — logged-in user or an
		// active WooCommerce cart/session — do not hold signals. Those
		// requests are already excluded from the FastCGI cache (see
		// nginx/templates/default.conf.template `$skip_cache` rules), so
		// there is nothing to gain, and full server-side tracking should
		// keep working for them exactly as before.
		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			if ( 0 === strpos( $cookie_name, 'wordpress_logged_in_' )
				|| 0 === strpos( $cookie_name, 'wp_woocommerce_session_' ) ) {
				return $held;
			}
		}

		// Pure anonymous page view: hold signals so no Set-Cookie header is
		// emitted, allowing this response to be written to the FastCGI cache.
		return true;
	}
);
