<?php

namespace App\Libraries\WhatsApp;

class Encryption
{
    private string $key;

    public function __construct()
    {
        $config = config('Rovix');
        $keyHex = $config->encryptionKey;

        if (strlen($keyHex) !== 64) {
            throw new \Exception('Encryption key must be 64 hex characters (32 bytes)');
        }

        $this->key = hex2bin($keyHex);
    }

    public function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new \Exception('Encryption failed');
        }

        return bin2hex($iv) . ':' . bin2hex($ciphertext) . ':' . bin2hex($tag);
    }

    public function decrypt(string $encrypted): string
    {
        $parts = explode(':', $encrypted);

        if (count($parts) === 3) {
            return $this->decryptGcm($parts);
        } elseif (count($parts) === 2) {
            return $this->decryptCbc($parts);
        }

        throw new \Exception('Invalid encrypted format');
    }

    private function decryptGcm(array $parts): string
    {
        [$ivHex, $ciphertextHex, $tagHex] = $parts;

        $plaintext = openssl_decrypt(
            hex2bin($ciphertextHex),
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            hex2bin($ivHex),
            hex2bin($tagHex)
        );

        if ($plaintext === false) {
            throw new \Exception('Decryption failed - data may be corrupted');
        }

        return $plaintext;
    }

    private function decryptCbc(array $parts): string
    {
        [$ivHex, $ciphertextHex] = $parts;

        $plaintext = openssl_decrypt(
            hex2bin($ciphertextHex),
            'aes-256-cbc',
            $this->key,
            OPENSSL_RAW_DATA,
            hex2bin($ivHex)
        );

        if ($plaintext === false) {
            throw new \Exception('Decryption failed');
        }

        return $plaintext;
    }

    public function isLegacyFormat(string $encrypted): bool
    {
        return count(explode(':', $encrypted)) === 2;
    }
}
