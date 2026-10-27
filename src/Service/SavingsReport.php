<?php

namespace App\Service;

use App\Dto\SavingsRow;
use App\Entity\BudgetEntity;
use App\Repository\BudgetRepo;
use App\Repository\TransactionRepo;

class SavingsReport
{
    public function __construct(
        private readonly BudgetRepo $budgetRepo,
        private readonly TransactionRepo $transactionRepo,
        private readonly CurrencySummary $currencySummary,
    ) {
    }

    /**
     * Aggregates one account's budgets with their linked transaction spending.
     *
     * @return array{
     *     rows: SavingsRow[],
     *     totalBudget: float,
     *     totalSpent: float,
     *     totalRemaining: float,
     *     uncategorizedSpent: float,
     *     currency: string
     * }
     */
    public function generate(int $userId, string $username): array
    {
        $spentByBudget = [];
        foreach ($this->transactionRepo->getTotalSpentPerBudgetForUser($userId) as $row) {
            $spentByBudget[(int) $row['budgetId']] = (float) $row['totalSpent'];
        }

        $rows = [];
        $totalBudget = 0.0;
        $totalSpent = 0.0;

        foreach ($this->budgetRepo->findAllOrderedByNewestForUser($userId) as $budget) {
            $actualBudget = (float) $budget->getAmount();
            $spent = $spentByBudget[$budget->getBudgetId()] ?? 0.0;

            $rows[] = $this->createRow($budget, $username, $actualBudget, $spent);

            $totalBudget += $actualBudget;
            $totalSpent += $spent;
        }

        return [
            'rows' => $rows,
            'totalBudget' => $totalBudget,
            'totalSpent' => $totalSpent,
            'totalRemaining' => $totalBudget - $totalSpent,
            'uncategorizedSpent' => $this->transactionRepo->getUncategorizedTotalSpentForUser($userId),
            'currency' => $this->currencySummary->resolve(array_map(
                static fn (SavingsRow $row): string => $row->currencyType,
                $rows
            )),
        ];
    }

    private function createRow(
        BudgetEntity $budget,
        string $username,
        float $actualBudget,
        float $spent,
    ): SavingsRow {
        return new SavingsRow(
            budgetId: $budget->getBudgetId(),
            description: $budget->getDescription() ?? 'Untitled budget',
            currencyType: $budget->getCurrencyType() ?? 'PHP',
            username: $username,
            actualBudget: $actualBudget,
            totalSpent: $spent,
            remainingSavings: $actualBudget - $spent,
            createdAt: $budget->getCreatedAt(),
        );
    }
}
