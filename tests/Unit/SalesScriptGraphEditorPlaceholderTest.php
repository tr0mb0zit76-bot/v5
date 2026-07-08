<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SalesScriptGraphEditorPlaceholderTest extends TestCase
{
    #[Test]
    public function placeholder_button_inserts_token_at_textarea_cursor(): void
    {
        $component = file_get_contents(__DIR__.'/../../resources/js/Pages/SalesScripts/Editor/Graph.vue');

        $this->assertIsString($component);
        $this->assertStringContainsString('ref="bodyTextareaRef"', $component);
        $this->assertStringContainsString('selectionStart', $component);
        $this->assertStringContainsString('selectionEnd', $component);
        $this->assertStringContainsString('setSelectionRange(cursor, cursor)', $component);
        $this->assertStringNotContainsString('selectedNode.value.body = `${selectedNode.value.body}${spacer}${token}`', $component);
    }
}
