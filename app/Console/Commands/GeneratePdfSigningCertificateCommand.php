<?php

namespace App\Console\Commands;

use App\Support\Pdf\PdfSigningCertificateGenerator;
use Illuminate\Console\Command;

class GeneratePdfSigningCertificateCommand extends Command
{
    protected $signature = 'pdf-signing:generate-certificate
                            {--force : Перезаписать существующие cert.pem и key.pem}
                            {--days=3650 : Срок действия сертификата в днях}
                            {--cn= : Common Name (по умолчанию APP_NAME)}';

    protected $description = 'Сгенерировать self-signed сертификат для удостоверяющей подписи PDF (DocMDP)';

    public function handle(): int
    {
        if (! extension_loaded('openssl')) {
            $this->error('Расширение PHP openssl не установлено.');

            return self::FAILURE;
        }

        $directory = storage_path('app/pdf-signing');
        $commonName = trim((string) ($this->option('cn') ?: config('app.name', 'CRM PDF Signing')));
        $days = max(30, (int) $this->option('days'));

        try {
            $paths = PdfSigningCertificateGenerator::generate(
                $directory,
                $commonName,
                $days,
                (bool) $this->option('force'),
            );
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Self-signed сертификат создан:');
        $this->line('  '.$paths['certificate_path']);
        $this->line('  '.$paths['private_key_path']);
        $this->newLine();
        $this->comment('В .env включите: PDF_CERTIFY_ENABLED=true');
        $this->comment('После согласования печатной формы PDF будет удостоверен (DocMDP '.config('pdf_signing.docmdp', 2).').');

        return self::SUCCESS;
    }
}
