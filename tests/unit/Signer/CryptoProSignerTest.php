<?php

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\CryptoProSigner;

class CryptoProSignerTest extends Unit
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
            '2d6e18176b1512b2bc782f884ecf65fb3d5cba8b',
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
