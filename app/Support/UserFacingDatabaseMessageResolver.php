<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Переводит технические ошибки БД в короткие сообщения на русском для UI.
 * Полный текст исключения остаётся в логах Laravel.
 */
final class UserFacingDatabaseMessageResolver
{
    public function resolve(Throwable $throwable): ?string
    {
        if ($throwable instanceof QueryException) {
            return $this->fromQueryException($throwable);
        }

        $previous = $throwable->getPrevious();

        if ($previous instanceof QueryException) {
            return $this->fromQueryException($previous);
        }

        return null;
    }

    private function fromQueryException(QueryException $exception): string
    {
        $message = $exception->getMessage();

        if ($this->isForeignKeyViolation($message)) {
            return 'Нельзя выполнить операцию: с записью связаны другие данные в системе. '
                .'Сначала удалите или отвяжите связанные строки (плечи заказа, документы, платежи и т.п.).';
        }

        if ($this->isDuplicateEntry($message)) {
            return 'Такая запись уже существует. Проверьте уникальные поля (номер, название, ИНН).';
        }

        if ($this->isDataTooLong($message)) {
            return 'Слишком длинное значение в одном из полей. Сократите текст и попробуйте снова.';
        }

        if ($this->isCannotBeNull($message)) {
            return 'Не заполнено обязательное поле. Проверьте форму и попробуйте снова.';
        }

        return 'Не удалось сохранить данные. Попробуйте ещё раз или обратитесь к администратору.';
    }

    private function isForeignKeyViolation(string $message): bool
    {
        return str_contains($message, '1451')
            || str_contains($message, 'Integrity constraint violation')
            || str_contains($message, 'foreign key constraint')
            || str_contains($message, 'FOREIGN KEY');
    }

    private function isDuplicateEntry(string $message): bool
    {
        return str_contains($message, '1062')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'unique constraint');
    }

    private function isDataTooLong(string $message): bool
    {
        return str_contains($message, '1406')
            || str_contains($message, 'Data too long');
    }

    private function isCannotBeNull(string $message): bool
    {
        return str_contains($message, '1048')
            || str_contains($message, 'cannot be null')
            || str_contains($message, 'Column') && str_contains($message, 'cannot be null');
    }
}
