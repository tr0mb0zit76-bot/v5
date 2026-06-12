<?php

namespace App\Support\Pdf;

class PdfSigningCertificateGenerator
{
    /**
     * @return array{certificate_path: string, private_key_path: string}
     */
    public static function generate(string $directory, string $commonName, int $days = 3650, bool $force = false): array
    {
        $certificatePath = $directory.DIRECTORY_SEPARATOR.'cert.pem';
        $privateKeyPath = $directory.DIRECTORY_SEPARATOR.'key.pem';

        if (
            ! $force
            && (is_file($certificatePath) || is_file($privateKeyPath))
        ) {
            throw new \RuntimeException('Сертификат уже существует.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Не удалось создать каталог для сертификата.');
        }

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => __DIR__.DIRECTORY_SEPARATOR.'openssl.cnf',
        ];

        $privateKey = openssl_pkey_new($config);
        if ($privateKey === false) {
            throw new \RuntimeException('Не удалось сгенерировать закрытый ключ.');
        }

        $csr = openssl_csr_new(['commonName' => $commonName], $privateKey, $config);
        if ($csr === false) {
            throw new \RuntimeException('Не удалось создать CSR.');
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, max(30, $days), $config);
        if ($certificate === false) {
            throw new \RuntimeException('Не удалось подписать сертификат.');
        }

        $certificatePem = '';
        $privateKeyPem = '';
        if (
            ! openssl_x509_export($certificate, $certificatePem)
            || ! openssl_pkey_export($privateKey, $privateKeyPem, null, $config)
        ) {
            throw new \RuntimeException('Не удалось экспортировать PEM.');
        }

        if (file_put_contents($certificatePath, $certificatePem) === false) {
            throw new \RuntimeException('Не удалось записать cert.pem.');
        }

        if (file_put_contents($privateKeyPath, $privateKeyPem) === false) {
            throw new \RuntimeException('Не удалось записать key.pem.');
        }

        return [
            'certificate_path' => $certificatePath,
            'private_key_path' => $privateKeyPath,
        ];
    }
}
