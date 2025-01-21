<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignException;

final readonly class OpenSSLSigner implements SignerInterface
{
    public function __construct(
        private string $privateKeyPath,
        #[\SensitiveParameter]
        private ?string $privateKeyPassword = null,
    ) {}

    /**
     * @throws SignException
     */
    public function sign(string $message): string
    {
        $privateKey = openssl_pkey_get_private(
            file_get_contents($this->privateKeyPath),
            $this->privateKeyPassword,
        );

        if ($privateKey === false) {
            throw new SignException('Cannot read the private key: ' . openssl_error_string());
        }

        $signature = null;

        $signResult = openssl_sign(
            $message,
            $signature,
            $privateKey,
            'md_gost12_256',
        );

        if (!$signResult) {
            throw new SignException('Cannot sign the message:' . openssl_error_string());
        }

        return $this->urlSafe(base64_encode($signature));
    }

    private function urlSafe(string $string): string
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }
}
