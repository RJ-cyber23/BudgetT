<?php

namespace App\Tests\Report;

use App\Tests\Support\AuthenticatedWebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class ReportPageTest extends AuthenticatedWebTestCase
{
    public function testReportRendersNavigationAndEmptyState(): void
    {
        $crawler = $this->client->request('GET', '/report');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('header.topbar');
        $this->assertSelectorExists('aside#rightNav');
        $this->assertSelectorExists('.nav-overlay');

        // "Reports" is the menu entry and it is active on this page.
        $this->assertCount(1, $crawler->filter('.topnav a.is-active[href="/report"]'));
        $overviewSection = $crawler->filter('.drawer-section')->reduce(
            static fn ($section) => str_contains($section->text(), 'Overview')
        );
        $this->assertCount(1, $overviewSection);
        $this->assertCount(1, $overviewSection->filter('a.drawer-link.is-active[href="/report"]'));

        // Styling lives in templates/css, not inline.
        $this->assertSelectorExists('link[rel="stylesheet"][href*="css/report"]');
        $this->assertCount(0, $crawler->filter('style'));
        $this->assertCount(0, $crawler->filter('script:not([src])'));

        $this->assertSelectorExists('.empty-state');
        $this->assertStringContainsString('No budgets found', $crawler->filter('.empty-state')->text());
    }

    public function testReportAggregatesActualBudgetTotalSpentAndRemainingSavings(): void
    {
        // 200.00 budget - (10 + 30 + 15) spent = 145.00 remaining.
        $groceries = $this->createBudget('Pamalengke Budget', '200.00');
        $this->createTransaction('Rice', '10.00', $groceries);
        $this->createTransaction('Vegetables', '30.00', $groceries);
        $this->createTransaction('Fish', '15.00', $groceries);

        // 300.00 budget - 120.00 spent = 180.00 remaining.
        $internet = $this->createBudget('Load / Internet', '300.00');
        $this->createTransaction('Fiber plan', '120.00', $internet);

        // A budget without transactions still reports its full amount as savings.
        $this->createBudget('Emergency Fund', '500.00');

        // Uncategorized spending has no budget_id, so it is excluded from per-budget rows.
        $this->createTransaction('Uncategorized Spend', '50.00');

        $crawler = $this->client->request('GET', '/report');

        $this->assertResponseIsSuccessful();

        // Overall totals: ₱1,000.00 budget, ₱175.00 spent, ₱825.00 remaining.
        $this->assertStringContainsString('₱1,000.00', $crawler->filter('.card')->eq(0)->text());
        $this->assertStringContainsString('₱175.00', $crawler->filter('.card')->eq(1)->text());
        $this->assertStringContainsString('₱825.00', $crawler->filter('.card')->eq(2)->text());

        // Per-budget row: actual_budget, total_spent, remaining_savings.
        $groceriesRow = $this->findRow($crawler, 'Pamalengke Budget');
        $groceriesText = $groceriesRow->text();
        $this->assertStringContainsString('₱200.00', $groceriesText);
        $this->assertStringContainsString('₱55.00', $groceriesText);
        $this->assertStringContainsString('₱145.00', $groceriesText);
        $this->assertStringContainsString('juan_dela_cruz', $groceriesText);
        $this->assertStringContainsString(
            'Created '.$groceries->getCreatedAt()->format('M j, Y g:i A'),
            $groceriesText
        );

        // The budget without transactions reports zero spent and full remaining.
        $emptyRowText = $this->findRow($crawler, 'Emergency Fund')->text();
        $this->assertStringContainsString('₱500.00', $emptyRowText);
        $this->assertStringContainsString('₱0.00', $emptyRowText);

        // Uncategorized spending is called out as excluded.
        $this->assertSelectorExists('.report-note');
        $this->assertStringContainsString('₱50.00', $crawler->filter('.report-note')->text());
        $this->assertStringContainsString('excluded', $crawler->filter('.report-note')->text());
    }

    public function testReportUsesBudgetCurrencySymbols(): void
    {
        $budget = $this->createBudget('Dollar budget', '100.00', 'USD');
        $this->createTransaction('Online purchase', '25.00', $budget);

        $crawler = $this->client->request('GET', '/report');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('$100.00', $crawler->filter('.card')->eq(0)->text());
        $this->assertStringContainsString('$25.00', $crawler->filter('.card')->eq(1)->text());
        $this->assertStringContainsString('$75.00', $crawler->filter('.card')->eq(2)->text());

        $rowText = $this->findRow($crawler, 'Dollar budget')->text();
        $this->assertStringContainsString('$100.00', $rowText);
        $this->assertStringContainsString('$25.00', $rowText);
        $this->assertStringContainsString('$75.00', $rowText);
    }

    private function findRow(Crawler $crawler, string $description): Crawler
    {
        $rows = $crawler->filter('.savings-item')->reduce(
            static fn ($item) => str_contains($item->text(), $description)
        );

        $this->assertCount(1, $rows, sprintf('Expected one savings row for "%s".', $description));

        return $rows->first();
    }

    public function testReportOnlyIncludesTheSignedInUsersData(): void
    {
        $otherUser = $this->createUser('other_user');
        $otherBudget = $this->createBudget('Other budget', '500.00', 'PHP', $otherUser);
        $this->createTransaction('Other spend', '50.00', $otherBudget, $otherUser);

        $budget = $this->createBudget('My budget', '200.00');
        $this->createTransaction('My spend', '20.00', $budget);

        $crawler = $this->client->request('GET', '/report');

        $this->assertResponseIsSuccessful();
        $pageText = $crawler->filter('.page')->text();
        $this->assertStringContainsString('My budget', $pageText);
        $this->assertStringNotContainsString('Other budget', $pageText);
        $this->assertStringContainsString('₱200.00', $crawler->filter('.card')->eq(0)->text());
        $this->assertStringContainsString('₱20.00', $crawler->filter('.card')->eq(1)->text());
        $this->assertStringContainsString('₱180.00', $crawler->filter('.card')->eq(2)->text());
    }
}
