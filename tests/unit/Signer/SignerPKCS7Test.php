<?php

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\Exceptions\CannotReadCertificateException;
use Esia\Signer\Exceptions\CannotReadPrivateKeyException;
use Esia\Signer\Exceptions\NoSuchCertificateFileException;
use Esia\Signer\Exceptions\NoSuchKeyFileException;
use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;
use Esia\Signer\SignerPKCS7;

/**
 * Class SignerPKCS7Test
 *
 * @coversDefaultClass \Esia\Signer\SignerPKCS7
 */
class SignerPKCS7Test extends Unit
{
    protected function setUp(): void
    {
        chmod(codecept_data_dir('non_readable_file'), 0044);
    }

    protected function tearDown(): void
    {
        chmod(codecept_data_dir('non_readable_file'), 0644);
    }

    /**
     * @throws SignFailException
     */
    public function testSign(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            codecept_data_dir('server.key'),
            'test',
            codecept_log_dir()
        );

        $signature = $signer->sign('test');

        file_put_contents(codecept_log_dir('content'), 'test');
        $signature = base64_decode(strtr($signature, '-_', '+/'));
        file_put_contents(codecept_log_dir('signature'), $signature);
        $command = sprintf(
            "openssl smime -verify -inform DER -in %s -CAfile %s -content %s",
            codecept_log_dir('signature'),
            codecept_data_dir('server.crt'),
            codecept_log_dir('content')
        );
        $output = null;
        $resultCode = null;
        exec($command, $output, $resultCode);
        self::assertEquals(0, $resultCode, 'OpenSSL verification failure');
    }

    /**
     * @throws SignFailException
     */
    public function testSignCertDoesNotExists(): void
    {
        $signer = new SignerPKCS7(
            '/test',
            codecept_data_dir('server.key'),
            'test',
            codecept_log_dir()
        );

        $this->expectException(NoSuchCertificateFileException::class);
        $signer->sign('test');
    }

    /**
     * @throws SignFailException
     */
    public function testPrivateKeyDoesNotExists(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            '/test',
            'test',
            codecept_log_dir()
        );

        $this->expectException(NoSuchKeyFileException::class);
        $signer->sign('test');
    }

    /**
     * @throws SignFailException
     */
    public function testTmpDirDoesNotExists(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            codecept_data_dir('server.key'),
            'test',
            '/'
        );

        $this->expectException(NoSuchTmpDirException::class);
        $signer->sign('test');
    }

    /**
     * @throws SignFailException
     */
    public function testTmpDirIsNotWritable(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            codecept_data_dir('server.key'),
            'test',
            codecept_log_dir('non_writable_directory')
        );

        $this->expectException(NoSuchTmpDirException::class);
        $signer->sign('test');
    }

    /**
     * @throws SignFailException
     */
    public function testCertificateIsNotReadable(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('non_readable_file'),
            codecept_data_dir('server.key'),
            'test',
            codecept_log_dir()
        );

        $this->expectException(CannotReadCertificateException::class);
        $signer->sign('test');
    }

    /**
     * @throws SignFailException
     */
    public function testPrivateKeyIsNotReadable(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            codecept_data_dir('non_readable_file'),
            'test',
            codecept_log_dir()
        );

        $this->expectException(CannotReadPrivateKeyException::class);
        $signer->sign('test');
    }
}
