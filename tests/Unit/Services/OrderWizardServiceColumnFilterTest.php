<?php

namespace Tests\Unit\Services;

use App\Services\OrderCompensationService;
use App\Services\OrderNumberGenerator;
use App\Services\OrderStatusService;
use App\Services\OrderWizardService;
use App\Services\OrderWizardStateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class OrderWizardServiceColumnFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['orders']);

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
            $table->json('metadata')->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->schemaDropMany(['orders']);

        parent::tearDown();
    }

    #[Test]
    public function it_filters_order_attributes_to_existing_columns(): void
    {
        $service = new OrderWizardService(
            $this->createMock(OrderNumberGenerator::class),
            $this->createMock(OrderStatusService::class),
            $this->createMock(OrderCompensationService::class),
            $this->createMock(OrderWizardStateService::class),
        );

        $method = new ReflectionMethod(OrderWizardService::class, 'onlyExistingOrderColumns');
        $method->setAccessible(true);

        $input = [
            'order_number' => 'ORD-1',
            'metadata' => ['loading_types' => ['side']],
            'not_a_real_column' => 'drop-me',
        ];

        $out = $method->invoke($service, $input);

        $this->assertSame(['ORD-1'], [$out['order_number']]);
        $this->assertArrayHasKey('metadata', $out);
        $this->assertSame(['loading_types' => ['side']], $out['metadata']);
        $this->assertArrayNotHasKey('not_a_real_column', $out);
    }

    #[Test]
    public function it_reuses_column_listing_on_second_call_for_same_instance(): void
    {
        $service = new OrderWizardService(
            $this->createMock(OrderNumberGenerator::class),
            $this->createMock(OrderStatusService::class),
            $this->createMock(OrderCompensationService::class),
            $this->createMock(OrderWizardStateService::class),
        );

        $method = new ReflectionMethod(OrderWizardService::class, 'onlyExistingOrderColumns');
        $method->setAccessible(true);

        $first = $method->invoke($service, ['order_number' => 'A', 'ghost' => 1]);
        $this->assertArrayNotHasKey('ghost', $first);

        $second = $method->invoke($service, ['order_number' => 'B', 'ghost' => 2]);
        $this->assertSame('B', $second['order_number']);
        $this->assertArrayNotHasKey('ghost', $second);
    }
}
