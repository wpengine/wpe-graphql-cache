# Welcome

This plugin is used to optimize the caching experience of graphql GET requests to the WP site at WP Engine's hosted WordPress product.

Use the steps in this readme to configure for each WP.

## Setup the WP service

Install and activate the following plugins in the WP at WP Engine:

- [wp-graphql](https://github.com/wp-graphql/wp-graphql)
- [wp-graphql-labs](https://github.com/wp-graphql/wp-graphql-labs)
- [wpe-graphql-cache](https://github.com/wpengine/wpe-graphql-cache)

## Use GET requests

Make a graphql request to the graphql endpoint and verify the cache header. Make multiple requests to see the `Hit` counter increase.

Change data that is included in the previous graphql request.

Make the graphql request again and verify the cache header is a `Miss`, then the hit counter starts over at 1.

## Use saved query names/aliases

Instead of passing the full graphql 'query { string }' on the url for the request, with the [wp-graphql-labs](https://github.com/wp-graphql/wp-graphql-labs) plugin, you can make use of easy to remember query names instead.

For example,

```
https://content.example.com/graphql?query=query my_query { posts(where: {tag: %22coffee%22}) { nodes { id } } }
```

Could be saved in the system and queried as

```
https://content.example.com/graphql?queryId=posts-about-coffee
```

To create queries, see documentation at the [wp-graphql-labs](https://github.com/wp-graphql/wp-graphql-labs/) plugin.

Queries can be created using the GraphiQL IDE editor, using graphql mutations or the wp-admin editor (enable the setting to show queries in the wp-admin menu).

## Caching for queries

The system is able to identify queries on the url or by query id alias name, that are identical and cache accordingly. As well as invalidate the cache as needed.  This insures that queries that are the same, are not returning different results.


## Automated testing

You can use this plugin development code to verify caching is running for your WPE site as expeceted as well as write more tests.

Tests are written using codeception, uses a special user and creds that you must create and are run locally against the domain you configure.

To use this testing against your WPE site, follow these steps:

### JWT Authentication

WP site must have jwt support. Install [wp-graphql-jwt-authentication](https://github.com/wp-graphql/wp-graphql-jwt-authentication) on the serving WP. Add graphql_jwt_auth_secret_key for that auth plugin to work.

- Add a PHP define variable for the JWT secret key or a filter like the following.

```
define( 'GRAPHQL_JWT_AUTH_SECRET_KEY', 'your-secret-token' );
```

```
add_filter( 'graphql_jwt_auth_secret_key', function() {
  return 'your-secret-token';
});
```

### Create a Test User

Log into wp-admin for the site and create a new user specific for these tests. Example user name `test-runner`. Record the username and password for use in later steps.  Role for this user should be 'editor' priveleges. This allows categories to be created with post is inserted.

### Local environment

- Checkout this repo to your development environment.

- Copy the .env.testing file variables to .env. Change the variable values for the test user and url or the WPE hosted WP site/domain

```
WP_URL=https://content.example.com
WP_TEST_USERNAME=test-runner
WP_TEST_PASSWORD=secret-password
```

- Build the dev software locally

`composer update`

or

`docker run -v $PWD:/app composer update`

- Run the tests using one of the following.

`php vendor/bin/codecept run --steps --debug`

`docker run --workdir /app -v $PWD:/app php vendor/bin/codecept run`

`docker run --workdir /app -v $PWD:/app php vendor/bin/codecept run --steps --debug`

Posts and queries created during testing should clean up at the end of the tests, even in the case of failure.
