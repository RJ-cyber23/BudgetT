<?php

namespace App\Tests\Budget;

use App\Entity\BudgetEntity;
use App\Tests\Support\AuthenticatedWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class BudgetPageTest extends AuthenticatedWebTestCase
{
    public function testBudgetPageRendersNavigationAndMenuEntry(): void
    {
        $crawler = $this->client->request('GET', '/budget');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('header.topbar');
        $this->assertSelectorExists('aside#rightNav');
        $this->assertSelectorExists('.nav-overlay');

        // The budget page is reachable from the "Manage" drawer category.
        $manageSection = $crawler->filter('.drawer-section')->reduce(
            static fn ($section) => str_contains($section->text(), 'Manage')
        );
        $this->assertCount(1, $manageSection);
        $this->assertCount(1, $manageSection->filter('a.drawer-link.is-active[href="/budget"]'));

        // Create and edit UIs are modals.
        $this->assertSelectorExists('#createBudgetModal.modal form[name="budget"]');
        $this->assertSelectorExists('button[data-bs-target="#createBudgetModal"]');

        // Styling and behaviour live in templates/css and templates/js, not inline.
        $this->assertSelectorExists('link[rel="stylesheet"][href*="css/crud"]');
        $this->assertSelectorExists('script[src*="js/crud"]');
        $this->assertCount(0, $crawler->filter('style'));
        $this->assertCount(0, $crawler->filter('script:not([src])'));
    }

    public function testBudgetPageListsBudgetsWithSwipeActions(): void
    {
        $budget = $this->createBudget('Pamalengke Budget', '200.00');

        $crawler = $this->client->request('GET', '/budget');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('ul.swipe-list');

        $item = $crawler->filter('[data-swipe-item]')->first();
        $this->assertStringContainsString('Pamalengke Budget', $item->text());
        $this->assertStringContainsString('200.00', $item->text());
        $this->assertStringContainsString('₱200.00', $item->text());
        $this->assertStringContainsString('juan_dela_cruz', $item->text());
        $this->assertStringNotContainsString('User #', $item->text());
        $this->assertStringContainsString(
            'Created '.$budget->getCreatedAt()->format('M j, Y g:i A'),
            $item->text()
        );
        $this->assertCount(1, $item->filter('[data-swipe-content]'));
        $this->assertCount(1, $item->filter('[data-swipe-actions]'));

        // Swiping reveals an edit button (modal) and a delete form (CSRF protected).
        $id = $budget->getBudgetId();
        $this->assertCount(1, $item->filter('button[data-bs-target="#editBudgetModal'.$id.'"]'));
        $this->assertCount(1, $item->filter('form[action="/budget/'.$id.'/delete"]'));
        $this->assertCount(1, $item->filter('form[action="/budget/'.$id.'/delete"] input[name="_token"]'));

        // The edit modal holds the existing values.
        $this->assertCount(1, $crawler->filter('#editBudgetModal'.$id.'.modal form[name="budget_edit_'.$id.'"]'));
        $this->assertSame(
            'Pamalengke Budget',
            $crawler->filter('#editBudgetModal'.$id.' input[name="budget_edit_'.$id.'[description]"]')->attr('value')
        );

        // The summary card totals every budget amount.
        $this->assertStringContainsString('₱200.00', $crawler->filter('.card')->eq(1)->text());
    }

    public function testBudgetAmountsUseTheirCurrencySymbols(): void
    {
        $this->createBudget('Peso budget', '200.00', 'PHP');
        $this->createBudget('Dollar budget', '100.00', 'USD');
        $this->createBudget('Euro budget', '50.00', 'EUR');

        $crawler = $this->client->request('GET', '/budget');

        $this->assertResponseIsSuccessful();

        $pageText = $crawler->filter('.page')->text();
        $this->assertStringContainsString('₱200.00', $pageText);
        $this->assertStringContainsString('$100.00', $pageText);
        $this->assertStringContainsString('€50.00', $pageText);

        // Mixed currencies cannot be combined, so the summary falls back to the app default.
        $this->assertStringContainsString('₱350.00', $crawler->filter('.card')->eq(1)->text());
    }

    public function testSummaryCardUsesTheSharedCurrency(): void
    {
        $this->createBudget('Dollar budget', '100.00', 'USD');
        $this->createBudget('Dollar budget 2', '50.00', 'USD');

        $crawler = $this->client->request('GET', '/budget');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('$150.00', $crawler->filter('.card')->eq(1)->text());
        $this->assertStringContainsString('$75.00', $crawler->filter('.card')->eq(2)->text());
    }

    public function testCreateBudgetFromModal(): void
    {
        $crawler = $this->client->request('GET', '/budget');

        $form = $crawler->filter('#createBudgetModal form[name="budget"]')->form([
            'budget[description]' => 'Coffee budget',
            'budget[amount]' => '300.00',
            'budget[currencyType]' => 'PHP',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/budget');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.flash--success');
        $this->assertCount(1, $crawler->filter('[data-swipe-item]'));
        $this->assertStringContainsString('Coffee budget', $crawler->filter('[data-swipe-item]')->text());
        $this->assertStringContainsString('300.00', $crawler->filter('[data-swipe-item]')->text());
    }

    public function testCreateBudgetValidationReopensModal(): void
    {
        $crawler = $this->client->request('GET', '/budget');

        $form = $crawler->filter('#createBudgetModal form[name="budget"]')->form([
            'budget[description]' => '',
            'budget[amount]' => '',
        ]);

        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('body[data-open-modal="createBudgetModal"]');
        $this->assertSelectorExists('.form-field ul');
        $this->assertStringContainsString('Please give this budget a description.', $crawler->filter('.modal-body')->text());
        $this->assertSelectorNotExists('[data-swipe-item]');
    }

    public function testEditBudgetFromModal(): void
    {
        $budget = $this->createBudget('Load budget', '500.00');
        $id = $budget->getBudgetId();

        $crawler = $this->client->request('GET', '/budget');

        $form = $crawler->filter('#editBudgetModal'.$id.' form[name="budget_edit_'.$id.'"]')->form([
            'budget_edit_'.$id.'[description]' => 'Load / Internet budget',
            'budget_edit_'.$id.'[amount]' => '650.00',
            'budget_edit_'.$id.'[currencyType]' => 'PHP',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/budget');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.flash--success');
        $this->assertStringContainsString('Load / Internet budget', $crawler->filter('[data-swipe-item]')->text());
        $this->assertStringContainsString('650.00', $crawler->filter('[data-swipe-item]')->text());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $updated = $entityManager->find(BudgetEntity::class, $id);

        $this->assertSame('Load / Internet budget', $updated?->getDescription());
        $this->assertEqualsWithDelta(650.00, (float) $updated?->getAmount(), 0.001);
    }

    public function testDeleteBudgetWithCsrfToken(): void
    {
        $budget = $this->createBudget('Temporary budget', '50.00');
        $id = $budget->getBudgetId();

        $crawler = $this->client->request('GET', '/budget');
        $form = $crawler->filter('form[action="/budget/'.$id.'/delete"]')->form();

        $this->client->submit($form);

        $this->assertResponseRedirects('/budget');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.flash--success');
        $this->assertSelectorNotExists('[data-swipe-item]');
        $this->assertSelectorExists('.empty-state');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $this->assertNull($entityManager->find(BudgetEntity::class, $id));
    }

    public function testDeleteBudgetRejectsInvalidCsrfToken(): void
    {
        $budget = $this->createBudget('Protected budget', '75.00');

        $this->client->request('POST', '/budget/'.$budget->getBudgetId().'/delete', ['_token' => 'not-a-real-token']);

        $this->assertResponseRedirects('/budget');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.flash--error');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $this->assertNotNull($entityManager->find(BudgetEntity::class, $budget->getBudgetId()));
    }

    public function testBudgetPageOnlyListsTheSignedInUsersBudgets(): void
    {
        $otherUser = $this->createUser('other_user');
        $this->createBudget('Other budget', '900.00', 'PHP', $otherUser);
        $this->createBudget('My budget', '100.00');

        $crawler = $this->client->request('GET', '/budget');

        $this->assertResponseIsSuccessful();
        $pageText = $crawler->filter('.page')->text();
        $this->assertStringContainsString('My budget', $pageText);
        $this->assertStringNotContainsString('Other budget', $pageText);
        $this->assertCount(1, $crawler->filter('[data-swipe-item]'));
    }

    public function testCannotEditAnotherUsersBudget(): void
    {
        $otherUser = $this->createUser('other_user');
        $budget = $this->createBudget('Private budget', '100.00', 'PHP', $otherUser);

        $this->client->request('POST', '/budget/'.$budget->getBudgetId().'/edit', [
            'budget_edit_'.$budget->getBudgetId().'[description]' => 'Changed',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
