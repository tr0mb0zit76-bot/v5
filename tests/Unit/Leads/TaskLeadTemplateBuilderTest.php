<?php

namespace Tests\Unit\Leads;

use App\Models\Task;
use App\Services\Leads\TaskLeadTemplateBuilder;
use Tests\TestCase;

class TaskLeadTemplateBuilderTest extends TestCase
{
    public function test_build_maps_task_fields_to_lead_template(): void
    {
        $task = new Task([
            'title' => 'Перезвонить по заявке',
            'description' => 'Клиент ждёт КП',
            'contractor_id' => 7,
            'responsible_id' => 3,
        ]);
        $task->id = 42;

        $template = (new TaskLeadTemplateBuilder)->build($task);

        $this->assertSame('new', $template['status']);
        $this->assertSame('', $template['source']);
        $this->assertSame(7, $template['counterparty_id']);
        $this->assertSame(3, $template['responsible_id']);
        $this->assertSame('Перезвонить по заявке', $template['title']);
        $this->assertSame('Клиент ждёт КП', $template['description']);
        $this->assertSame(42, $template['link_task_id']);
    }
}
