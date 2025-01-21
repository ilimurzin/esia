<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignException;

interface SignerInterface
{
    /**
     * @throws SignException
     */
    public function sign(string $message): string;
}
