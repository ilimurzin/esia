<?php

namespace tests\unit;

use Esia\Config;
use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\InvalidConfigurationException;
use Esia\OpenId;
use Esia\Signer\CliSignerPKCS7;
use GuzzleHttp\Psr7\Response;

class OpenIdCliOpensslTest extends OpenIdTest
{
    /**
     * @throws InvalidConfigurationException
     */
    public function setUp(): void
    {
        $this->config = [
            'clientId' => 'INSP03211',
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
            'privateKeyPath' => codecept_data_dir('server-gost.key'),
            'privateKeyPassword' => 'test',
            'certPath' => codecept_data_dir('server-gost.crt'),
            'tmpPath' => codecept_log_dir(),
        ];

        $config = new Config($this->config);

        $this->openId = new OpenId($config);
        $this->openId->setSigner(new CliSignerPKCS7(
            $this->config['certPath'],
            $this->config['privateKeyPath'],
            $this->config['privateKeyPassword'],
            $this->config['tmpPath']
        ));
    }

    /**
     * @throws AbstractEsiaException
     * @throws InvalidConfigurationException
     */
    public function testGetToken(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $oidBase64 = base64_encode('{"urn:esia:sbj_id" : ' . $oid . '}');
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"access_token": "test.' . $oidBase64 . '.test", "refresh_token":"not_important"}'),
        ]);
        $openId = new OpenId($config, $client);
        $openId->setSigner(new CliSignerPKCS7(
            $this->config['certPath'],
            $this->config['privateKeyPath'],
            $this->config['privateKeyPassword'],
            $this->config['tmpPath']
        ));

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
        $openId->setSigner(new CliSignerPKCS7(
            $this->config['certPath'],
            $this->config['privateKeyPath'],
            $this->config['privateKeyPassword'],
            $this->config['tmpPath']
        ));

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
        $openId->setSigner(new CliSignerPKCS7(
            $this->config['certPath'],
            $this->config['privateKeyPath'],
            $this->config['privateKeyPassword'],
            $this->config['tmpPath']
        ));

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
        $openId->setSigner(new CliSignerPKCS7(
            $this->config['certPath'],
            $this->config['privateKeyPath'],
            $this->config['privateKeyPassword'],
            $this->config['tmpPath']
        ));

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
        $openId->setSigner(new CliSignerPKCS7(
            $this->config['certPath'],
            $this->config['privateKeyPath'],
            $this->config['privateKeyPassword'],
            $this->config['tmpPath']
        ));

        $token = $openId->getTokenWithClientCredentials([
            'org_shortname?org_oid=1002416012',
            'org_ogrn?org_oid=1002416012',
            'org_inn?org_oid=1002416012',
        ]);

        self::assertNotEmpty($token);
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
        $openId->setSigner(new CliSignerPKCS7(
            $this->config['certPath'],
            $this->config['privateKeyPath'],
            $this->config['privateKeyPassword'],
            $this->config['tmpPath']
        ));

        $organizations = $openId->getOrganizations(['org_ogrn', 'org_inn']);

        self::assertTrue($organizations[0]['oid'] === 1002416012);
    }
}
