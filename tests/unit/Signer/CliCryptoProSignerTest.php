<?php

namespace tests\unit\Signer;

use Esia\Signer\CliCryptoProSigner;

class CliCryptoProSignerTest extends \Codeception\Test\Unit
{
    protected function setUp(): void
    {
        $output = null;
        $resultCode = null;
        // Suppose cryptcp is in your PATH
        exec('cryptcp -help', $output, $resultCode);

        if ($resultCode !== 0) {
            $this->markTestSkipped('The cryptcp utility is not available');
        }
    }

    public function testSign(): void
    {
        $signer = new CliCryptoProSigner(
            'cryptcp',
            '745187e5c161cd2e3130d886f9df4492fa270685',
            'test',
        );

        $signature = $signer->sign('test');

        file_put_contents(codecept_log_dir('content'), 'test');
        $signature = base64_decode(strtr($signature, '-_', '+/'));
        file_put_contents(codecept_log_dir('signature'), $signature);
        $command = sprintf(
            "openssl smime -verify -inform DER -in %s -CAfile %s -content %s",
            codecept_log_dir('signature'),
            codecept_data_dir('server.crt'),
            codecept_log_dir('content'),
        );
        $output = null;
        $resultCode = null;
        exec($command, $output, $resultCode);
        self::assertEquals(0, $resultCode, 'OpenSSL verification failure');
    }

    public function testTempDirDoesNotExists(): void
    {
        $this->expectException(\Esia\Signer\Exceptions\NoSuchTmpDirException::class);

        new CliCryptoProSigner(
            '/opt/cprocsp/bin/amd64/cryptcp',
            '66821344ce484aceb984d887b303544bfdda8ea4',
            null,
            '/',
        );
    }

    public function testTempDirIsNotWritable(): void
    {
        $this->expectException(\Esia\Signer\Exceptions\NoSuchTmpDirException::class);

        new CliCryptoProSigner(
            '/opt/cprocsp/bin/amd64/cryptcp',
            '66821344ce484aceb984d887b303544bfdda8ea4',
            null,
            codecept_log_dir('non_writable_directory'),
        );
    }
}
