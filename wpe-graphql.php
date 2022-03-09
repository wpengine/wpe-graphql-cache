<?php
/**
 * Plugin Name:     WP Engine Graphql Add Ons
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     WP Engine add ons for wp-graphql in the WP Engine hosted WP environment
 * Author:          Mark Kelnar
 * Author URI:      YOUR SITE HERE
 * Text Domain:     wpe-graphql
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

/**
 * For wpe, when our varnish cache function is invoked, add to paths being filtered.
 * See the wpengine must-use plugin for the 'wpe_purge_varnish_cache_paths' filter.
 * 
 * @param array    $paths  Path, urls to pages cached in varnish to be purged.
 * @param int   $identifer The requested post_id to purge if one was passed
 *  or string 'wpe-graphql:all', 'wpe-graphql:cG9zdDo1NjQ='
 */
add_filter( 'wpe_purge_varnish_cache_paths', function ( $paths, $identifer ) {

	if ( ! is_array( $paths ) ) {
		$paths = [];
	}

	error_log( "Purge Varnish: $identifer " );

	// When any post changes, look up graphql paths previously queried containing post resources and purge those
	$collection = new Collection();
	if ( is_int( $identifer ) ) {
		$id = Relay::toGlobalId( 'post', $identifer );
	} else {
		$id = substr( $identifer, strlen( MAGIC_STRING ) );

		// Do something when we trigger varnish purge with an indicator id
		if ( false === $id ) {
			return $paths;
		}

		// Erase any other wpe paths cause we triggered this and want to purge something specific
		$paths = [];

		if ( 'all' === $id ) {
			// This purges all cached pages at graphql endpoint.
			$paths[] = preg_quote( Settings::graphql_endpoint() );
		}
	}

	$key = $collection->node_key( $id );
	$nodes = $collection->get( $key );
	error_log( "Graphql Purge Post: $key " . print_r($nodes, 1) );

	// Get the list of queries associated with this key
	if ( is_array( $nodes ) ) {
		foreach( $nodes as $request_key ) {
			$urls_key = $collection->url_key( $request_key );
			$urls = $collection->get( $urls_key );

			if ( is_array( $urls ) ) {
				// Add these specific paths to be purged
				foreach ( $urls as $url ) {
					// escape any regex characters
					$paths[] = preg_quote( $url );
				}
			}
		}
	}

	error_log( 'Graphql Purge Paths: ' . print_r($paths, 1) );
	return array_unique( $paths );
}, 10, 2 );

add_action( 'wpgraphql_cache_purge_all', function () {
	/**
	 * Invoke the WPE varnish purge function with specific identifier
	 */
	if ( method_exists( WpeCommon, 'purge_varnish_cache' ) ) {
		error_log( 'Trigger Varnish Purge ' );
		\WpeCommon::purge_varnish_cache( MAGIC_STRING . 'all' );
	}
}, 10 );
