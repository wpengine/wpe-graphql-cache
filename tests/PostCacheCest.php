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
	// The post id created during tests, and needing cleanup
	public $post_id;

	// Use the following data on example post create/update
	public $post_title = "Cache Test Runner";
	public $post_content_0 = "initial content";
	public $post_content_1 = "secondary data";

	/**
	 * While creating a post used for these other tests
	*/
	public function CreatePost( AcceptanceTester $I )
	{
		$post['title'] = $this->post_title;
		$post['content'] = $this->post_content_0;
		$this->post_id = $I->havePost( $post );
	}

	/**
	 * Query the specific post and check the cache hit counter
	 */
	public function VerifySeeCacheHeaderTest( AcceptanceTester $I )
	{
		$I->seePostById( $this->post_id );
		$response = $I->grabDataByPost();
		$I->assertEquals( $this->post_title, $response['title'] );
		$I->assertEquals( "<p>{$this->post_content_0}</p>\n", $response['content'] );
		$I->seeHttpHeader('X-Cache', 'MISS');

		// Query again and see the cached version
		$I->seePostById( $this->post_id );
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
		$I->updatePost( $this->post_id, $params );
	}

	/**
	 * After changing the post content, verify cache miss
	 */
	public function CheckForCacheMissTest( AcceptanceTester $I )
	{
		$I->seePostById( $this->post_id );
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