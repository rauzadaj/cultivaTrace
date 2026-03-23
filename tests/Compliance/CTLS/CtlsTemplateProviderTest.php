<?php

namespace App\Tests\Compliance\CTLS;

use App\Compliance\CTLS\OfficialTemplate\CtlsTemplateProvider;
use PHPUnit\Framework\TestCase;

final class CtlsTemplateProviderTest extends TestCase
{
    public function testItLoadsTheOfficialTemplateSnapshot(): void
    {
        $provider = new CtlsTemplateProvider(dirname(__DIR__, 3));
        $headers = $provider->getHeaders();

        self::assertCount(2329, $headers);
        self::assertSame('Reporting Period Year (####)', $headers[0]);
        self::assertSame('Licence ID', $headers[2]);
        self::assertSame('Licensed outdoor growing area', $headers[2328]);
    }
}
