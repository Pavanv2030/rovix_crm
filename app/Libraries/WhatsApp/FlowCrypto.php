<?php

namespace App\Libraries\WhatsApp;

/**
 * WhatsApp Flow endpoint encryption (Meta's Business Encryption spec).
 *
 * Request envelope from Meta: {encrypted_flow_data, encrypted_aes_key, initial_vector}
 * (all base64). The AES key is RSA-OAEP-SHA256 wrapped with our public key;
 * the flow data is AES-128-GCM encrypted with that key + the given IV.
 * Responses must be AES-128-GCM encrypted with the SAME key but a bit-flipped
 * IV, ciphertext+tag concatenated, base64-encoded, returned as raw text
 * (not JSON).
 *
 * PHP's openssl_private_decrypt() only supports SHA-1 OAEP, but Meta requires
 * SHA-256 OAEP+MGF1, and this project has no Composer vendor dir to pull in
 * a library that supports it. The OpenSSL CLI (present on this machine)
 * supports configurable OAEP hashes via `pkeyutl`, so that one operation
 * shells out to it; everything else uses native PHP openssl_* functions.
 */
class FlowCrypto
{
    private static function opensslBinary(): string
    {
        $candidates = [
            'C:\\xampp\\apache\\bin\\openssl.exe',
            'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) return $c;
        }
        return 'openssl'; // fall back to PATH
    }

    /**
     * @return array{0: string, 1: string} [publicKeyPem, privateKeyPem]
     */
    public static function generateKeyPair(): array
    {
        // PHP's openssl_pkey_new() fails on this Windows setup ("No such
        // process" — can't locate openssl.cnf). Generate via the CLI, same
        // binary already used for OAEP decryption, for consistency.
        $tmpDir = WRITEPATH . 'flowcrypto/';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0700, true);

        $privFile = $tmpDir . bin2hex(random_bytes(8)) . '_priv.pem';
        $pubFile  = $tmpDir . bin2hex(random_bytes(8)) . '_pub.pem';

        try {
            $genCmd = escapeshellarg(self::opensslBinary()) . ' genrsa -out ' . escapeshellarg($privFile) . ' 2048';
            exec($genCmd . ' 2>&1', $out1, $code1);
            if ($code1 !== 0 || !is_file($privFile)) {
                throw new \Exception('Key generation failed: ' . implode("\n", $out1));
            }

            $pubCmd = escapeshellarg(self::opensslBinary()) . ' rsa -in ' . escapeshellarg($privFile)
                    . ' -pubout -out ' . escapeshellarg($pubFile);
            exec($pubCmd . ' 2>&1', $out2, $code2);
            if ($code2 !== 0 || !is_file($pubFile)) {
                throw new \Exception('Public key extraction failed: ' . implode("\n", $out2));
            }

            return [file_get_contents($pubFile), file_get_contents($privFile)];
        } finally {
            @unlink($privFile);
            @unlink($pubFile);
        }
    }

    /**
     * RSA-OAEP-SHA256 decrypt via the OpenSSL CLI (see class docblock for why).
     */
    public static function decryptAesKey(string $encryptedAesKeyBinary, string $privateKeyPem): string
    {
        $tmpDir = WRITEPATH . 'flowcrypto/';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0700, true);

        $keyFile = $tmpDir . bin2hex(random_bytes(8)) . '.pem';
        file_put_contents($keyFile, $privateKeyPem);
        chmod($keyFile, 0600);

        try {
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $cmd = escapeshellarg(self::opensslBinary()) . ' pkeyutl -decrypt'
                 . ' -inkey ' . escapeshellarg($keyFile)
                 . ' -pkeyopt rsa_padding_mode:oaep'
                 . ' -pkeyopt rsa_oaep_md:sha256'
                 . ' -pkeyopt rsa_mgf1_md:sha256';

            $process = proc_open($cmd, $descriptors, $pipes);
            if (!is_resource($process)) {
                throw new \Exception('Failed to start openssl process');
            }

            fwrite($pipes[0], $encryptedAesKeyBinary);
            fclose($pipes[0]);

            $aesKey = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0 || $aesKey === false || $aesKey === '') {
                throw new \Exception('AES key decryption failed: ' . trim($stderr));
            }

            return $aesKey;
        } finally {
            @unlink($keyFile);
        }
    }

    /**
     * AES-128-GCM decrypt the flow data payload. Meta appends the 16-byte
     * auth tag to the end of the ciphertext (not sent separately).
     */
    public static function decryptFlowData(string $encryptedFlowDataBinary, string $aesKey, string $ivBinary): array
    {
        $tagLength  = 16;
        $ciphertext = substr($encryptedFlowDataBinary, 0, -$tagLength);
        $tag        = substr($encryptedFlowDataBinary, -$tagLength);

        $plaintext = openssl_decrypt($ciphertext, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $ivBinary, $tag);
        if ($plaintext === false) {
            throw new \Exception('Flow data decryption failed: ' . openssl_error_string());
        }

        return json_decode($plaintext, true) ?? [];
    }

    /**
     * Encrypts the response with the same AES key but a bit-flipped IV, per
     * Meta's spec. Returns base64 of ciphertext+tag concatenated.
     */
    public static function encryptResponse(array $responseData, string $aesKey, string $ivBinary): string
    {
        $flippedIv = ~$ivBinary;

        $ciphertext = openssl_encrypt(
            json_encode($responseData),
            'aes-128-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $flippedIv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new \Exception('Response encryption failed: ' . openssl_error_string());
        }

        return base64_encode($ciphertext . $tag);
    }
}
