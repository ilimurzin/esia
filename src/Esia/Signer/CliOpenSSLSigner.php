<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignException;

final readonly class CliOpenSSLSigner implements SignerInterface
{
    public function __construct(
        private string $privateKeyPath,
        #[\SensitiveParameter]
        private ?string $privateKeyPassword = null,
        private ?string $tempDir = null,
    ) {}

    /**
     * @throws SignException
     */
    public function sign(string $message): string
    {
        $messageFilePath = tempnam($this->tempDir ?? sys_get_temp_dir(), 'openssl');
        $signatureFilePath = tempnam($this->tempDir ?? sys_get_temp_dir(), 'openssl');

        file_put_contents($messageFilePath, $message);

        try {
            $signature = $this->signFile($messageFilePath, $signatureFilePath);
        } finally {
            unlink($messageFilePath);
            unlink($signatureFilePath);
        }

        // https://digital.gov.ru/ru/documents/6186/: закодировать полученное значение в base64 url safe
        return $this->urlSafe(base64_encode($signature));
    }

    private function signFile(string $messageFilePath, string $signatureFilePath): string
    {
        $command = "openssl dgst -sign $this->privateKeyPath -out $signatureFilePath";

        if ($this->privateKeyPassword) {
            $command .= " -passin pass:$this->privateKeyPassword";
        }

        $command .= " $messageFilePath";

        $output = null;
        $resultCode = null;
        exec(escapeshellcmd($command) . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0) {
            throw new SignException('Failure signing: ' . implode("\n", $output));
        }

        $signature = file_get_contents($signatureFilePath);

        if (!$signature) {
            throw new SignException("Failure reading $signatureFilePath");
        }

        return $signature;
    }

    private function urlSafe(string $string): string
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }
}
