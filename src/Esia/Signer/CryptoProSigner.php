<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignFailException;

final class CryptoProSigner implements SignerInterface
{
    private $thumbprint;
    private $pin;

    public function __construct(
        string $thumbprint,
        ?string $pin = null,
    ) {
        $this->thumbprint = $thumbprint;
        $this->pin = $pin;
    }

    public function sign(string $message): string
    {
        $store = new \CPStore();
        $store->Open(CURRENT_USER_STORE, 'My', STORE_OPEN_READ_ONLY);

        $certificates = $store->get_Certificates();
        $found = $certificates->Find(CERTIFICATE_FIND_SHA1_HASH, $this->thumbprint, 0);
        if ($found->Count() === 0) {
            throw new SignFailException("Not found certificate with thumbprint $this->thumbprint");
        }
        $certificate = $found->Item(1);
        if ($certificate->HasPrivateKey() === false) {
            throw new SignFailException('Cannot read the private key');
        }

        $signer = new \CPSigner();
        $signer->set_Certificate($certificate);
        if ($this->pin) {
            $signer->set_KeyPin($this->pin);
        }

        $sd = new \CPSignedData();
        $sd->set_ContentEncoding(BASE64_TO_BINARY);
        $sd->set_Content(base64_encode($message));

        return $sd->SignCades($signer, CADES_BES, true, ENCODE_BASE64);
    }
}
