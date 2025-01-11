<?php

namespace tests\unit\Signer;

use Esia\Signer\CryptoProSigner;

class CryptoProSignerTest extends \Codeception\Test\Unit
{
    protected function setUp(): void
    {
        if (!extension_loaded('php_CPCSP')) {
            $this->markTestSkipped('The CryptoPro extension is not available');
        }
    }

    public function testSign(): void
    {
        $signer = new CryptoProSigner(
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
}
