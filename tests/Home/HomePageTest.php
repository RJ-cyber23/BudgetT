<?php

namespace App\Tests\Home;

use App\Tests\Support\AuthenticatedWebTestCase;

class HomePageTest extends AuthenticatedWebTestCase
{
    public function testHomePageRendersNavigation(): void
    {
        $crawler = $this->client->request('GET', '/home');

        $this->assertResponseIsSuccessful();

        // Upper navigation bar with the hamburger toggle.
        $this->assertSelectorExists('header.topbar');
        $this->assertSelectorExists('nav.topnav');

        $toggle = $crawler->filter('#navToggle');
        $this->assertCount(1, $toggle);
        $this->assertSame('rightNav', $toggle->attr('aria-controls'));
        $this->assertSame('false', $toggle->attr('aria-expanded'));
        $this->assertSame('button', $toggle->attr('type'));

        // Right navigation bar is present but hidden (inert) until toggled.
        $drawer = $crawler->filter('aside#rightNav');
        $this->assertCount(1, $drawer);
        $this->assertNotNull($drawer->attr('inert'));
        $this->assertSame('Close navigation menu', $crawler->filter('#navClose')->attr('aria-label'));

        // Only the real pages remain in the drawer: Overview and Manage.
        $this->assertCount(2, $drawer->filter('.drawer-section'));
        $this->assertCount(4, $drawer->filter('a[href]'));
        $drawerText = $drawer->text();
        $this->assertStringNotContainsString('Add Budget', $drawerText);
        $this->assertStringNotContainsString('Add Transaction', $drawerText);
        $this->assertStringNotContainsString('Accounts', $drawerText);
        $this->assertStringNotContainsString('Savings goals', $drawerText);
        $this->assertStringNotContainsString('Settings', $drawerText);
        $this->assertSelectorExists('.nav-overlay');

        // Styling and behaviour live in templates/css and templates/js, not inline.
        $this->assertSelectorExists('link[rel="stylesheet"][href*="css/home"]');
        $this->assertSelectorExists('script[src*="js/home"]');
        $this->assertCount(0, $crawler->filter('style'));
        $this->assertCount(0, $crawler->filter('script:not([src])'));
    }

    public function testDashboardShowsLiveBudgetSpendingAndRecentTransactions(): void
    {
        $budget = $this->createBudget('Pamalengke Budget', '200.00');
        $this->createTransaction('Rice', '10.00', $budget);
        $this->createTransaction('Vegetables', '30.00', $budget);
        $this->createTransaction('Uncategorized Spend', '50.00');

        $crawler = $this->client->request('GET', '/home');

        $this->assertResponseIsSuccessful();

        // Three summary cards with real totals: 200 budget, 40 spent, 160 remaining.
        $this->assertCount(3, $crawler->filter('.cards--stats .stat-card'));
        $this->assertStringContainsString('₱200.00', $crawler->filter('.stat-card')->eq(0)->text());
        $this->assertStringContainsString('₱40.00', $crawler->filter('.stat-card')->eq(1)->text());
        $this->assertStringContainsString('₱160.00', $crawler->filter('.stat-card')->eq(2)->text());

        // Budget health card shows the budget and its remaining amount.
        $health = $crawler->filter('.health-item');
        $this->assertCount(1, $health);
        $healthText = $health->text();
        $this->assertStringContainsString('Pamalengke Budget', $healthText);
        $this->assertStringContainsString('₱40.00', $healthText);
        $this->assertStringContainsString('₱160.00', $healthText);

        // Recent transactions are shown as readable rows, including the budget description.
        $this->assertCount(3, $crawler->filter('.recent-item'));
        $this->assertStringContainsString('Uncategorized Spend', $crawler->filter('.recent-item')->first()->text());
        $this->assertStringContainsString('Pamalengke Budget', $crawler->filter('.recent-item')->last()->text());
        $this->assertStringContainsString('₱10.00', $crawler->filter('.recent-item')->last()->text());

        // Uncategorized spending is called out and links back to the transaction page.
        $this->assertSelectorExists('.dashboard-note');
        $this->assertStringContainsString('₱50.00', $crawler->filter('.dashboard-note')->text());
        $this->assertStringContainsString('Review transactions', $crawler->filter('.dashboard-note')->text());

        // Quick actions point at the CRUD pages.
        $this->assertCount(1, $crawler->filter('.hero__actions a[href="/budget"]'));
        $this->assertCount(1, $crawler->filter('.hero__actions a[href="/transaction"]'));
    }

    public function testDashboardUsesTheSharedBudgetCurrency(): void
    {
        $budget = $this->createBudget('Dollar budget', '100.00', 'USD');
        $this->createTransaction('Online purchase', '25.00', $budget);

        $crawler = $this->client->request('GET', '/home');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('$100.00', $crawler->filter('.stat-card')->eq(0)->text());
        $this->assertStringContainsString('$25.00', $crawler->filter('.stat-card')->eq(1)->text());
        $this->assertStringContainsString('$75.00', $crawler->filter('.stat-card')->eq(2)->text());
        $this->assertStringContainsString('$75.00', $crawler->filter('.health-item')->text());
        $this->assertStringContainsString('$25.00', $crawler->filter('.recent-item')->text());
    }

    public function testDashboardShowsEmptyStatesWhenThereIsNoData(): void
    {
        $crawler = $this->client->request('GET', '/home');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.dashboard-grid');
        $this->assertCount(2, $crawler->filter('.dashboard-grid .panel'));
        $this->assertCount(2, $crawler->filter('.empty-state'));
        $this->assertStringContainsString('No budgets yet', $crawler->filter('.empty-state')->eq(0)->text());
        $this->assertStringContainsString('No transactions yet', $crawler->filter('.empty-state')->eq(1)->text());
        $this->assertSelectorNotExists('.dashboard-note');
    }

    public function testDashboardDoesNotListRawUsersTable(): void
    {
        $crawler = $this->client->request('GET', '/home');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('table.data-table');
        $this->assertStringNotContainsString('users', strtolower($crawler->filter('main')->text()));
    }

    public function testDashboardOnlyShowsTheSignedInUsersData(): void
    {
        $otherUser = $this->createUser('other_user');
        $otherBudget = $this->createBudget('Other budget', '999.00', 'PHP', $otherUser);
        $this->createTransaction('Other spend', '99.00', $otherBudget, $otherUser);
        $this->createBudget('My budget', '100.00');

        $crawler = $this->client->request('GET', '/home');

        $this->assertResponseIsSuccessful();
        $mainText = $crawler->filter('main')->text();
        $this->assertStringContainsString('My budget', $mainText);
        $this->assertStringNotContainsString('Other budget', $mainText);
        $this->assertStringNotContainsString('Other spend', $mainText);
        $this->assertStringContainsString('₱100.00', $crawler->filter('.stat-card')->eq(0)->text());
    }
}
