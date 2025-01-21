<?php

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\CliCryptoProSigner;

class CliCryptoProSignerTest extends Unit
{
    protected function setUp(): void
    {
        $output = null;
        $resultCode = null;
        // Suppose csptest is in your PATH
        exec('csptest -help', $output, $resultCode);

        if ($resultCode !== 0) {
            $this->markTestSkipped('The csptest utility is not available');
        }
    }

    public function testSign(): void
    {
        $signer = new CliCryptoProSigner(
            'HDIMAGE\\\\otvconba',
            '1xqoyc4e',
        );

        $signature = $signer->sign('test');

        self::assertEquals(
            1,
            openssl_verify(
                'test',
                base64_decode(strtr($signature, '-_', '+/')),
                file_get_contents(codecept_data_dir('cert_otvconba/certs/otvconba.cer')),
                'md_gost12_256',
            ),
            'OpenSSL verification failure',
        );
    }
}
