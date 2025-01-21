<?php

namespace Esia;

final readonly class Config
{
    public function __construct(
        public string $clientId,
        public string $clientCertificateHash,
        public string $redirectUrl,
        public string $portalUrl = 'https://esia-portal1.test.gosuslugi.ru/',
        public array $scopes = ['openid'],
        public array $organizationScopes = [],
    ) {}
}
