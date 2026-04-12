<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreContractorRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class StoreContractorRequestPrepareForValidationTest extends TestCase
{
    #[Test]
    public function prepare_for_validation_casts_numeric_inn_to_string(): void
    {
        $base = Request::create('/contractors', 'POST', [
            'inn' => 7_707_083_893,
        ]);

        $formRequest = StoreContractorRequest::createFrom($base, new StoreContractorRequest);
        $formRequest->setContainer($this->app);
        $formRequest->setRedirector($this->app['redirect']);

        $method = new ReflectionMethod(StoreContractorRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($formRequest);

        $this->assertSame('7707083893', $formRequest->input('inn'));
    }

    #[Test]
    public function prepare_for_validation_truncates_dadata_like_long_strings_to_column_limits(): void
    {
        $base = Request::create('/contractors', 'POST', [
            'name' => str_repeat('N', 320),
            'full_name' => str_repeat('F', 320),
            'legal_address' => str_repeat('A', 400),
            'actual_address' => str_repeat('B', 410),
            'postal_address' => str_repeat('C', 420),
        ]);

        $formRequest = StoreContractorRequest::createFrom($base, new StoreContractorRequest);
        $formRequest->setContainer($this->app);
        $formRequest->setRedirector($this->app['redirect']);

        $method = new ReflectionMethod(StoreContractorRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($formRequest);

        $this->assertSame(255, strlen((string) $formRequest->input('name')));
        $this->assertSame(255, strlen((string) $formRequest->input('full_name')));
        $this->assertSame(255, strlen((string) $formRequest->input('legal_address')));
        $this->assertSame(255, strlen((string) $formRequest->input('actual_address')));
        $this->assertSame(255, strlen((string) $formRequest->input('postal_address')));
    }
}
