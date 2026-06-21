<?php

namespace Database\Seeders;

use App\Models\ProposalHtmlTemplateVariable;
use App\Support\ProposalHtmlTemplateVariableCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProposalHtmlTemplateVariableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('proposal_html_template_variables')) {
            return;
        }

        $catalog = app(ProposalHtmlTemplateVariableCatalog::class);

        foreach ($catalog->seedRows() as $row) {
            ProposalHtmlTemplateVariable::query()->updateOrCreate(
                ['path' => $row['path']],
                [
                    'label' => $row['label'],
                    'group_name' => $row['group_name'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
