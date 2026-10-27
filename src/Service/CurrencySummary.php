<?php
namespace App\Service;

final class CurrencySummary
{
    /**
     * Returns the single currency used by a set of records, falling back to PHP when empty or mixed.
     *
     * @param iterable<string|null> $currencies
     */
    public function resolve(iterable $currencies): string
    {
        $unique = [];

        foreach ($currencies as $currency) {
            $unique[strtoupper(trim((string) ($currency ?: 'PHP')))] = true;
        }

        return 1 === count($unique) ? (string) array_key_first($unique) : 'PHP';
    }
}
