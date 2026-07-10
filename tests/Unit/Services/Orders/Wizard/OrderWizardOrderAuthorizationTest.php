<?php

namespace Tests\Unit\Services\Orders\Wizard;

use App\Models\Order;
use App\Services\Orders\Wizard\OrderWizardOrderAuthorization;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OrderWizardOrderAuthorizationTest extends TestCase
{
    private OrderWizardOrderAuthorization $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $serviceReflection = new ReflectionClass(ContractorPrintFormChangeRequestService::class);
        /** @var ContractorPrintFormChangeRequestService $printFormChangeRequestService */
        $printFormChangeRequestService = $serviceReflection->newInstanceWithoutConstructor();

        $this->authorization = new OrderWizardOrderAuthorization($printFormChangeRequestService);
    }

    #[Test]
    public function can_edit_order_returns_false_when_user_is_null(): void
    {
        $order = $this->createMock(Order::class);

        $this->assertFalse($this->authorization->canEditOrder(Request::create('/'), $order));
    }

    #[Test]
    public function can_promote_basic_terms_returns_false_when_user_is_null(): void
    {
        $this->assertFalse($this->authorization->canPromoteBasicTerms(Request::create('/')));
    }

    #[Test]
    public function can_direct_promote_basic_terms_returns_false_when_user_is_null(): void
    {
        $this->assertFalse($this->authorization->canDirectPromoteBasicTerms(Request::create('/')));
    }
}
