<?php

namespace App\Compliance\CTLS\OfficialTemplate;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CtlsTemplateProvider
{
    public const OFFICIAL_SOURCE_URL = 'https://www.canada.ca/content/dam/hc-sc/documents/services/drugs-medication/cannabis/tracking-system/overview/flh-cannabis-tracking-reporting-form-20250318.csv';
    public const RETRIEVED_AT = '2026-03-23';

    /** @var string[]|null */
    private ?array $headers = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        if ($this->headers !== null) {
            return $this->headers;
        }

        $contents = file_get_contents($this->getLocalTemplatePath());
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read CTLS template snapshot at "%s".', $this->getLocalTemplatePath()));
        }

        $headers = str_getcsv(trim($contents), escape: '');
        if ($headers === [] || count($headers) < 10) {
            throw new \RuntimeException('The CTLS template snapshot is empty or malformed.');
        }

        return $this->headers = $headers;
    }

    public function getLocalTemplatePath(): string
    {
        return $this->projectDir . '/docs/compliance/official-template/flh-cannabis-tracking-reporting-form-20250318.csv';
    }

    /**
     * Business statistics are mandatory per Health Canada guidance.
     *
     * @return string[]
     */
    public function getBusinessStatisticsHeaders(): array
    {
        return [
            'Management Employees',
            'Administrative Employees',
            'Sales Employees',
            'Production Employees',
            'Other Employees',
            'Licensed growing area',
            'Licensed processing area',
            'Total building(s) area',
            'Licensed outdoor growing area',
        ];
    }

    /**
     * @return string[]
     */
    public function getRequiredHeaders(): array
    {
        return array_merge([
            'Reporting Period Year (####)',
            'Reporting Period Month (##)',
            'Licence ID',
        ], $this->getBusinessStatisticsHeaders());
    }
}
