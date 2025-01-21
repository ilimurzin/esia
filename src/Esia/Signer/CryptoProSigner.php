<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignException;

final readonly class CryptoProSigner implements SignerInterface
{
    public function __construct(
        private string $thumbprint,
        #[\SensitiveParameter]
        private ?string $pin = null,
    ) {}

    public function sign(string $message): string
    {
        $store = new \CPStore();
        $store->Open(CURRENT_USER_STORE, 'My', STORE_OPEN_READ_ONLY);

        $certificates = $store->get_Certificates();
        $found = $certificates->Find(CERTIFICATE_FIND_SHA1_HASH, $this->thumbprint, 0);
        if ($found->Count() === 0) {
            throw new SignException("Not found certificate with thumbprint $this->thumbprint");
        }
        $certificate = $found->Item(1);
        if ($certificate->HasPrivateKey() === false) {
            throw new SignException('Cannot read the private key');
        }

        if ($this->pin) {
            $certificate->PrivateKey()->set_KeyPin($this->pin);
        }

        $hashedData = new \CPHashedData();
        $hashedData->set_Algorithm(\CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_256);
        $hashedData->set_DataEncoding(\BASE64_TO_BINARY);
        $hashedData->Hash(base64_encode($message));

        $rawSignature = new \CPRawSignature();

        // https://docs.cryptopro.ru/cades/reference/cadescom/cadescom_interface/irawsignaturesignhash
        $signature = $rawSignature->SignHash($hashedData, $certificate);

        // https://digital.gov.ru/ru/documents/6186/: развернуть зеркально, побайтово, полученную подпись
        $signature = strrev(hex2bin($signature));

        // https://digital.gov.ru/ru/documents/6186/: закодировать полученное значение в base64 url safe
        return $this->urlSafe(base64_encode($signature));
    }

    private function urlSafe(string $string): string
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }
}
