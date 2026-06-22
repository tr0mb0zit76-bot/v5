<?php

namespace Tests\Unit;

use App\Http\Requests\StoreOrderRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreOrderActualDateValidationTest extends TestCase
{
    public function test_future_loading_actual_is_rejected(): void
    {
        $rules = (new StoreOrderRequest)->rules();
        $future = now()->addDay()->toDateString();

        $validator = Validator::make([
            'performers' => [
                ['loading_actual' => $future],
            ],
        ], [
            'performers' => $rules['performers'],
            'performers.*.loading_actual' => $rules['performers.*.loading_actual'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('performers.0.loading_actual', $validator->errors()->toArray());
    }

    public function test_today_loading_actual_is_accepted(): void
    {
        $rules = (new StoreOrderRequest)->rules();
        $today = now()->toDateString();

        $validator = Validator::make([
            'performers' => [
                ['loading_actual' => $today],
            ],
        ], [
            'performers' => $rules['performers'],
            'performers.*.loading_actual' => $rules['performers.*.loading_actual'],
        ]);

        $this->assertFalse($validator->fails());
    }
}
