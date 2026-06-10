<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesScriptPlaySessionFieldValue extends Model
{
    protected $table = 'sales_script_play_session_field_values';

    protected $fillable = [
        'sales_script_play_session_id',
        'sales_script_capture_field_id',
        'value',
        'captured_at_node_id',
    ];

    /**
     * @return BelongsTo<SalesScriptPlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SalesScriptPlaySession::class, 'sales_script_play_session_id');
    }

    /**
     * @return BelongsTo<SalesScriptCaptureField, $this>
     */
    public function captureField(): BelongsTo
    {
        return $this->belongsTo(SalesScriptCaptureField::class, 'sales_script_capture_field_id');
    }

    /**
     * @return BelongsTo<SalesScriptNode, $this>
     */
    public function capturedAtNode(): BelongsTo
    {
        return $this->belongsTo(SalesScriptNode::class, 'captured_at_node_id');
    }
}
