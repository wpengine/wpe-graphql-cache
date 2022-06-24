<?php
/**
 * Plugin Name:     WP Engine Graphql Cache
 * Plugin URI:      https://wpengine.com
 * Description:     WP Engine add ons for wp-graphql in the WP Engine hosted WP environment
 * Author:          Mark Kelnar
 * Text Domain:     wpe-graphql-cache
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Wpe_Graphql
 */

namespace WPEngine\Graphql;

use WPGraphQL\Labs\Cache\Collection;
use WPGraphQL\Labs\Admin\Settings;
use GraphQLRelay\Relay;

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
			$urls = $collection->retrieve_urls( $request_key );

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
