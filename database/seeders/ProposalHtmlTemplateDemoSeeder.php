<?php

namespace Database\Seeders;

use App\Models\ProposalHtmlTemplate;
use App\Support\ProposalHtmlTemplateParallelImportDemo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProposalHtmlTemplateDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('proposal_html_templates')) {
            return;
        }

        ProposalHtmlTemplate::query()->updateOrCreate(
            ['slug' => ProposalHtmlTemplateParallelImportDemo::SLUG],
            [
                'name' => ProposalHtmlTemplateParallelImportDemo::NAME,
                'is_active' => true,
                'html_body' => ProposalHtmlTemplateParallelImportDemo::htmlBody(),
                'css_inline' => ProposalHtmlTemplateParallelImportDemo::cssInline(),
                'version' => 1,
                'published_at' => now(),
                'owner_user_id' => null,
                'visibility' => 'workspace',
            ],
        );
    }
}
