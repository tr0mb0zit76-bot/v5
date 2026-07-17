<?php

namespace Tests\Unit\Support;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Support\ContractorMailRecipientCatalog;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractorMailRecipientCatalogTest extends TestCase
{
    #[Test]
    public function lists_contacts_and_company_emails_without_duplicates(): void
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasTable('contractor_contacts')) {
            $this->markTestSkipped('Таблицы контрагентов недоступны.');
        }

        $contractor = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Клиент',
            'email' => 'office@client.test',
            'contact_person' => 'Секретарь',
            'contact_person_email' => 'secretary@client.test',
            'is_active' => true,
        ]);

        ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Иван Иванов',
            'email' => 'ivan@client.test',
            'is_primary' => true,
        ]);

        ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Дубль офиса',
            'email' => 'office@client.test',
            'is_primary' => false,
        ]);

        $rows = ContractorMailRecipientCatalog::forContractorId((int) $contractor->id);
        $emails = array_column($rows, 'email');

        $this->assertContains('ivan@client.test', $emails);
        $this->assertContains('secretary@client.test', $emails);
        $this->assertContains('office@client.test', $emails);
        $this->assertCount(3, $emails);
        $this->assertTrue((bool) collect($rows)->firstWhere('email', 'ivan@client.test')['is_primary']);
    }
}
