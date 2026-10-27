<?php

namespace App\Tests\Service;

use App\Service\CurrencySummary;
use PHPUnit\Framework\TestCase;

class CurrencySummaryTest extends TestCase
{
    public function testReturnsTheOnlyCurrencyInUse(): void
    {
        $summary = new CurrencySummary();

        $this->assertSame('PHP', $summary->resolve(['PHP', 'PHP']));
        $this->assertSame('USD', $summary->resolve(['USD']));
        $this->assertSame('EUR', $summary->resolve(['EUR', 'eur']));
    }

    public function testFallsBackToPesoWhenEmptyOrMixed(): void
    {
        $summary = new CurrencySummary();

        $this->assertSame('PHP', $summary->resolve([]));
        $this->assertSame('PHP', $summary->resolve(['PHP', 'USD']));
        $this->assertSame('PHP', $summary->resolve([null, '']));
    }
}
