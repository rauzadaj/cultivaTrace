<?php

namespace App\Domain\Cultivation\Exception;

use DomainException;

final class JournalEntryAppendOnlyViolation extends DomainException
{
    public static function update(): self
    {
        return new self('Journal entries are append-only and cannot be updated once persisted.');
    }

    public static function delete(): self
    {
        return new self('Journal entries are append-only and cannot be deleted once persisted.');
    }
}
