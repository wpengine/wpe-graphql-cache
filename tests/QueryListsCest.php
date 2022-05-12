<?php
/**
 * This collection of tests is intended to:
 *  create a post
 *  query posts
 *  verify content cache hit count
 *  create a new post
 *  query posts
 *  verify cache miss
 * 
 */
class QueryListsCest
{
	public $my_unique_id;
	public $saved_query_string;
	public $saved_query_id;

	public function SetupAtTheBeginning( AcceptanceTester $I )
	{
		$I->setUp();

		// This query alias will be used as the queryId in our GET request to check cache requests.
		$this->my_unique_id = uniqid( "test_posts_" );

		$this->saved_query_string = sprintf( 'query %s {
			posts {
				nodes {
					id
					title
					content
				}
			}
		  }
		', $this->my_unique_id );
		$this->saved_query_id = $I->haveSavedQuery( $this->saved_query_string, [ $this->my_unique_id ] );

		$post['title'] = 'Test Runner Posts ' . $this->my_unique_id;
		$post['content'] = 'Content for test runner ';
		$I->havePost( $post );
	}

	public function VerifySeeCacheHeaderTest( AcceptanceTester $I )
	{
		// Make two queries. Verify the second one is cached.
		$I->sendGet('graphql', [ 'queryId' => $this->my_unique_id ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.posts.nodes')[0];
		$I->seeHttpHeader('X-Cache', 'MISS');
		$I->assertEquals( 'Test Runner Posts ' . $this->my_unique_id, $response[0]['title'] );

		$I->sendGet('graphql', [ 'queryId' => $this->my_unique_id ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.posts.nodes')[0];
		$I->seeHttpHeader('X-Cache', 'HIT: 1');
		$I->assertEquals( 'Test Runner Posts ' . $this->my_unique_id, $response[0]['title'] );
	}

	public function CreateNewPostTest( AcceptanceTester $I )
	{
		$post['title'] = 'Test Runner Posts 2 ' . $this->my_unique_id;
		$post['content'] = 'Content for test runner 2';
		$I->havePost( $post );
	}

	public function VerifySeeCacheMissAfterCreatePostTest( AcceptanceTester $I )
	{
		$I->sendGet('graphql', [ 'queryId' => $this->my_unique_id ] );
		$response = $I->grabDataFromResponseByJsonPath('$..data.posts.nodes')[0];
		codecept_debug($response);
		$I->seeHttpHeader('X-Cache', 'MISS');
		$I->assertEquals( 'Test Runner Posts 2 ' . $this->my_unique_id, $response[0]['title'] );
		$I->assertEquals( 'Test Runner Posts ' . $this->my_unique_id, $response[1]['title'] );
	}

	public function CleanUpAtTheEnd( AcceptanceTester $I )
	{
		$I->dontHaveSavedQuery( $this->saved_query_id );
		$I->cleanUp();
	}
}
