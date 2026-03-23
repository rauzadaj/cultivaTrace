<?php

namespace App\Compliance\CTLS\Export;

final class CtlsExportReport
{
    /** @var list<array<string, mixed>> */
    private array $mappings = [];

    /** @var list<array<string, mixed>> */
    private array $ignoredFields = [];

    /** @var list<array<string, mixed>> */
    private array $errors = [];

    /** @var list<array<string, mixed>> */
    private array $warnings = [];

    public function __construct(
        private int $sourceLineCount = 0,
        private int $exportedRowCount = 0,
    ) {
    }

    public function setSourceLineCount(int $sourceLineCount): void
    {
        $this->sourceLineCount = $sourceLineCount;
    }

    public function setExportedRowCount(int $exportedRowCount): void
    {
        $this->exportedRowCount = $exportedRowCount;
    }

    public function addMapping(string $sourceField, string $targetField, string $detail): void
    {
        $this->mappings[] = [
            'sourceField' => $sourceField,
            'targetField' => $targetField,
            'detail' => $detail,
        ];
    }

    public function addIgnoredField(string $sourceField, string $reason): void
    {
        $this->ignoredFields[] = [
            'sourceField' => $sourceField,
            'reason' => $reason,
        ];
    }

    public function addError(
        int|string $sourceLine,
        string $sourceColumn,
        string $targetField,
        string $reason,
        string $action,
    ): void {
        $this->errors[] = [
            'sourceLine' => $sourceLine,
            'sourceColumn' => $sourceColumn,
            'targetField' => $targetField,
            'reason' => $reason,
            'action' => $action,
        ];
    }

    public function addWarning(string $field, string $message): void
    {
        $this->warnings[] = [
            'field' => $field,
            'message' => $message,
        ];
    }

    public function hasBlockingErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array{
     *   sourceLineCount: int,
     *   exportedRowCount: int,
     *   mappings: list<array<string, mixed>>,
     *   ignoredFields: list<array<string, mixed>>,
     *   errors: list<array<string, mixed>>,
     *   warnings: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'sourceLineCount' => $this->sourceLineCount,
            'exportedRowCount' => $this->exportedRowCount,
            'mappings' => $this->mappings,
            'ignoredFields' => $this->ignoredFields,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
