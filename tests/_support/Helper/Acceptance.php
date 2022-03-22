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
	public $auth_token;

	// Clean up what we created at the end of all the tests in this suite
	public $should_cleanup = true;

	// Use this to delete the saved query
	public $saved_query_id;

	// Use this as the queryId
	public $saved_query_alias;

	// The post id created during tests, and needing cleanup
	public $created_post_id;

	public function _beforeSuite($settings = [])
	{
		$this->login();
		$this->saved_query_alias = uniqid( "test_runner_" );
		$this->haveQueryForSinglePost();

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
		if ( ! $this->saved_query_id ) {
			$mutation = 'mutation MyMutationInsert($input: CreateGraphqlDocumentInput!) {
				createGraphqlDocument(input: $input) {
				graphqlDocument {
					id
				}
				}
			}
			';
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

			$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
			$this->saved_query_id = $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.createGraphqlDocument.graphqlDocument.id')[0];
		}
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
			"vars" => [
				"title" => $params['title'],
				"content" => $params['content'],
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

		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$response = $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.createPost.post')[0];
		$this->created_post_id = $response['id'];

		$this->getModule('Asserts')->assertEquals( $params['title'], $response['title'] );
		$this->getModule('Asserts')->assertEquals( "<p>{$params['content']}</p>\n", $response['content'] );

		return $this->created_post_id;
	}

	public function updatePost( $post_id, $params )
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
			"vars" => [
				"id" => $post_id,
				"content" => $params['content'],
			]
		];
		$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
		$response = $this->getModule('REST')->grabDataFromResponseByJsonPath('$..data.updatePost.post')[0];
		$this->getModule('Asserts')->assertEquals( "<p>{$params['content']}</p>\n", $response['content'] );

	}

	/**
	 * Query graphql server and verify single post id exists.
	 * Uses saved queryId mutation.
	 *
	 * param $url
	 * param array $params
	 */
	public function seePostById( $post_id )
	{
		$vars = [ "post" => $post_id ];
		$this->getModule('REST')->sendGet('graphql', [ 'queryId' => $this->saved_query_alias, 'variables' => $vars ] );
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
	 * Call at the end of the suite run
	 */
	public function cleanUp()
	{
		codecept_debug("TEARDOWN");

		if ( $this->should_cleanup ) {
			codecept_debug( "Cleanup, after" );

			if ( $this->created_post_id ) {
				$mutation = 'mutation deletePost($var: DeletePostInput!) { deletePost (input: $var) { deletedId } }';
				$vars = [
					"var" => [
						"id" => $this->created_post_id
					]
				];
				codecept_debug( "Cleanup, after" );
				$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
				codecept_debug( $this->getModule('REST')->grabResponse() );
				$this->created_post_id = null;
			}

			if ( $this->saved_query_id ) {
				codecept_debug( $this->saved_query_id );
				$mutation = 'mutation deleteSavedQuery($var: DeleteGraphqlDocumentInput!) { deleteGraphqlDocument (input: $var) { graphqlDocument { id } } }';
				$vars = [
					"var" => [
						"id" => $this->saved_query_id
					]
				];
				codecept_debug( "Cleanup, after" );
				$this->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
				codecept_debug( $this->getModule('REST')->grabResponse() );
				$this->saved_query_id = null;
			}
		}

	}
}
