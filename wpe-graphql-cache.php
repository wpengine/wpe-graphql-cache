<?php
/**
 * Plugin Name:     WP Engine Graphql Cache
 * Plugin URI:      https://wpengine.com
 * Description:     WP Engine add ons for wp-graphql in the WP Engine hosted WP environment
 * Author:          Mark Kelnar
 * Text Domain:     wpe-graphql-cache
 * Domain Path:     /languages
 * Version:         0.1.3
 *
 * @package         Wpe_Graphql
 */

namespace WPEngine\Graphql;

use WPGraphQL\SmartCache\Cache\Collection;
use WPGraphQL\SmartCache\Admin\Settings;

const MAGIC_STRING = 'wpe-graphql:';

if ( ! function_exists( __NAMESPACE__ . 'log' ) ) {
	function log( $msg ) {
		if ( defined('GRAPHQL_DEBUG_ENABLED') ) {
			error_log( $msg );
			graphql_debug( $msg, $config[ 'debug' ] );
		}
	}
}

/**
 * For wpe, when our varnish cache function is invoked, add to paths being filtered.
 * See the wpengine must-use plugin for the 'wpe_purge_varnish_cache_paths' filter.
 *
 * @param array    $paths  Path, urls to pages cached in varnish to be purged.
 * @param int   $identifier The requested post_id to purge if one was passed
 *  or string 'wpe-graphql:all', 'wpe-graphql:cG9zdDo1NjQ='
 */
add_filter( 'wpe_purge_varnish_cache_paths', function ( $paths, $identifier ) {

	// If the id doesn't start with our magic string, that means we didn't initiate the purge.
	$id = substr( $identifier, 0, strlen( MAGIC_STRING ) );
	if ( MAGIC_STRING !== $id ) {
		// If not initiated by us, return
		return $paths;
	}

	log( "WpeGraphql Purge Varnish: $identifier " );

	// Get the rest of the string after our magic string
	$id = substr( $identifier, strlen( MAGIC_STRING ) );

	if ( 'all' === $id ) {
		// This purges all cached pages at graphql endpoint.
		return [ preg_quote( Settings::graphql_endpoint() ) ];
	}

	// Erase any other paths cause we triggered this and want to purge something specific.
	$paths = [];

	$collection = new Collection();
	$nodes = $collection->get( $id );
	log( "WpeGraphql Purge Post: $id " . print_r($nodes, 1) );

	// Get the list of queries associated with this key
	// Look up graphql path/urls previously queried containing resources and purge those
	if ( is_array( $nodes ) ) {
		foreach( $nodes as $request_key ) {
			$urls = wpe_cache_retrieve_urls( $request_key );

			if ( is_array( $urls ) ) {
				// Add these specific paths to be purged
				foreach ( $urls as $url ) {
					// The saved url was raw, unencoded. quote/escape any regex characters in the path for varnish to purge.
					$paths[] = preg_quote( $url );
				}
			}
		}
	}

	// If got here but don't have any actual paths, have to return something, otherwise WPE will purge everything
	if ( empty( $paths ) ) {
		$paths = [ preg_quote( Settings::graphql_endpoint() . '/' . uniqid('--notreal--') ) ];
	}

	log( 'WpeGraphql Purge Paths: ' . print_r($paths, 1) );

	return array_unique( $paths );
}, 10, 2 );

add_action( 'wpgraphql_cache_purge_all', function () {
	/**
	 * Invoke the WPE varnish purge function with specific identifier
	 */
	if ( is_callable( [ 'WpeCommon', 'purge_varnish_cache' ] ) ) {
		log( 'WpeGraphql Trigger Varnish Purge All' );
		// Second argument is 'force'.
		\WpeCommon::purge_varnish_cache( MAGIC_STRING . 'all', true );
	}
}, 10, 0);

add_action( 'wpgraphql_cache_purge_nodes', function ( $id, $nodes ) {
	/**
	 * Invoke the WPE varnish purge function with specific identifier
	 */
	if ( is_callable( [ 'WpeCommon', 'purge_varnish_cache' ] ) ) {
		log( 'WpeGraphql Trigger Varnish Purge '. $id );
		// Second argument is 'force'.
		\WpeCommon::purge_varnish_cache( MAGIC_STRING . $id, true );
		log( 'WpeGraphql Trigger Varnish Purge - After '. $id );
	}
}, 10, 2);


add_action( 'wpgraphql_cache_save_request', function( $request_key ) {
	// Only store mappings of urls when it's a GET request
	$map_the_url = false;
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
		$map_the_url = true;
	}

	// We don't want POSTs during mutations or nothing on the url. cause it'll purge /graphql*
	if ( $map_the_url && ! empty( $_SERVER['REQUEST_URI'] ) ) {
		$url_to_save = wp_unslash( $_SERVER['REQUEST_URI'] );

		// Save the url this query request came in on, so we can purge it later when something changes
		$collection = new Collection();
		$urls = $collection->store_content( wpe_cache_url_key( $request_key ), $url_to_save );

		log( "Graphql Save Urls: $request_key " . print_r( $urls, 1 ) );
	}
}, 10, 1 );

/**
 * When save or retrieve urls for a specific Unique identifier for this request for use in the collection map
 *
 * @param string $id Id for the node
 *
 * @return string unique id for this request
 */
function wpe_cache_url_key( $id ) {
	return 'url:' . $id;
}

/**
 * Get the list of urls associated with the content/node/list id
 *
 * @param mixed|string|int $id The content node identifier
 *
 * @return array The unique list of content stored
 */
function wpe_cache_retrieve_urls( $id ) {
	$key = wpe_cache_url_key( $id );
	$collection = new Collection();
	return $collection->get( $key );
}
