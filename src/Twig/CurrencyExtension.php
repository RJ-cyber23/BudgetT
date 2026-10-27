<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CurrencyExtension extends AbstractExtension
{
    private const SYMBOLS = [
        'PHP' => '₱',
        'USD' => '$',
        'EUR' => '€',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'formatMoney']),
        ];
    }

    public function formatMoney(float|int|string|null $amount, ?string $currency = null): string
    {
        $code = strtoupper(trim((string) ($currency ?: 'PHP')));
        $symbol = self::SYMBOLS[$code] ?? '₱';
        $value = (float) str_replace(',', '', (string) ($amount ?? 0));
        $sign = $value < 0 ? '-' : '';

        return $sign.$symbol.number_format(abs($value), 2, '.', ',');
    }
}
