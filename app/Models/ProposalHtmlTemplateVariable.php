<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalHtmlTemplateVariable extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'label',
        'group_name',
        'sort_order',
    ];
}
