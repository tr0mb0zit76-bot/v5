<?php

namespace Tests\Unit;

use App\Models\SalesScriptTrainerMessage;
use App\Services\SalesScripts\TrainerDialogLoopDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrainerDialogLoopDetectorTest extends TestCase
{
    private TrainerDialogLoopDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new TrainerDialogLoopDetector;
    }

    #[Test]
    public function it_detects_repeated_assistant_replies(): void
    {
        $messages = collect([
            $this->message('user', 'Сколько стоит?'),
            $this->message('assistant', 'Цена зависит от маршрута и сроков.'),
            $this->message('user', 'Ну и?'),
            $this->message('assistant', 'Цена зависит от маршрута и сроков доставки.'),
        ]);

        $result = $this->detector->analyze($messages);

        $this->assertTrue($result['loop_detected']);
        $this->assertContains('assistant_repeated_reply', $result['reasons']);
    }

    private function message(string $role, string $content): SalesScriptTrainerMessage
    {
        $message = new SalesScriptTrainerMessage;
        $message->role = $role;
        $message->content = $content;

        return $message;
    }
}
