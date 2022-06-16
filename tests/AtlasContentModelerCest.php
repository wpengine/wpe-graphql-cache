<?php
/**
 * This collection of tests is intended to:
 *  create a post
 *  query the post, maybe multiple times
 *  verify content cache hit count
 *  update the post
 *  query the post and verify the cache hit count
 * 
 * To run these test, make sure in the WP you have:
 *  - installed and activated Atlas Content Modeler
 *  - imported the model json file from this repo
 * 
 */
class AtlasContentModelerCest
{
	public $my_unique_id;
	public $saved_query_string;
	public $saved_query_id;

	public function SetupAtTheBeginning( AcceptanceTester $I )
	{
		$I->setUp();

		// This query alias will be used as the queryId in our GET request to check cache requests.
		$this->my_unique_id = uniqid( "test_acm_" );

		$this->post_query_string = sprintf( 'query %s {
			foos {
				nodes {
					id
					name
					description
				}
			}
		}
		', $this->my_unique_id );
		$this->saved_query_id = $I->haveSavedQuery( $this->post_query_string, [ $this->my_unique_id ] );
	}

	/**
	 * While creating a post used for these other tests
	*/
	public function CreatePost( AcceptanceTester $I )
	{
		$mutation = 'mutation CreateFoo {
			createFoo (
				input: {
					status: PUBLISH
					name: "lorem"
					description: "lorem ipsum"
				}
			) {
				foo {
					id
					name
					description
				}
			}
		}
		';
		$vars = [
			"vars" => [
				"status" => "PUBLISH",
				"name" => "lorem",
				"description" => "<p>lorem ipsum</p>"
			]
		];

		$I->sendAuthenticatedPost('graphql', [ 'query' => $mutation ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.createFoo.foo')[0];
		$this->created_post_id = $response['id'];

		$I->assertEquals("lorem", $response['name'] );
		$I->assertEquals( "lorem ipsum", $response['description'] );
	}

	public function VerifySeeCacheHeaderTest( AcceptanceTester $I )
	{
		// Make two queries. Verify the second one is cached.
		$I->sendGet('graphql', [ 'queryId' => $this->my_unique_id ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.foos.nodes')[0];
		$I->seeHttpHeader('X-Cache', 'MISS');
		$I->assertEquals( 'lorem ipsum', $response[0]['description'] );

		$I->sendGet('graphql', [ 'queryId' => $this->my_unique_id ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.foos.nodes')[0];
		$I->seeHttpHeader('X-Cache', 'HIT: 1');
		$I->assertEquals( 'lorem ipsum', $response[0]['description'] );
	}

	/**
	 * I want to change the specific post content to initiate a cache purge
	 */
	public function UpdateThePostTest( AcceptanceTester $I )
	{
		//$params['content'] = $this->post_content_1;
		//$I->updatePost( $this->post_id_1, $params );
		$mutation = 'mutation thing ($var: UpdateFooInput!) {
			updateFoo (input: $var) {
				foo {
					id
					name
					description
				}
			}
		}';
		$vars = [
			"var" => [
				"id" => $this->created_post_id,
				"description" => "updated ipsum"
			]
		];
		$I->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );
	}

	public function VerifySeeCacheMissAfterUpdateTest( AcceptanceTester $I )
	{
		$I->sendGet('graphql', [ 'queryId' => $this->my_unique_id ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.foos.nodes')[0];
		$I->seeHttpHeader('X-Cache', 'MISS');
		$I->assertEquals( 'updated ipsum', $response[0]['description'] );
	}
	
	public function CleanUpAtTheEnd( AcceptanceTester $I )
	{
		$mutation = 'mutation deleteFoo($var: DeleteFooInput!) { deleteFoo (input: $var) { deletedId } }';
		$vars = [
			"var" => [
				"id" => $this->created_post_id
			]
		];
		$I->sendAuthenticatedPost('graphql', [ 'query' => $mutation, 'variables' => $vars ] );

		$I->dontHaveSavedQuery( $this->saved_query_id );
		$I->cleanUp();
	}
}
