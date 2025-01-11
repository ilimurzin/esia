<?php

namespace tests\unit\Signer;

use Esia\Signer\CliSignerPKCS7;

class CliSignerPKCS7Test extends \Codeception\Test\Unit
{
    public function testSign(): void
    {
        $signer = new CliSignerPKCS7(
            codecept_data_dir('server-gost.crt'),
            codecept_data_dir('server-gost.key'),
            'test',
            codecept_log_dir(),
        );

        $signature = $signer->sign('test');

        file_put_contents(codecept_log_dir('content'), 'test');
        $signature = base64_decode(strtr($signature, '-_', '+/'));
        file_put_contents(codecept_log_dir('signature'), $signature);
        $command = sprintf(
            "openssl smime -verify -inform DER -in %s -CAfile %s -content %s",
            codecept_log_dir('signature'),
            codecept_data_dir('server-gost.crt'),
            codecept_log_dir('content'),
        );
        $output = null;
        $resultCode = null;
        exec($command, $output, $resultCode);
        self::assertEquals(0, $resultCode, 'OpenSSL verification failure');
    }
}
