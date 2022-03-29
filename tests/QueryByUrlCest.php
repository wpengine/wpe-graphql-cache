<?php
/**
 * Compare a graphql GET query string vs saved as a queryId.
 * Verify cache purges each.
 * 
 */
class QueryByUrlCest
{
	// The post id created during tests, and needing cleanup
	public $post_id_1;

	// Use the following data on example post create/update
	public $post_title = "Cache Url Runner";
	public $post_content_0 = "initial url test content";
	public $post_content_1 = "secondary url test content";

	public $post_query_string;

	public function SetupAtTheBeginning( AcceptanceTester $I )
	{
		$I->setUp();
	}

	/**
	 * While creating a post used for these other tests
	*/
	public function CreatePost( AcceptanceTester $I )
	{
		$post['title'] = $this->post_title;
		$post['content'] = $this->post_content_0;
		$this->post_id_1 = $I->havePost( $post );

		// Graphql query string for specific post.
		// We want to make sure this post also purges because it references the same post_id
		$this->post_query_string = sprintf( '{
			post(id: "%s", idType: ID) {
				title
				content
			}
		}', $this->post_id_1 );

	}

	/**
	 * Query the specific post and check the cache hit counter.
	 * Use this separate step function because the 'wpe_auth' cookie was present in $I tester and bypasses cache.
	 */
	public function VerifySeeCacheHeaderTest( AcceptanceTester $I )
	{
		// Make two queries. Verify the second one is cached.
		$I->seePostById( $this->post_id_1 );
		$I->seePostById( $this->post_id_1 );
		$response = $I->grabDataByPost();
		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_0}</p>\n", $response['content'] );
		$I->seeHttpHeader('X-Cache', 'HIT: 1');
	}

	public function VerifySeeCacheHeader2Test( AcceptanceTester $I )
	{
		// Make two queries. Verify the second one is cached.
		$I->sendQuery( $this->post_query_string );
		$I->sendQuery( $this->post_query_string );
		$response = $I->grabDataByPost();
		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_0}</p>\n", $response['content'] );
		$I->seeHttpHeader('X-Cache', 'HIT: 1');
	}

	/**
	 * I want to change the specific post content to initiate a cache purge
	 */
	public function UpdateThePostTest( AcceptanceTester $I )
	{
		$params['content'] = $this->post_content_1;
		$I->updatePost( $this->post_id_1, $params );
	}

	/**
	 * After changing the post content, verify cache miss
	 */
	public function CheckForCacheMissTest( AcceptanceTester $I )
	{
		$I->seePostById( $this->post_id_1 );
		$I->seeHttpHeader('X-Cache', 'MISS');
		$response = $I->grabDataByPost();
		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_1}</p>\n", $response['content'] );
	}

	public function CheckForCacheMiss2Test( AcceptanceTester $I )
	{
		// Query using string should also purge
		$I->sendQuery( $this->post_query_string );
		$I->seeHttpHeader('X-Cache', 'MISS');
		$response = $I->grabDataByPost();
		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_1}</p>\n", $response['content'] );
	}

	public function CleanUpAtTheEnd( AcceptanceTester $I )
	{
		$I->cleanUp();
	}

}
