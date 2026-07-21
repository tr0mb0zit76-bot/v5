<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Role;
use App\Models\User;
use App\Services\PrintForm\PrintFormDraftPlaceholderConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class PrintFormDraftPlaceholderConverterTest extends TestCase
{
    #[Test]
    public function it_proposes_party_requisite_and_typo_replacements(): void
    {
        Storage::fake('local');

        $own = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО Автоальянс Смоленск',
            'inn' => '6732110940',
            'is_own_company' => true,
            'is_active' => true,
            'signer_name_nominative' => 'Х.Г. Аветисян',
        ]);
        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО ШТЕРН',
            'inn' => '6679070489',
            'is_own_company' => false,
            'is_active' => true,
            'email' => 'zoomlion@shtern66.ru',
        ]);

        $docx = $this->makeTempDocx(
            'Перевозчик: ООО Автоальянс Смоленск ИНН 6732110940 '
            .'Заказчик: ООО ШТЕРН email zoomlion@shtern66.ru '
            .'${kontankt_pogruzka} '
            .'${data_zagruzki}, ${vremya_zagruzki} '
            .'блок2 ${data_zagruzki}, ${vremya_zagruzki}',
        );

        $converter = app(PrintFormDraftPlaceholderConverter::class);
        $result = $converter->analyze($docx, 'stern.docx', 'customer', $customer, $own);

        $this->assertNotEmpty($result['draft_token']);
        $finds = collect($result['proposals'])->pluck('find')->all();
        $this->assertContains('${kontankt_pogruzka}', $finds);
        $this->assertTrue(
            collect($result['proposals'])->contains(
                fn (array $row): bool => str_contains($row['find'], 'ООО Автоальянс Смоленск')
                    || $row['replace'] === '${lp_nazv}',
            ),
        );
        $this->assertTrue(
            collect($result['proposals'])->contains(
                fn (array $row): bool => $row['replace'] === '${cp_nazv}'
                    || str_contains($row['find'], 'ШТЕРН'),
            ),
        );
        $this->assertTrue(
            collect($result['proposals'])->contains(
                fn (array $row): bool => str_starts_with($row['find'], '@@nth:2@@'),
            ),
        );

        $out = $converter->apply($result['draft_token'], $result['proposals']);
        $this->assertFileExists($out);

        $plain = $this->docxPlain($out);
        $this->assertStringContainsString('${kontakt_na_zagruzke}', $plain);
        $this->assertStringContainsString('${data_vygruzki}', $plain);
        $this->assertStringContainsString('${lp_nazv}', $plain);
        $this->assertStringContainsString('${cp_nazv}', $plain);
    }

    #[Test]
    public function settings_user_can_analyze_and_apply_via_http(): void
    {
        Storage::fake('local');

        $user = $this->settingsSystemUser();
        $own = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'ООО ТестСвоя',
            'inn' => '1234567890',
            'is_own_company' => true,
            'is_active' => true,
        ]);
        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО ТестКлиент',
            'inn' => '0987654321',
            'is_own_company' => false,
            'is_active' => true,
        ]);

        $path = $this->makeTempDocx('Стороны: ООО ТестСвоя и ООО ТестКлиент, ИНН 1234567890');
        $upload = new UploadedFile($path, 'draft.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $analyze = $this->actingAs($user)->post(route('settings.templates.draft-converter.analyze'), [
            'source_file' => $upload,
            'party' => 'customer',
            'contractor_id' => $customer->id,
            'own_company_id' => $own->id,
        ]);

        $analyze->assertOk();
        $token = $analyze->json('draft_token');
        $this->assertNotEmpty($token);
        $proposals = $analyze->json('proposals');
        $this->assertIsArray($proposals);
        $this->assertNotEmpty($proposals);

        $apply = $this->actingAs($user)->post(route('settings.templates.draft-converter.apply'), [
            'draft_token' => $token,
            'replacements' => $proposals,
            'download_filename' => 'out.docx',
        ]);

        $apply->assertOk();
        $apply->assertDownload('out.docx');
    }

    private function settingsSystemUser(): User
    {
        $role = Role::query()->create([
            'name' => 'tpl_draft_'.uniqid(),
            'display_name' => 'Templates Draft',
            'permissions' => [],
            'visibility_areas' => ['settings_system'],
            'visibility_scopes' => [],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function makeTempDocx(string $paragraphText): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-docx-');
        if ($path === false) {
            $this->fail('tempnam failed');
        }
        @unlink($path);
        $path .= '.docx';

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body><w:p><w:r><w:t>'.htmlspecialchars($paragraphText, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p></w:body>'
            .'</w:document>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE) === true);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $path;
    }

    private function docxPlain(string $absolutePath): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($absolutePath) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
