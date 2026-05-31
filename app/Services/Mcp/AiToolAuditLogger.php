<?php

namespace App\Services\Mcp;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AiToolAuditLogger
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function log(
        User $user,
        string $tool,
        array $arguments,
        bool $ok,
        ?string $errorMessage = null,
    ): void {
        if (! Schema::hasTable('ai_tool_audit_logs')) {
            return;
        }

        try {
            DB::table('ai_tool_audit_logs')->insert([
                'user_id' => $user->id,
                'tool' => $tool,
                'arguments' => json_encode($this->redact($arguments), JSON_UNESCAPED_UNICODE),
                'ok' => $ok,
                'error_message' => $errorMessage,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // Аудит не должен ломать вызов tool.
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function redact(array $arguments): array
    {
        $redacted = $arguments;

        foreach (['password', 'token', 'api_key', 'secret'] as $key) {
            if (array_key_exists($key, $redacted)) {
                $redacted[$key] = '[redacted]';
            }
        }

        return $redacted;
    }
}
