<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Jobs\EnsureOneCOrderCustomerJob;
use App\Models\Contractor;
use App\Models\Order;
use App\Services\OneC\OneCCounterpartyEnsureService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OneCCounterpartyEnsureServiceTest extends TestCase
{
    public function test_ensure_for_contractor_returns_ref_when_enabled_fake(): void
    {
        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);

        $contractor = new Contractor([
            'name' => 'ООО Тест',
            'inn' => '2312178145',
            'kpp' => '231201001',
        ]);
        $contractor->id = 1;

        $result = app(OneCCounterpartyEnsureService::class)->ensureForContractor($contractor);

        $this->assertNotNull($result);
        $this->assertNotSame('', $result['ref']);
    }

    public function test_ensure_skips_when_disabled(): void
    {
        config(['one_c.enabled' => false]);

        $contractor = new Contractor([
            'name' => 'ООО Тест',
            'inn' => '2312178145',
            'kpp' => '231201001',
        ]);

        $this->assertNull(app(OneCCounterpartyEnsureService::class)->ensureForContractor($contractor));
    }

    public function test_job_is_dispatchable(): void
    {
        Queue::fake();

        EnsureOneCOrderCustomerJob::dispatch(138);

        Queue::assertPushed(EnsureOneCOrderCustomerJob::class, function (EnsureOneCOrderCustomerJob $job): bool {
            return $job->orderId === 138;
        });
    }

    public function test_ensure_order_customer_uses_client(): void
    {
        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'fake',
        ]);

        $client = Contractor::query()->create([
            'name' => 'ООО Ensure Client',
            'inn' => '7707083893',
            'kpp' => '770701001',
        ]);

        $order = Order::query()->create([
            'order_number' => 'АС-ENSURE-1',
            'customer_id' => $client->id,
        ]);

        $result = app(OneCCounterpartyEnsureService::class)->ensureOrderCustomer($order);

        $this->assertNotNull($result);
        $this->assertNotSame('', $result['ref']);
    }
}
