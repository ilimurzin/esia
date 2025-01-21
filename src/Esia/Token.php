<?php

declare(strict_types=1);

namespace Esia;

final readonly class Token
{
    private function __construct(
        public string $accessToken,
        public int $expiresIn,
        public string $state,
        public string $tokenType,
        public string $refreshToken,
    ) {}

    public static function fromResponseBody(string $response): self
    {
        $response = json_decode($response);

        if (isset($response->error)) {
            throw new \InvalidArgumentException('You passed response with error ' . $response->error);
        }

        return new self(
            $response->access_token,
            $response->expires_in,
            $response->state,
            $response->token_type,
            $response->refresh_token,
        );
    }

    public function getPayload(): string
    {
        $chunks = explode('.', $this->accessToken);

        return base64_decode(strtr($chunks[1], '-_', '+/'));
    }

    public function getOid(): int
    {
        $payload = json_decode($this->getPayload());

        return $payload->{'urn:esia:sbj_id'};
    }

    public function getClientId(): string
    {
        $payload = json_decode($this->getPayload());

        return $payload->{'client_id'};
    }
}
