<?php

declare(strict_types=1);

namespace App\Service\Pdf;

use Symfony\Component\Filesystem\Filesystem;

final readonly class SimplePdfGenerator
{
    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @param list<string> $lines
     */
    public function writeTextDocument(string $absolutePath, array $lines): void
    {
        $this->filesystem->mkdir(\dirname($absolutePath), 0775);
        $this->filesystem->dumpFile($absolutePath, $this->buildPdf($lines));
    }

    /**
     * @param list<string> $lines
     */
    private function buildPdf(array $lines): string
    {
        $pages = $this->paginateLines($lines);
        $objects = [];
        $pageReferences = [];
        $nextObjectId = 4;

        foreach ($pages as $pageLines) {
            $contentObjectId = $nextObjectId++;
            $pageObjectId = $nextObjectId++;

            $contentStream = $this->buildContentStream($pageLines);
            $objects[$contentObjectId] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($contentStream),
                $contentStream,
            );
            $objects[$pageObjectId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>",
                $contentObjectId,
            );
            $pageReferences[] = sprintf('%d 0 R', $pageObjectId);
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = sprintf(
            "<< /Type /Pages /Kids [%s] /Count %d >>",
            implode(' ', $pageReferences),
            count($pageReferences),
        );
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $objectId => $objectBody) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $objectId, $objectBody);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n", count($objects) + 1);
        $pdf .= "0000000000 65535 f \n";

        for ($objectId = 1; $objectId <= count($objects); ++$objectId) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectId]);
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
            count($objects) + 1,
            $xrefOffset,
        );

        return $pdf;
    }

    /**
     * @param list<string> $lines
     * @return list<list<string>>
     */
    private function paginateLines(array $lines): array
    {
        $wrapped = [];

        foreach ($lines as $line) {
            $segments = preg_split("/\r\n|\r|\n/", wordwrap($line, 95, "\n", true)) ?: [''];
            foreach ($segments as $segment) {
                $wrapped[] = $segment;
            }
        }

        if ($wrapped === []) {
            $wrapped[] = '';
        }

        $pages = array_chunk($wrapped, 52);

        return array_map(
            static fn (array $page): array => array_map(
                static fn (mixed $line): string => (string) $line,
                $page,
            ),
            $pages,
        );
    }

    /**
     * @param list<string> $lines
     */
    private function buildContentStream(array $lines): string
    {
        $escapedLines = array_map(fn (string $line): string => $this->escapePdfText($line), $lines);

        $stream = "BT\n/F1 11 Tf\n14 TL\n40 802 Td\n";
        foreach ($escapedLines as $index => $line) {
            if ($index > 0) {
                $stream .= "T*\n";
            }

            $stream .= sprintf("(%s) Tj\n", $line);
        }
        $stream .= "ET";

        return $stream;
    }

    private function escapePdfText(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;
        }

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $encoded,
        );
    }
}
