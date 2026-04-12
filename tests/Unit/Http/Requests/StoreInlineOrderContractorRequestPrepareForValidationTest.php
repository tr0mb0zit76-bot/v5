<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreInlineOrderContractorRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class StoreInlineOrderContractorRequestPrepareForValidationTest extends TestCase
{
    #[Test]
    public function prepare_for_validation_truncates_long_inline_contractor_fields(): void
    {
        $base = Request::create('/orders/contractors', 'POST', [
            'name' => str_repeat('N', 330),
            'inn' => str_repeat('1', 30),
            'kpp' => str_repeat('2', 30),
            'address' => str_repeat('A', 500),
            'phone' => str_repeat('7', 80),
            'email' => str_repeat('e', 280),
            'contact_person' => str_repeat('P', 280),
        ]);

        $formRequest = StoreInlineOrderContractorRequest::createFrom($base, new StoreInlineOrderContractorRequest);
        $formRequest->setContainer($this->app);
        $formRequest->setRedirector($this->app['redirect']);

        $method = new ReflectionMethod(StoreInlineOrderContractorRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($formRequest);

        $this->assertSame(255, strlen((string) $formRequest->input('name')));
        $this->assertSame(20, strlen((string) $formRequest->input('inn')));
        $this->assertSame(20, strlen((string) $formRequest->input('kpp')));
        $this->assertSame(255, strlen((string) $formRequest->input('address')));
        $this->assertSame(50, strlen((string) $formRequest->input('phone')));
        $this->assertSame(255, strlen((string) $formRequest->input('email')));
        $this->assertSame(255, strlen((string) $formRequest->input('contact_person')));
    }
}
