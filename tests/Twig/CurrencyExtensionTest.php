<?php

namespace App\Tests\Twig;

use App\Twig\CurrencyExtension;
use PHPUnit\Framework\TestCase;

class CurrencyExtensionTest extends TestCase
{
    public function testFormatsAmountsWithTheCurrencySymbol(): void
    {
        $extension = new CurrencyExtension();

        $this->assertSame('₱1,234.50', $extension->formatMoney('1234.50', 'PHP'));
        $this->assertSame('$1,234.50', $extension->formatMoney('1234.50', 'USD'));
        $this->assertSame('€1,234.50', $extension->formatMoney('1234.50', 'EUR'));
    }

    public function testFallsBackToPesoForMissingOrUnknownCurrency(): void
    {
        $extension = new CurrencyExtension();

        $this->assertSame('₱1,234.50', $extension->formatMoney('1234.50'));
        $this->assertSame('₱1,234.50', $extension->formatMoney('1234.50', ''));
        $this->assertSame('₱1,234.50', $extension->formatMoney('1234.50', 'GBP'));
    }

    public function testFormatsNegativeAndEmptyAmounts(): void
    {
        $extension = new CurrencyExtension();

        $this->assertSame('-₱50.00', $extension->formatMoney(-50, 'PHP'));
        $this->assertSame('₱0.00', $extension->formatMoney(null, 'PHP'));
    }
}
