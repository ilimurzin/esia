<?php

namespace tests\unit\Signer;

use Esia\Signer\CliCryptoProSigner;

class CliCryptoProSignerTest extends \Codeception\Test\Unit
{
    public function testSign(): void
    {
        $signer = new CliCryptoProSigner(
            '/opt/cprocsp/bin/amd64/cryptcp',
            '66821344ce484aceb984d887b303544bfdda8ea4'
        );

        // we expect exception here, because we can't install cryptcp to ci 🤷
        $this->expectException(\Esia\Signer\Exceptions\SignFailException::class);
        $signer->sign('test');
    }

    public function testTempDirDoesNotExists(): void
    {
        $this->expectException(\Esia\Signer\Exceptions\NoSuchTmpDirException::class);

        new CliCryptoProSigner(
            '/opt/cprocsp/bin/amd64/cryptcp',
            '66821344ce484aceb984d887b303544bfdda8ea4',
            null,
            '/'
        );
    }

    public function testTempDirIsNotWritable(): void
    {
        $this->expectException(\Esia\Signer\Exceptions\NoSuchTmpDirException::class);

        new CliCryptoProSigner(
            '/opt/cprocsp/bin/amd64/cryptcp',
            '66821344ce484aceb984d887b303544bfdda8ea4',
            null,
            codecept_log_dir('non_writable_directory')
        );
    }
}
