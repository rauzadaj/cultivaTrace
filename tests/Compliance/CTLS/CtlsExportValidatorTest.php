<?php

namespace App\Tests\Compliance\CTLS;

use App\Compliance\CTLS\Export\CtlsExportReport;
use App\Compliance\CTLS\OfficialTemplate\CtlsTemplateProvider;
use App\Compliance\CTLS\Validation\CtlsExportValidator;
use PHPUnit\Framework\TestCase;

final class CtlsExportValidatorTest extends TestCase
{
    public function testItRejectsMissingMandatoryFields(): void
    {
        $provider = new CtlsTemplateProvider(dirname(__DIR__, 3));
        $validator = new CtlsExportValidator($provider);
        $report = new CtlsExportReport();
        $row = array_fill_keys($provider->getHeaders(), '');
        $row['Reporting Period Year (####)'] = '2026';
        $row['Reporting Period Month (##)'] = '03';

        $validator->validate($row, $report);

        self::assertTrue($report->hasBlockingErrors());
    }
}
