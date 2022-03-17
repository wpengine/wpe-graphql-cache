<?php
class LoginCest 
{    
    public function _before(AcceptanceTester $I)
    {
    }

    public function frontpageWorks(AcceptanceTester $I)
    {
        $I->amOnPage('/graphql?queryId=category-bees');
        $I->seeResponseContainsJson([
            'data' => [
                'posts' => [
                    'nodes' => [
                        "title" => "hive",
                        'content' => "<p>foo bar. biz bang.</p>\n",
                    ]
                ]
            ]
         ]);
     }
}