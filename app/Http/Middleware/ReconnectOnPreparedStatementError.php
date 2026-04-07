<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReconnectOnPreparedStatementError
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (QueryException $e) {
            // Проверяем, является ли ошибка ошибкой 1615 "Prepared statement needs to be re-prepared"
            if ($this->isPreparedStatementError($e)) {
                // Переподключаемся к базе данных
                foreach (array_keys(config('database.connections')) as $connection) {
                    DB::purge($connection);
                    DB::reconnect($connection);
                }

                // Повторяем запрос
                return $next($request);
            }

            throw $e;
        }
    }

    /**
     * Проверяет, является ли исключение ошибкой подготовленного выражения MySQL (1615)
     */
    private function isPreparedStatementError(QueryException $e): bool
    {
        $message = $e->getMessage();
        
        return str_contains($message, '1615') || 
               str_contains($message, 'Prepared statement needs to be re-prepared') ||
               str_contains($message, 'HY000') && str_contains($message, 'prepared statement');
    }
}
