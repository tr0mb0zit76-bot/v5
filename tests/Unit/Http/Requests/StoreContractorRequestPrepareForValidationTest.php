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
}
