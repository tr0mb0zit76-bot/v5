<?php

namespace Database\Seeders;

use App\Models\ProposalHtmlTemplate;
use App\Support\ProposalHtmlTemplateColdEmailLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProposalHtmlTemplateDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('proposal_html_templates')) {
            return;
        }

        foreach (ProposalHtmlTemplateColdEmailLibrary::templates() as $template) {
            ProposalHtmlTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                [
                    'name' => $template['name'],
                    'is_active' => true,
                    'html_body' => $template['html_body'],
                    'css_inline' => $template['css_inline'],
                    'version' => 2,
                    'published_at' => now(),
                    'owner_user_id' => null,
                    'visibility' => 'workspace',
                ],
            );
        }
    }
}
