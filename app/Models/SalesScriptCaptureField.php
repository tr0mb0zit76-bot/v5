<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesScriptCaptureField extends Model
{
    protected $table = 'sales_script_capture_fields';

    protected $fillable = [
        'code',
        'label',
        'value_type',
        'description',
        'sort_order',
    ];

    /**
     * @return HasMany<SalesScriptPlaySessionFieldValue, $this>
     */
    public function sessionValues(): HasMany
    {
        return $this->hasMany(SalesScriptPlaySessionFieldValue::class, 'sales_script_capture_field_id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
