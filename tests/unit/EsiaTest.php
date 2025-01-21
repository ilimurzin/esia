<?php

namespace tests\unit;

use Codeception\Test\Unit;
use Esia\Config;
use Esia\Esia;
use Esia\Signer\OpenSSLSigner;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class EsiaTest extends Unit
{
    public function testBuildUrl(): void
    {
        $state = '47e1f1e9-8b56-4666-ac02-d1408408e5f2';
        $esia = $this->buildEsia();

        $url = $esia->buildUrl($state, [
            'person_filter' => base64_encode('conf_acc'),
        ]);

        self::assertStringContainsString($state, $url);
        self::assertStringContainsString('person_filter=' . urlencode(base64_encode('conf_acc')), $url);
    }

    private function buildEsia(
        string $clientId = 'TEST111',
        ClientInterface $httpClient = new Client(),
    ): Esia {
        return new Esia(
            new Config(
                clientId: $clientId,
                clientCertificateHash: 'CD6EA35843FDE0212F301509EDD5B51BA7C954782FA4DE0608550A7FB35D80EE',
                redirectUrl: 'http://localhost/response.php',
            ),
            new OpenSSLSigner(
                codecept_data_dir('cert_otvconba/keys/private.pem'),
                'test',
            ),
            $httpClient,
        );
    }

    public function testBuildLogoutUrl(): void
    {
        $clientId = 'TEST111';
        $esia = $this->buildEsia($clientId);
        $redirectUrl = 'http://localhost/response.php?logout';

        $url = $esia->buildLogoutUrl($redirectUrl);

        self::assertStringContainsString("client_id=" . $clientId, $url);
        self::assertStringContainsString("redirect_url=" . urlencode($redirectUrl), $url);
    }

    public function testGetToken(): void
    {
        $refreshToken = '587253d9-21ae-4f32-b20e-d0542c99e09d';
        $tokenType = 'Bearer';
        $expiresIn = 3600;
        $oid = 1000719157;
        $clientId = 'TEST111';
        $esia = $this->buildEsia(
            httpClient: $this->buildMockClient([
                $this->buildTokenResponse(
                    payload: [
                        'urn:esia:sbj_id' => $oid,
                        'client_id' => $clientId,
                    ],
                    refreshToken: $refreshToken,
                    tokenType: $tokenType,
                    expiresIn: $expiresIn,
                ),
            ]),
        );

        $token = $esia->getToken('test');

        self::assertSame($refreshToken, $token->refreshToken);
        self::assertSame($tokenType, $token->tokenType);
        self::assertSame($expiresIn, $token->expiresIn);
        self::assertSame($oid, $token->getOid());
        self::assertSame($clientId, $token->getClientId());
    }

    private function buildMockClient(
        array $queue,
    ): ClientInterface {
        return new Client([
            'handler' => HandlerStack::create(new MockHandler($queue)),
        ]);
    }

    private function buildTokenResponse(
        array $payload,
        string $refreshToken = '587253d9-21ae-4f32-b20e-d0542c99e09d',
        string $tokenType = 'Bearer',
        int $expiresIn = 3600,
    ): callable {
        return function (RequestInterface $request) use ($payload, $refreshToken, $tokenType, $expiresIn) {
            parse_str((string) $request->getBody(), $decodedBody);

            return new Response(
                body: json_encode([
                    'access_token' => 'eyJ2ZX.' . base64_encode(json_encode($payload)) . '.eyJ2ZX',
                    'refresh_token' => $refreshToken,
                    'state' => $decodedBody['state'],
                    'token_type' => $tokenType,
                    'expires_in' => $expiresIn,
                ]),
            );
        };
    }

    public function testRefreshToken(): void
    {
        $firstRefreshToken = '587253d9-21ae-4f32-b20e-d0542c99e09d';
        $secondRefreshToken = '62078fba-1c99-422d-8979-e6d35f83d09d';
        $oid = 1000719157;
        $clientId = 'TEST111';
        $payload = [
            'urn:esia:sbj_id' => $oid,
            'client_id' => $clientId,
        ];
        $esia = $this->buildEsia(
            clientId: $clientId,
            httpClient: $this->buildMockClient([
                $this->buildTokenResponse(
                    $payload,
                    $firstRefreshToken,
                ),
                $this->buildTokenResponse(
                    $payload,
                    $secondRefreshToken,
                ),
            ]),
        );

        $firstToken = $esia->refreshToken('previous-refresh-token');
        $secondToken = $esia->refreshToken($firstToken->refreshToken);

        self::assertSame($firstRefreshToken, $firstToken->refreshToken);
        self::assertSame($secondRefreshToken, $secondToken->refreshToken);
    }
}
