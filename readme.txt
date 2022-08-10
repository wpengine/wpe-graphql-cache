=== WP Engine Graphql Add Ons ===
Contributors: markkelnar
Tags: graphql, wp-graphql
Requires at least: 4.5
Tested up to: 5.8.3
Requires PHP: 7.1
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This plugin is used to optimize the caching experience of graphql GET requests to the WP site at WP Engine's hosted WordPress product.

Works with Apollo Persisted Queries JavaScript client.

Optimized your wp-graphql requests and Javascript client by using GET for your graphql requests to the WP. At WP Engine, these requests will be optimized in WP Engine's EverCache environment.

This plugin integrates with the [WPGraphQL Smart Cache](https://github.com/wp-graphql/wp-graphql-smart-cache) plugin to correctly invalidate cached data when it changes in your plugin.

== Changelog ==

= 0.1.3 =

- fixes bug where undefined function was being called.

= 0.1.2 =

- use action from WPGraphQL Smart Cache to save urls to collection

= 0.1.1 =

- Updated to work with WPGraphQL Smart Cache v0.1.1
