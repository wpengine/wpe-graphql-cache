<?php
namespace Helper;

/**
 * Extend the acceptance tester to send Rest requests to the graphql endpoint.
 * Handle login and save those auth creds as well as add to requests.
 */

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Acceptance extends \Codeception\Module
{
	// The admin login token
	protected $auth_token;

	// Clean up what we created at the end of all the tests in this suite
	public $should_cleanup = true;

	// Use this to delete the saved query
	protected $post_query_id;

	// Use this as the queryId
	protected $post_query_alias;

	// The query string
	protected $post_query_string;

	// The post ids created during tests, and needing cleanup
	protected $created_posts = [];

	// The user ids created during tests, and needing cleanup
	protected $created_users = [];

	public function setUp()
	{
		$this->login();
		$this->haveQueryForSinglePost();
	}

	/**
	 * Send graphql GET request.
	 * TODO: Add vars and operation name as params.
	 *
	 */
	public function sendQuery( $query )
	{
		$this->getModule('REST')->sendGet('graphql', [ 'query' => $query ] );
	}

	public function login()
	{
		// Login and save wordpress login token
		if ( ! $this->auth_token ) {
			$mutation = sprintf( "mutation LoginUser {
				login( input: {
					username: \"%s\",
					password: \"%s\"
				} ) {
					authToken
				}
			}
			", $_ENV['WP_TEST_USERNAME'], $_ENV['WP_TEST_PASSWORD'] );

			$this->getModule('REST')->sendPost('graphql', [ 'query' => $mutation ] );
			$this->getModule('REST')->seeResponseCodeIs(200);

			// Save the token for other
			$this->auth_token = $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.login.authToken')[0];
		}
	}

	/**
	 * Create a saved query for use by this test runner
	 */
	public function haveQueryForSinglePost()
	{
		// This query alias will be used as the queryId in our GET request to check cache requests.
		$this->post_query_alias = uniqid( "test_runner_" );

		$this->post_query_string = sprintf( 'query get_post_%s ($post: ID!) {
			post(id: $post) {
				id
				title
				content
			}
		}', $this->post_query_alias );
		$this->post_query_id = $this->haveSavedQuery( $this->post_query_string, [ $this->post_query_alias ] );
	}

	public function dontHaveQueryForSinglePost () {
		$this->dontHaveSavedQuery( $this->post_query_id );
	}

	public function grabQueryForSinglePost()
	{
		return $this->post_query_string;
	}

	/**
	 * Create a saved query for use by this test runner
	 */
	public function haveSavedQuery( $query, $aliases = [] )
	{
		$mutation = 'mutation MyMutationInsert($input: CreateGraphqlDocumentInput!) {
			createGraphqlDocument(input: $input) {
				graphqlDocument {
					id
				}
			}
		}';
		$vars = [
			"input" => [
				"title" => "Saved query for test runner",
				"content" => $query,
				"alias" => $aliases,
				"status" => "PUBLISH",
			]
		];

		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		return $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.createGraphqlDocument.graphqlDocument.id')[0];
}

	public function dontHaveSavedQuery( $query_id )
	{
		$mutation = 'mutation deleteSavedQuery($var: DeleteGraphqlDocumentInput!) { deleteGraphqlDocument (input: $var) { graphqlDocument { id } } }';
		$vars = [
			"var" => [
				"id" => $query_id
			]
		];
		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
	}

	/**
	 * Create a WP post used for these test scenarios. Will be cleaned up after the suite run completes.
	 *
	 * param $params
	 *  'title' - post_title
	 *  'content' - post_content
	 *  '
	 */
	public function havePost( $params = [] )
	{
		$category = "test-runner";
		$mutation = 'mutation custom ($vars: CreatePostInput!) {
			createPost(input: $vars) {
			  post {
				id
				title
				content
			  }
			}
		  }
		';
		$vars = [
			"vars" => array_merge( [
					"status" => "PUBLISH",
					"categories" => [
						"nodes" => [
							[
								"name" => $category
							]
						],
						"append" => false
					]
				], $params )
		];

		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$response = $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.createPost.post')[0];
		$this->created_posts[] = $response['id'];

		$this->getModule('Asserts')->assertEquals( $params['title'], $response['title'] );
		$this->getModule('Asserts')->assertEquals( "<p>{$params['content']}</p>\n", $response['content'] );

		return $response['id'];
	}

	public function updatePost( $post_id, $params = [] )
	{
		$mutation = 'mutation custom ($vars: UpdatePostInput!) {
			updatePost(input: $vars) {
			  post {
				id
				title
				content
			  }
			}
		  }
		';

		$vars = [
			"vars" => array_merge( [ "id" => $post_id ], $params )
		];
		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$response = $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.updatePost.post')[0];
		$this->getModule('Asserts')->assertEquals( "<p>{$params['content']}</p>\n", $response['content'] );

	}

	public function dontHavePost( $post_id )
	{
		$mutation = 'mutation deletePost($var: DeletePostInput!) { deletePost (input: $var) { deletedId } }';
		$vars = [
			"var" => [
				"id" => $post_id
			]
		];
		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
	}

	/**
	 * Query graphql server and verify single post id exists.
	 * Uses saved queryId mutation.
	 *
	 * param string $post_id post node id
	 */
	public function seePostById( $post_id )
	{
		$vars = [ "post" => $post_id ];
		$this->getModule('REST')->sendGet('graphql', [ 'queryId' => $this->post_query_alias, 'variables' => $vars ] );
	}

	/**
	 * Return the single post from the graphql query.
	 *
	 * return string
	 */
	public function grabDataByPost()
	{
		return $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.post')[0];
	}

	/**
	 * Sends a POST request to given uri with authorization bearer token from login().
	 * Assumes login() already invoked and successful.
	 *
	 * param $url
	 * param array|string|\JsonSerializable $params
	 */
	public function sendAuthenticatedPost( $url, $params )
	{
		$this->getModule('REST')->haveHttpHeader( "Authorization", "Bearer " . $this->auth_token );
		$this->getModule('REST')->sendPost( $url, $params );
		$this->getModule('REST')->seeResponseCodeIs(200);
	}

	/**
	 * Create a WP user for these test scenarios. Will be cleaned up after the suite run completes.
	 *
	 * param $params
	 *  'username'
	 *  'firstName'
	 *  'lastName'
	 */
	public function haveUser( $params = [] )
	{
		$category = "test-runner";
		$mutation = 'mutation custom ($vars: CreateUserInput!) {
			createUser(input: $vars) {
				user {
					id
					userId
					username
					firstName
					lastName
				}
			}
		}
		';
		$vars = [
			"vars" => $params
		];

		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$response = $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.createUser.user')[0];
		$this->created_users[] = $response['userId'];

		$this->getModule('Asserts')->assertEquals( $params['username'], $response['username'] );

		return $response['userId'];
	}

	public function updateUser( $user_id, $params = [] )
	{
		$mutation = 'mutation custom ($vars: UpdateUserInput!) {
			updateUser(input: $vars) {
			  user {
				userId
				username
			  }
			}
		  }
		';

		$vars = [
			"vars" => array_merge( [ 'id' => $user_id ], $params )
		];
		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$response = $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.updateUser.user')[0];

		$this->getModule('Asserts')->assertEquals( $user_id, $response['userId'] );

	}

	public function dontHaveUser( $user_id, $params = [] )
	{
		$mutation = 'mutation deleteUser($var: DeleteUserInput!) { deleteUser (input: $var) { deletedId } }';
		$vars = [
			"var" => array_merge( [ 'id' => $user_id ], $params )
		];
		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
	}

	/**
	 * Call at the end of the suite run
	 */
	public function cleanUp()
	{
		if ( $this->should_cleanup ) {
			codecept_debug( "Cleanup, after" );

			if ( $this->created_posts ) {
				foreach ( $this->created_posts as $post_id ) {
					$this->dontHavePost( $post_id );
				}
			}

			if ( $this->post_query_id ) {
				$this->dontHaveQueryForSinglePost();
				$this->post_query_id = null;
			}

			if ( $this->created_users ) {
				foreach ( $this->created_users as $user_id ) {
					$this->dontHaveUser( $user_id );
				}
			}
		}
	}
}
