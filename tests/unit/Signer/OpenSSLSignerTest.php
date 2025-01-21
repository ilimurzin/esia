<?php

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\Exceptions\SignException;
use Esia\Signer\OpenSSLSigner;

class OpenSSLSignerTest extends Unit
{
    public function testSign(): void
    {
        $signer = new OpenSSLSigner(
            codecept_data_dir('cert_otvconba/keys/private.pem'),
            'test',
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

    public function testExceptionThrowsWhenPasswordIsIncorrect(): void
    {
        $signer = new OpenSSLSigner(
            codecept_data_dir('cert_otvconba/keys/private.pem'),
            'wrong',
        );

        $this->expectException(SignException::class);
        $signer->sign('test');
    }

    public function testExceptionThrowsWhenPrivateKeyNotExist(): void
    {
        $signer = new OpenSSLSigner(
            '/tmp/test',
        );

        $this->expectException(SignException::class);
        $signer->sign('test');
    }
}
