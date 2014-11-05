<?php


class OauthTest extends \Codeception\TestCase\Test
{
   /**
    * @var \UnitTester
    */
    protected $tester;

	/**
	 * @inheritdoc
	 */
	protected function _before()
	{
		$this->tester->initPs();
	}

	/**
	 * @inheritdoc
	 */
	protected function _after()
	{
	}

	/**
	 * Tests the creation of the oauth authorize url.
	 */
	public function testAuthorizeUrl()
    {
		$client = new NostoTaggingOAuth2Client();
		$client->setClientId('test');
		$client->setClientSecret('test');
		$client->setRedirectUrl(urlencode('http://localhost'));
		$client->setScopes(NostoTaggingApiToken::$api_token_names);
		$base_url = $this->tester->getOauthBaseUrl();
		$query_params = 'client_id=test&redirect_uri=http%3A%2F%2Flocalhost&response_type=code&scope=sso products&lang=en';
		$this->assertEquals($base_url.'?'.$query_params, $client->getAuthorizationUrl());
    }

	/**
	 * Tests the authentication of the auth code.
	 */
	public function testAuthenticate()
	{
		$client = new NostoTaggingOAuth2Client();
		$client->setRedirectUrl('http://localhost');
		$token = $client->authenticate('dummy');
		$this->assertFalse($token);
	}

	/**
	 * Tests the creation of an oauth2 token instance.
	 */
	public function testTokenCreation()
	{
		$token = NostoTaggingOAuth2Token::create(array('access_token' => 'dummy'));
		$this->assertInstanceOf('NostoTaggingOAuth2Token', $token);
		$this->assertObjectHasAttribute('access_token', $token);
		$this->assertEquals($token->access_token, 'dummy');
	}
}