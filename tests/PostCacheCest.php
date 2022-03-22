<?php
/**
 * WP site must have jwt support. Install wp-graphql-jwt-authentication on the serving WP.
 * Add graphql_jwt_auth_secret_key for that auth plugin to work.
 * 
 * Set up test user with name and password to use for these tests.
 * Role for this user should be 'editor' priveleges. This allows categories to be created with post is inserted.
 * 
 * This collection of tests is intended to:
 *  create a post
 *  query the post, maybe multiple times
 *  verify content cache hit count
 *  update the post
 *  query the post and verify the cache hit count
 *  purge the cache
 *  query the post and verify the cache hit count
 * 
 */
class PostCacheCest
{
	// The admin login token
	public $auth_token;

	// The post id created during tests, and needing cleanup
	public $post_id;

	// Clean up what we created at the end of all the tests in this suite
	public $should_cleanup = true;

	// Use this to delete the saved query
	public $saved_query_id;

	// Use this as the queryId
	public $saved_query_alias;

	// Use the following data on example post create/update
	public $post_title = "Cache Test Runner";
	public $post_content_0 = "initial content";
	public $post_content_1 = "secondary data";

	public function _login(AcceptanceTester $I)
	{
		// Login and save wordpress login token
		if ( ! $this->auth_token ) {
			$mutation = sprintf( "mutation LoginUser {
				login( input: {
					username: \"%s\",
					password: \"%s\"
				} ) {
				authToken
					user {
						id
						name
					}
				}
			}
			", $_ENV['WP_TEST_USERNAME'], $_ENV['WP_TEST_PASSWORD'] );

			$I->sendPost('graphql', [ 'query' => $mutation ] );

			// Save the token for other
			$this->auth_token = $I->grabDataFromResponseByJsonPath('$..data.login.authToken')[0];
			codecept_debug($this->auth_token);
		}
	}

	/**
	 * Create a saved query for use by this test runner
	 */
	public function SetUpTheSavedQuery( AcceptanceTester $I )
	{
		$this->_login( $I );

		$I->haveHttpHeader( "Authorization", "Bearer " . $this->auth_token );

		$mutation = 'mutation MyMutationInsert($input: CreateGraphqlDocumentInput!) {
			createGraphqlDocument(input: $input) {
			  graphqlDocument {
				id
			  }
			}
		  }
		';
		$this->saved_query_alias = uniqid( "test_runner_" );
		$vars = [
			"input" => [
				"title" => "Saved query for test runner",
				"content" => sprintf( 'query get_%s ($post: ID!) {
					post(id: $post) {
					  id
					  title
					  content
					}
				}', $this->saved_query_alias),
				"alias" => [
					$this->saved_query_alias
				],
				"status" => "PUBLISH",
			]
		];
		
		$I->sendPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$this->saved_query_id = $I->grabDataFromResponseByJsonPath('$..data.createGraphqlDocument.graphqlDocument.id')[0];
	}

	/**
	 * While creating a post used for these other tests
	*/
	public function CreatePost( AcceptanceTester $I )
	{
		$this->_login( $I );

		// Create a post
		$I->haveHttpHeader( "Authorization", "Bearer " . $this->auth_token );
		$mutation = 'mutation custom ($input: CreatePostInput!) {
			createPost(input: $input) {
			  post {
				content
				id
				title
			  }
			}
		  }
		';
		$category = "test-runner";
		$vars = [
			"input" => [
				"title" => $this->post_title,
				"content" => $this->post_content_0,
				"status" => "PUBLISH",
				"categories" => [
					"nodes" => [
						[
							"name" => $category
						]
					],
					"append" => false
				]
			]
		];

		$I->sendPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.createPost.post')[0];
		$this->post_id = $response['id'];

		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_0}</p>\n", $response['content'] );
	}

	/**
	 * Query the specific post and check the cache hit counter
	 */
	public function VerifySeeCacheHeaderTest( AcceptanceTester $I )
	{
		// Query the specific post with GET
		$vars = [ "post" => $this->post_id ];
		$I->sendGet('graphql', [ 'queryId' => $this->saved_query_alias, 'variables' => $vars ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.post')[0];
		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_0}</p>\n", $response['content'] );
		$I->seeHttpHeader('X-Cache', 'MISS');

		// Query again and see the cached version
		$I->sendGet('graphql', [ 'queryId' => $this->saved_query_alias, 'variables' => $vars ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.post')[0];
		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_0}</p>\n", $response['content'] );
		$I->seeHttpHeader('X-Cache', 'HIT: 1');
	}

	/**
	 * I want to change the specific post content to initiate a cache purge
	 */
	public function UpdateThePostTest( AcceptanceTester $I )
	{
		// Update the post
		$I->haveHttpHeader( "Authorization", "Bearer " . $this->auth_token );
		$mutation = 'mutation custom ($update: UpdatePostInput!) {
			updatePost(input: $update) {
			  post {
				id
				title
				content  
			  }
			}
		  }
		';

		$vars = [
			"update" => [
				"id" => $this->post_id,
				"content" => $this->post_content_1,
			]
		];
		$I->sendPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.updatePost.post')[0];
		$I->assertEquals( "<p>{$this->post_content_1}</p>\n", $response['content'] );
	}

	/**
	 * After changing the post content, verify cache miss
	 */
	public function CheckForCacheMissTest( AcceptanceTester $I )
	{
		// Query and check for cache miss
		$vars = [ "post" => $this->post_id ];
		$I->sendGet('graphql', [ 'queryId' => $this->saved_query_alias, 'variables' => $vars ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.post')[0];

		$I->seeHttpHeader('X-Cache', 'MISS');
		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_1}</p>\n", $response['content'] );
	}

	/**
	 * At the end of all tests, call this teardown
	 */
	public function tearDown(AcceptanceTester $I)
	{
		codecept_debug("TEARDOWN");

		if ( $this->should_cleanup ) {
			codecept_debug( "Cleanup, after" );
	
			$this->_login( $I );
			$I->haveHttpHeader( "Authorization", "Bearer " . $this->auth_token );

			if ( $this->post_id ) {
				codecept_debug( $this->post_id );
				$mutation = 'mutation deletePost($var: DeletePostInput!) { deletePost (input: $var) { deletedId } }';
				$vars = [
					"var" => [
						"id" => $this->post_id
					]
				];
				$I->sendPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
				codecept_debug( $I->grabResponse() );
				$this->post_id = null;
			}

			if ( $this->saved_query_id ) {
				codecept_debug( $this->saved_query_id );
				$mutation = 'mutation deleteSavedQuery($var: DeleteGraphqlDocumentInput!) { deleteGraphqlDocument (input: $var) graphqlDocument { id } } ';
				$vars = [
					"var" => [
						"id" => $this->saved_query_id
					]
				];
				$I->sendPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
				codecept_debug( $I->grabResponse() );
				$this->saved_query_id = null;
			}
		}
	}

}