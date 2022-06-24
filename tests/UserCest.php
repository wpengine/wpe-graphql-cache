<?php

class UserCest
{
	public $my_unique_id;

	public $users_query_string;
	public $users_query_id;
	public $users_alias;

	public $posts_query_string;
	public $posts_query_id;
	public $posts_alias;

	public $created_user_1;
	public $created_user_2;

	public function SetupAtTheBeginning( AcceptanceTester $I )
	{
		$I->setUp();

		$this->my_unique_id = uniqid( "testuser_" );

		$this->users_query_string = sprintf( 'query %s {
			users {
				nodes {
					id
					databaseId
					username
				}
			}
		}
		', $this->my_unique_id );
		$this->users_alias = $this->my_unique_id . "_users";
		$this->users_query_id = $I->haveSavedQuery( $this->users_query_string, [ $this->users_alias ] );

		$this->posts_query_string = sprintf( 'query %s {
			posts {
				nodes {
					id
					title
					content
					authorId
				}
			}
		  }
		', $this->my_unique_id );
		$this->posts_alias = $this->my_unique_id . "_posts";
		$this->posts_query_id = $I->haveSavedQuery( $this->posts_query_string, [ $this->posts_alias ] );
	}

	public function SetupUserWithPost( AcceptanceTester $I )
	{
		$this->created_user_1 = $I->haveUser([
			'username' => $this->my_unique_id . '_name_1',
			'firstName' => 'test1',
			'lastName' => 'user1',
		]);

		$post['title'] = 'title ' . $this->my_unique_id;
		$post['content'] = 'content';
		$post['authorId'] = $this->created_user_1;
		$this->user_1_post_1 = $I->havePost( $post );
	}

	public function QueryAndVerifyCacheHitTest( AcceptanceTester $I )
	{
		$I->sendGet('graphql', [ 'queryId' => $this->posts_alias ] );
		$I->seeHttpHeader('X-Cache', 'MISS');

		$I->sendGet('graphql', [ 'queryId' => $this->posts_alias ] );
		$I->seeHttpHeader('X-Cache', 'HIT: 1');

		$I->sendGet('graphql', [ 'queryId' => $this->users_alias ] );
		$I->seeHttpHeader('X-Cache', 'MISS');

		$I->sendGet('graphql', [ 'queryId' => $this->users_alias ] );
		$I->seeHttpHeader('X-Cache', 'HIT: 1');
	}

	public function CreateUserTest( AcceptanceTester $I )
	{
		$this->created_user_2 = $I->haveUser([
			'username' => $this->my_unique_id . '_name_2',
			'firstName' => 'first2',
			'lastName' => 'last2',
		]);
	}

	// Creating the user in previous function, should not invalidate cache.
	public function AfterCreatedUserVerifyUserCacheHitTest( AcceptanceTester $I )
	{
		$I->sendGet('graphql', [ 'queryId' => $this->posts_alias ] );
		$I->seeHttpHeader('X-Cache', 'HIT: 2');

		$I->sendGet('graphql', [ 'queryId' => $this->users_alias ] );
		$I->seeHttpHeader('X-Cache', 'HIT: 2');
	}

    // delete user with no published posts (no purge)
	public function DeleteUserTest( AcceptanceTester $I )
	{
		$I->dontHaveUser( $this->created_user_2 );
		$this->created_user_2 = null;
	}

	// Creating the user in previous function, should not invalidate cache.
	public function AfterDeletedUserVerifyUserCacheHitTest( AcceptanceTester $I )
	{
		$I->sendGet('graphql', [ 'queryId' => $this->posts_alias ] );
		$I->seeHttpHeader('X-Cache', 'HIT: 3');

		$I->sendGet('graphql', [ 'queryId' => $this->users_alias ] );
		$I->seeHttpHeader('X-Cache', 'HIT: 3');
	}

    // delete user with published posts and reassign to user with no posts
	public function DeleteUserWithReassignTest( AcceptanceTester $I )
	{
		$this->created_user_2 = $I->haveUser([
			'username' => $this->my_unique_id . '_name_3',
			'firstName' => 'first3',
			'lastName' => 'last3',
		]);

		$I->dontHaveUser( $this->created_user_1, [ 'reassignId' => $this->created_user_2 ] );
		$this->created_user_1 = null;
	}

	public function AfterDeleteUserVerifyCacheMissTest( AcceptanceTester $I )
	{
		$I->sendGet('graphql', [ 'queryId' => $this->users_alias ] );
		codecept_debug( $I->grabResponse() );
		$I->seeHttpHeader('X-Cache', 'MISS');

		$I->sendGet('graphql', [ 'queryId' => $this->posts_alias ] );
		codecept_debug( $I->grabResponse() );
		$I->seeHttpHeader('X-Cache', 'MISS');
	}

	public function CleanUpAtTheEnd( AcceptanceTester $I )
	{
		$I->dontHaveSavedQuery( $this->users_query_id );
		$I->dontHaveSavedQuery( $this->posts_query_id );
		$I->cleanUp();
	}
}