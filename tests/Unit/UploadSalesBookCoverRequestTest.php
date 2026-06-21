<?php

namespace Tests\Unit;

use App\Http\Requests\UploadSalesBookCoverRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UploadSalesBookCoverRequestTest extends TestCase
{
    public function test_it_accepts_wide_cover_ratio(): void
    {
        $validator = $this->validatorFor(UploadedFile::fake()->image('cover.jpg', 1600, 200));

        $this->assertFalse($validator->fails());
    }

    public function test_it_rejects_non_wide_cover_ratio(): void
    {
        $validator = $this->validatorFor(UploadedFile::fake()->image('cover.jpg', 1200, 400));

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Обложка должна быть узкой: примерное соотношение сторон от 8:1 до 10:1.',
            $validator->errors()->first('file'),
        );
    }

    private function validatorFor(UploadedFile $file): \Illuminate\Validation\Validator
    {
        $request = UploadSalesBookCoverRequest::create('/', 'POST', [], [], [
            'file' => $file,
        ]);
        $request->setContainer($this->app);

        $validator = Validator::make($request->allFiles(), $request->rules(), $request->messages());

        $validator->after($request->after());

        return $validator;
    }
}
