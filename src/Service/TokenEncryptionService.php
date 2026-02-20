<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TokenEncryptionService
{
    private const string CIPHER = 'aes-256-gcm';

    public function __construct(
        #[Autowire('%env(BOT_TOKEN_ENCRYPTION_KEY)%')]
        private readonly string $key
    ) {}

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            hex2bin($this->key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encrypted): string
    {
        $data = base64_decode($encrypted);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, 16);
        $ciphertext = substr($data, $ivLength + 16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            hex2bin($this->key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }
}
