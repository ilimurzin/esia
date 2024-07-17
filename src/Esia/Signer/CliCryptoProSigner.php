<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;

final class CliCryptoProSigner implements SignerInterface
{
    private $toolPath;
    private $thumbprint;
    private $pin;
    private $tempDir;
    private $proxy;
    private $timeout;

    public function __construct(
        string $toolPath,
        string $thumbprint,
        ?string $pin = null,
        ?string $tempDir = null,
        ?string $proxy = null,
        ?int $timeout = null
    ) {
        $this->toolPath = $toolPath;
        $this->thumbprint = $thumbprint;
        $this->pin = $pin;
        $this->tempDir = $tempDir ?? sys_get_temp_dir();

        if (!file_exists($this->tempDir)) {
            throw new NoSuchTmpDirException('Temporary folder is not found');
        }
        if (!is_writable($this->tempDir)) {
            throw new NoSuchTmpDirException('Temporary folder is not writable');
        }

        $this->proxy = $proxy;
        $this->timeout = $timeout;
    }

    public function sign(string $message): string
    {
        $tempPath = tempnam($this->tempDir, 'cryptcp');
        file_put_contents($tempPath, $message);

        try {
            return $this->signFile($tempPath);
        } catch (SignFailException $e) {
            unlink($tempPath);

            throw $e;
        }
    }

    private function signFile(string $tempPath): string
    {
        $command = "$this->toolPath -signf -dir $this->tempDir -cert -thumbprint $this->thumbprint";
        if ($this->pin) {
            $command .= " -pin $this->pin";
        }
        $command .= " $tempPath";

        if ($this->timeout) {
            $command = "timeout $this->timeout " . $command;
        }

        if ($this->proxy) {
            $command = "export http_proxy=$this->proxy && " . $command;
        }

        $output = null;
        $resultCode = null;
        exec($command, $output, $resultCode);

        if ($resultCode === 124) {
            throw new SignFailException('Signing timeout');
        }

        if ($resultCode !== 0) {
            throw new SignFailException('Failure signing: ' . implode("\n", $output));
        }

        $signatureFilePath = $tempPath . '.sgn';
        $signature = file_get_contents($signatureFilePath);
        unlink($signatureFilePath);

        if (!$signature) {
            throw new SignFailException("Failure reading $signatureFilePath");
        }

        return $signature;
    }
}
