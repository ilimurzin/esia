<?php

namespace Esia;

use Esia\Exceptions\EsiaRequestException;
use Esia\Exceptions\RandomIntGenerationException;
use Esia\Signer\SignerInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class Esia
{
    public function __construct(
        private Config $config,
        private SignerInterface $signer,
        private ClientInterface $client = new Client(),
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function buildUrl(string $state, array $additionalParams = []): string
    {
        $timestamp = $this->getTimestamp();

        $message = $this->config->clientId
            . implode(' ', $this->config->scopes)
            . implode(' ', $this->config->organizationScopes)
            . $timestamp
            . $state
            . $this->config->redirectUrl;
        $clientSecret = $this->signer->sign($message);

        $params = [
            'client_id' => $this->config->clientId,
            'client_certificate_hash' => $this->config->clientCertificateHash,
            'client_secret' => $clientSecret,
            'redirect_uri' => $this->config->redirectUrl,
            'scope' => implode(' ', $this->config->scopes),
            'scope_org' => implode(' ', $this->config->organizationScopes),
            'response_type' => 'code',
            'state' => $state,
            'access_type' => 'offline',
            'timestamp' => $timestamp,
        ];

        if ($additionalParams) {
            $params = array_merge($params, $additionalParams);
        }

        return $this->config->portalUrl . 'aas/oauth2/v2/ac?' . http_build_query($params);
    }

    public function buildLogoutUrl(string $redirectUrl = ''): string
    {
        $params = [
            'client_id' => $this->config->clientId,
        ];

        if ($redirectUrl) {
            $params['redirect_url'] = $redirectUrl;
        }

        return $this->config->portalUrl . 'idp/ext/Logout?' . http_build_query($params);
    }

    public function getToken(string $code): Token
    {
        $timestamp = $this->getTimestamp();
        $state = $this->buildState();

        $message = $this->config->clientId
            . implode(' ', $this->config->scopes)
            . implode(' ', $this->config->organizationScopes)
            . $timestamp
            . $state
            . $this->config->redirectUrl
            . $code;
        $clientSecret = $this->signer->sign($message);

        $body = [
            'client_id' => $this->config->clientId,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_certificate_hash' => $this->config->clientCertificateHash,
            'client_secret' => $clientSecret,
            'state' => $state,
            'redirect_uri' => $this->config->redirectUrl,
            'scope' => implode(' ', $this->config->scopes),
            'scope_org' => implode(' ', $this->config->organizationScopes),
            'timestamp' => $timestamp,
            'token_type' => 'Bearer',
        ];

        $request = new Request(
            'POST',
            $this->config->portalUrl . 'aas/oauth2/v3/te',
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query($body),
        );

        try {
            $response = $this->client->sendRequest($request);
        } catch (\Throwable $t) {
            $this->logger->error('ESIA get token failure', [
                'exception' => $t,
            ]);

            throw new EsiaRequestException(message: 'ESIA request failure', previous: $t);
        }

        $this->logger->debug('ESIA response', [
            'response' => $response,
        ]);

        $body = (string) $response->getBody();
        $decodedBody = json_decode($body);

        if ($decodedBody === null) {
            $this->logger->error('Failure decoding ESIA response', [
                'body' => $body,
            ]);

            throw new EsiaRequestException('Failure decoding ESIA response');
        }

        if (isset($decodedBody->error)) {
            $this->logger->error('ESIA returned error', [
                'error' => $decodedBody->error,
                'errorDescription' => $decodedBody->error_description,
                'body' => $body,
            ]);

            throw new EsiaRequestException("ESIA returned error `$body`");
        }

        $token = Token::fromResponseBody($body);

        if ($token->state !== $state) {
            $this->logger->error('ESIA state not match requested state', [
                'esiaState' => $token->state,
                'requestedState' => $state,
            ]);

            throw new EsiaRequestException('ESIA state not match requested state');
        }

        return $token;
    }

    public function refreshToken(string $refreshToken): Token
    {
        $timestamp = $this->getTimestamp();
        $state = $this->buildState();

        $message = $this->config->clientId
            . implode(' ', $this->config->scopes)
            . implode(' ', $this->config->organizationScopes)
            . $timestamp
            . $state
            . $this->config->redirectUrl;
        $clientSecret = $this->signer->sign($message);

        $body = [
            'client_id' => $this->config->clientId,
            'grant_type' => 'refresh_token',
            'client_certificate_hash' => $this->config->clientCertificateHash,
            'client_secret' => $clientSecret,
            'state' => $state,
            'redirect_uri' => $this->config->redirectUrl,
            'scope' => implode(' ', $this->config->scopes),
            'scope_org' => implode(' ', $this->config->organizationScopes),
            'timestamp' => $timestamp,
            'token_type' => 'Bearer',
            'refresh_token' => $refreshToken,
        ];

        $request = new Request(
            'POST',
            $this->config->portalUrl . 'aas/oauth2/v3/te',
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query($body),
        );

        try {
            $response = $this->client->sendRequest($request);
        } catch (\Throwable $t) {
            $this->logger->error('ESIA refresh token failure', [
                'exception' => $t,
            ]);

            throw new EsiaRequestException(message: 'ESIA request failure', previous: $t);
        }

        $this->logger->debug('ESIA response', [
            'response' => $response,
        ]);

        $body = (string) $response->getBody();
        $decodedBody = json_decode($body);

        if ($decodedBody === null) {
            $this->logger->error('Failure decoding ESIA response', [
                'body' => $body,
            ]);

            throw new EsiaRequestException('Failure decoding ESIA response');
        }

        if (isset($decodedBody->error)) {
            $this->logger->error('ESIA returned error', [
                'error' => $decodedBody->error,
                'errorDescription' => $decodedBody->error_description,
                'body' => $body,
            ]);

            throw new EsiaRequestException("ESIA returned error `$body`");
        }

        $token = Token::fromResponseBody($body);

        if ($token->state !== $state) {
            $this->logger->error('ESIA state not match requested state', [
                'esiaState' => $token->state,
                'requestedState' => $state,
            ]);

            throw new EsiaRequestException('ESIA state not match requested state');
        }

        return $token;
    }

    private function getTimestamp(): string
    {
        return date('Y.m.d H:i:s O');
    }

    private function buildState(): string
    {
        try {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
            );
        } catch (Exception $e) {
            throw new RandomIntGenerationException('Appropriate source of randomness cannot be found', 0, $e);
        }
    }
}
