<?php
namespace App\Dto;

final readonly class SavingsRow
{
    public function __construct(
        public int $budgetId,
        public string $description,
        public string $currencyType,
        public string $username,
        public float $actualBudget,
        public float $totalSpent,
        public float $remainingSavings,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
