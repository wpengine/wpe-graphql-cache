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
use GraphQLRelay\Relay;

/**
 * For wpe, when our varnish cache function is invoked, add to paths being filtered.
 * See the wpengine must-use plugin for the 'wpe_purge_varnish_cache_paths' filter.
 * 
 * @param array    $paths  Path, urls to pages cached in varnish to be purged.
 * @param WP_Post  $post_id   Post object id.
 */
add_filter( 'wpe_purge_varnish_cache_paths', function ( $paths, $post_id ) {
	
	if ( ! is_array( $paths ) ) {
		$paths = [];
	}

	// This purges all cached pages at graphql endpoint.
	//$add_paths[] = Settings::graphql_endpoint();

	// When any post changes, look up graphql paths previously queried containing post resources and purge those
	$collection = new Collection();
	//$key   = $collection->node_key( 'post' );
	$id = Relay::toGlobalId( 'post', $post_id );
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
