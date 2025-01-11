<?php

namespace tests\unit;

use Codeception\Test\Unit;
use Esia\Config;
use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\InvalidConfigurationException;
use Esia\Exceptions\OrgOidNotFoundInUrlException;
use Esia\Http\GuzzleHttpClient;
use Esia\OpenId;
use Esia\Signer\Exceptions\SignFailException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;

class OpenIdTest extends Unit
{
    public $config;

    /**
     * @var OpenId
     */
    public $openId;

    /**
     * @throws InvalidConfigurationException
     */
    public function setUp(): void
    {
        $this->config = [
            'clientId' => 'INSP03211',
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
            'privateKeyPath' => codecept_data_dir('server.key'),
            'privateKeyPassword' => 'test',
            'certPath' => codecept_data_dir('server.crt'),
            'tmpPath' => codecept_log_dir(),
        ];

        $config = new Config($this->config);

        $this->openId = new OpenId($config);
    }

    /**
     * @throws SignFailException
     * @throws AbstractEsiaException
     * @throws InvalidConfigurationException
     */
    public function testGetToken(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $oidBase64 = base64_encode('{"urn:esia:sbj_id" : ' . $oid . '}');
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"access_token": "test.' . $oidBase64 . '.test", "refresh_token": "not_important"}'),
        ]);
        $openId = new OpenId($config, $client);

        $token = $openId->getToken('test');

        self::assertNotEmpty($token);
        self::assertSame($oid, $openId->getConfig()->getOid());
    }

    public function testGetTokenRememberRefreshToken(): void
    {
        $config = new Config($this->config);

        $refreshToken = 'remember?';

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"access_token": "test.' . base64_encode('{"urn:esia:sbj_id": 123}') . '.test", "refresh_token": "' . $refreshToken . '"}'),
        ]);
        $openId = new OpenId($config, $client);

        $openId->getToken('test');
        self::assertSame($refreshToken, $openId->getConfig()->getRefreshToken());
    }

    public function testRefreshToken(): void
    {
        $config = new Config($this->config);
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"access_token": "test.' . base64_encode('{"urn:esia:sbj_id": 123}') . '.test", "refresh_token": "first"}'),
        ]);
        $openId = new OpenId($config, $client);

        $openId->refreshToken();

        self::assertSame('first', $openId->getConfig()->getRefreshToken());
    }

    public function testMultipleRefreshToken(): void
    {
        $config = new Config($this->config);
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"access_token": "test.' . base64_encode('{"urn:esia:sbj_id": 123}') . '.test", "refresh_token": "first"}'),
            new Response(200, [], '{"access_token": "test.' . base64_encode('{"urn:esia:sbj_id": 123}') . '.test", "refresh_token": "second"}'),
        ]);
        $openId = new OpenId($config, $client);

        $openId->refreshToken();
        $first = $openId->getConfig()->getRefreshToken();
        $openId->refreshToken();
        $second = $openId->getConfig()->getRefreshToken();

        self::assertSame(['first','second'], [$first, $second]);
    }

    public function testGetTokenWithClientCredentials(): void
    {
        $config = new Config($this->config);
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"access_token": "not_empty"}'),
        ]);
        $openId = new OpenId($config, $client);

        $token = $openId->getTokenWithClientCredentials([
            'org_shortname?org_oid=1002416012',
            'org_ogrn?org_oid=1002416012',
            'org_inn?org_oid=1002416012',
        ]);

        self::assertNotEmpty($token);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetPersonInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"username": "test"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getPersonInfo();
        self::assertNotEmpty($info);
        self::assertSame(['username' => 'test'], $info);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetContactInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getContactInfo();
        self::assertNotEmpty($info);
        self::assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetAddressInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getAddressInfo();
        self::assertNotEmpty($info);
        self::assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetDocInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getDocInfo();
        self::assertNotEmpty($info);
        self::assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    public function testGetRoles(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(
                200,
                [],
                <<<'JSON'
{"stateFacts":["hasSize"],"size":2,"elements":[{"oid":1002416012,"prnOid":1000719157,"fullName":"Индивидуальный предприниматель Илимурзин Владимир Андреевич","shortName":"ИП Илимурзин В. А.","ogrn":"319290100017299","type":"BUSINESS","chief":true,"admin":false,"active":true,"hasRightOfSubstitution":true,"hasApprovalTabAccess":false,"isLiquidated":false},{"oid":1000547703,"prnOid":1000719157,"fullName":"Индивидуальный предприниматель Иванов Иван Иванович","shortName":"ИП Иванов И. И.","ogrn":"312344215554346","type":"BUSINESS","chief":false,"admin":true,"email":"ilimurzin@ya.ru","active":true,"hasRightOfSubstitution":false,"hasApprovalTabAccess":false,"isLiquidated":false}]}
JSON,
            ),
        ]);
        $openId = new OpenId($config, $client);

        $roles = $openId->getRoles();
        self::assertTrue($roles[0]['oid'] === 1002416012);
    }

    public function testGetOrganizations(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"stateFacts":["hasSize"],"size":1,"elements":["https://esia-portal1.test.gosuslugi.ru/rs/orgs/1002416012"]}'),
            new Response(200, [], '{"access_token": "client_credentials_token"}'),
            new Response(200, [], '{"stateFacts":["Identifiable"],"oid":1002416012,"ogrn":"319290100017299","inn":"290136958241","leg":"","isLiquidated":false,"eTag":"656620472844B6421FE4DA6DFAD521755158F9E4"}'),
        ]);
        $openId = new OpenId($config, $client);

        $organizations = $openId->getOrganizations(['org_ogrn', 'org_inn']);

        self::assertTrue($organizations[0]['oid'] === 1002416012);
    }

    public function testGetOrganizationsThrowOrgOidNotFoundInUrl(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"stateFacts":["hasSize"],"size":1,"elements":["https://esia-portal1.test.gosuslugi.ru/rs/orgs/"]}'),
            new Response(200, [], '{"access_token": "client_credentials_token"}'),
            new Response(200, [], '{"stateFacts":["Identifiable"],"oid":1002416012,"ogrn":"319290100017299","inn":"290136958241","leg":"","isLiquidated":false,"eTag":"656620472844B6421FE4DA6DFAD521755158F9E4"}'),
        ]);
        $openId = new OpenId($config, $client);

        self::expectException(OrgOidNotFoundInUrlException::class);

        $openId->getOrganizations(['org_ogrn', 'org_inn']);
    }

    public function testBuildUrl(): void
    {
        $state = '47e1f1e9-8b56-4666-ac02-d1408408e5f2';
        $url = $this->openId->buildUrl($state);
        self::assertStringContainsString($state, $url);
    }

    public function testPassAdditionalParams(): void
    {
        $url = $this->openId->buildUrl(null, [
            'person_filter' => base64_encode('conf_acc'),
        ]);
        self::assertStringContainsString('person_filter=Y29uZl9hY2M%3D', $url);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testBuildLogoutUrl(): void
    {
        $config = $this->openId->getConfig();

        $url = $config->getLogoutUrl() . '?client_id=' . $config->getClientId();
        $logoutUrl = $this->openId->buildLogoutUrl();
        self::assertSame($url, $logoutUrl);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testBuildLogoutUrlWithRedirect(): void
    {
        $config = $this->openId->getConfig();

        $redirectUrl = 'test.example.com';
        $url = $config->getLogoutUrl() . '?client_id=' . $config->getClientId() . '&redirect_url=' . $redirectUrl;
        $logoutUrl = $this->openId->buildLogoutUrl($redirectUrl);
        self::assertSame($url, $logoutUrl);
    }

    /**
     * Client with prepared responses
     *
     * @param array $responses
     * @return ClientInterface
     */
    protected function buildClientWithResponses(array $responses): ClientInterface
    {
        $mock = new MockHandler($responses);

        $handler = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handler]);

        return new GuzzleHttpClient($guzzleClient);
    }
}
