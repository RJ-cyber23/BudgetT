<?php

namespace App\Tests\Transaction;

use App\Entity\TransactionEntity;
use App\Tests\Support\AuthenticatedWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class TransactionPageTest extends AuthenticatedWebTestCase
{
    public function testTransactionPageRendersNavigationAndMenuEntry(): void
    {
        $crawler = $this->client->request('GET', '/transaction');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('header.topbar');
        $this->assertSelectorExists('aside#rightNav');
        $this->assertSelectorExists('.nav-overlay');

        // The transaction page is reachable from the "Manage" drawer category.
        $manageSection = $crawler->filter('.drawer-section')->reduce(
            static fn ($section) => str_contains($section->text(), 'Manage')
        );
        $this->assertCount(1, $manageSection);
        $this->assertCount(1, $manageSection->filter('a.drawer-link.is-active[href="/transaction"]'));

        // Create and edit UIs are modals.
        $this->assertSelectorExists('#createTransactionModal.modal form[name="transaction"]');
        $this->assertSelectorExists('button[data-bs-target="#createTransactionModal"]');

        // Styling and behaviour live in templates/css and templates/js, not inline.
        $this->assertSelectorExists('link[rel="stylesheet"][href*="css/crud"]');
        $this->assertSelectorExists('script[src*="js/crud"]');
        $this->assertCount(0, $crawler->filter('style'));
        $this->assertCount(0, $crawler->filter('script:not([src])'));
    }

    public function testTransactionPageListsTransactionsWithSwipeActions(): void
    {
        $budget = $this->createBudget('Pamalengke Budget');
        $transaction = $this->createTransaction('Rice', '10.00', $budget);
        $this->createTransaction('Uncategorized Spend', '50.00');

        $crawler = $this->client->request('GET', '/transaction');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('ul.swipe-list');
        $this->assertCount(2, $crawler->filter('[data-swipe-item]'));

        $item = $crawler->filter('[data-swipe-item]')->last();
        $this->assertStringContainsString('Rice', $item->text());
        $this->assertStringContainsString('10.00', $item->text());
        $this->assertStringContainsString('Pamalengke Budget', $item->text());
        $this->assertCount(1, $item->filter('[data-swipe-content]'));
        $this->assertCount(1, $item->filter('[data-swipe-actions]'));

        // A transaction without a budget is labelled as uncategorized.
        $this->assertStringContainsString('Uncategorized', $crawler->filter('[data-swipe-item]')->first()->text());

        // Swiping reveals an edit button (modal) and a delete form (CSRF protected).
        $id = $transaction->getTransactionId();
        $this->assertCount(1, $item->filter('button[data-bs-target="#editTransactionModal'.$id.'"]'));
        $this->assertCount(1, $item->filter('form[action="/transaction/'.$id.'/delete"]'));
        $this->assertCount(1, $item->filter('form[action="/transaction/'.$id.'/delete"] input[name="_token"]'));

        // The edit modal holds the existing values, including the selected budget.
        $this->assertCount(1, $crawler->filter('#editTransactionModal'.$id.'.modal form[name="transaction_edit_'.$id.'"]'));
        $this->assertSame(
            'Rice',
            $crawler->filter('#editTransactionModal'.$id.' input[name="transaction_edit_'.$id.'[description]"]')->attr('value')
        );
        $this->assertSame(
            (string) $budget->getBudgetId(),
            $crawler->filter('#editTransactionModal'.$id.' select[name="transaction_edit_'.$id.'[budget]"] option[selected]')->attr('value')
        );

        // The create modal lists budgets by description, not by numeric ID.
        $budgetOption = $crawler->filter('#createTransactionModal select[name="transaction[budget]"] option[value="'.$budget->getBudgetId().'"]');
        $this->assertCount(1, $budgetOption);
        $this->assertSame('Pamalengke Budget', trim($budgetOption->text()));

        // The summary card totals every transaction amount.
        $this->assertStringContainsString('₱60.00', $crawler->filter('.card')->eq(1)->text());
    }

    public function testTransactionAmountsUseTheBudgetCurrencySymbol(): void
    {
        $budget = $this->createBudget('Dollar budget', '100.00', 'USD');
        $this->createTransaction('Online purchase', '25.00', $budget);

        $crawler = $this->client->request('GET', '/transaction');

        $this->assertResponseIsSuccessful();

        $item = $crawler->filter('[data-swipe-item]')->first();
        $this->assertStringContainsString('$25.00', $item->text());
        $this->assertStringContainsString('$25.00', $crawler->filter('.card')->eq(1)->text());
    }

    public function testCreateTransactionFromModal(): void
    {
        $budget = $this->createBudget('Transportation');

        $crawler = $this->client->request('GET', '/transaction');

        $form = $crawler->filter('#createTransactionModal form[name="transaction"]')->form([
            'transaction[description]' => 'Jeepney fare',
            'transaction[amount]' => '15.00',
            'transaction[budget]' => (string) $budget->getBudgetId(),
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/transaction');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.flash--success');
        $this->assertCount(1, $crawler->filter('[data-swipe-item]'));
        $itemText = $crawler->filter('[data-swipe-item]')->text();
        $this->assertStringContainsString('Jeepney fare', $itemText);
        $this->assertStringContainsString('15.00', $itemText);
        $this->assertStringContainsString('Transportation', $itemText);

        // The selected budget is persisted as budget_id, not just shown on screen.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $created = $entityManager->getRepository(TransactionEntity::class)->findOneBy(['description' => 'Jeepney fare']);
        $this->assertSame($budget->getBudgetId(), $created?->getBudget()?->getBudgetId());
    }

    public function testCreateTransactionValidationReopensModal(): void
    {
        $crawler = $this->client->request('GET', '/transaction');

        $form = $crawler->filter('#createTransactionModal form[name="transaction"]')->form([
            'transaction[description]' => '',
            'transaction[amount]' => '',
        ]);

        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('body[data-open-modal="createTransactionModal"]');
        $this->assertSelectorExists('.form-field ul');
        $this->assertStringContainsString('Please give this transaction a description.', $crawler->filter('.modal-body')->text());
        $this->assertStringContainsString('Choose the budget this transaction belongs to.', $crawler->filter('.modal-body')->text());
        $this->assertSelectorNotExists('[data-swipe-item]');
    }

    public function testEditTransactionFromModal(): void
    {
        $budget = $this->createBudget('Load / Internet');
        $secondBudget = $this->createBudget('Transportation');
        $transaction = $this->createTransaction('Globe Load', '100.00', $budget);
        $id = $transaction->getTransactionId();

        $crawler = $this->client->request('GET', '/transaction');

        $form = $crawler->filter('#editTransactionModal'.$id.' form[name="transaction_edit_'.$id.'"]')->form([
            'transaction_edit_'.$id.'[description]' => 'PLDT Wifi',
            'transaction_edit_'.$id.'[amount]' => '400.00',
            'transaction_edit_'.$id.'[budget]' => (string) $secondBudget->getBudgetId(),
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/transaction');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.flash--success');
        $itemText = $crawler->filter('[data-swipe-item]')->text();
        $this->assertStringContainsString('PLDT Wifi', $itemText);
        $this->assertStringContainsString('400.00', $itemText);
        $this->assertStringContainsString('Transportation', $itemText);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $updated = $entityManager->find(TransactionEntity::class, $id);

        $this->assertSame('PLDT Wifi', $updated?->getDescription());
        $this->assertEqualsWithDelta(400.00, (float) $updated?->getAmount(), 0.001);
        $this->assertSame($secondBudget->getBudgetId(), $updated?->getBudget()?->getBudgetId());
    }

    public function testDeleteTransactionWithCsrfToken(): void
    {
        $transaction = $this->createTransaction('Temporary spend', '50.00');
        $id = $transaction->getTransactionId();

        $crawler = $this->client->request('GET', '/transaction');
        $form = $crawler->filter('form[action="/transaction/'.$id.'/delete"]')->form();

        $this->client->submit($form);

        $this->assertResponseRedirects('/transaction');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.flash--success');
        $this->assertSelectorNotExists('[data-swipe-item]');
        $this->assertSelectorExists('.empty-state');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $this->assertNull($entityManager->find(TransactionEntity::class, $id));
    }

    public function testDeleteTransactionRejectsInvalidCsrfToken(): void
    {
        $transaction = $this->createTransaction('Protected spend', '75.00');

        $this->client->request('POST', '/transaction/'.$transaction->getTransactionId().'/delete', ['_token' => 'not-a-real-token']);

        $this->assertResponseRedirects('/transaction');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.flash--error');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $this->assertNotNull($entityManager->find(TransactionEntity::class, $transaction->getTransactionId()));
    }

    public function testTransactionPageOnlyListsTheSignedInUsersTransactions(): void
    {
        $otherUser = $this->createUser('other_user');
        $otherBudget = $this->createBudget('Other budget', '100.00', 'PHP', $otherUser);
        $this->createTransaction('Other spend', '10.00', $otherBudget, $otherUser);

        $budget = $this->createBudget('My budget');
        $this->createTransaction('My spend', '5.00', $budget);

        $crawler = $this->client->request('GET', '/transaction');

        $this->assertResponseIsSuccessful();
        $pageText = $crawler->filter('.page')->text();
        $this->assertStringContainsString('My spend', $pageText);
        $this->assertStringNotContainsString('Other spend', $pageText);
        $this->assertStringNotContainsString('Other budget', $pageText);
        $this->assertCount(1, $crawler->filter('[data-swipe-item]'));
    }

    public function testCannotEditAnotherUsersTransaction(): void
    {
        $otherUser = $this->createUser('other_user');
        $otherBudget = $this->createBudget('Other budget', '100.00', 'PHP', $otherUser);
        $transaction = $this->createTransaction('Other spend', '10.00', $otherBudget, $otherUser);

        $this->client->request('POST', '/transaction/'.$transaction->getTransactionId().'/edit', [
            'transaction_edit_'.$transaction->getTransactionId().'[description]' => 'Changed',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateFormOnlyListsTheSignedInUsersBudgets(): void
    {
        $otherUser = $this->createUser('other_user');
        $otherBudget = $this->createBudget('Other budget', '100.00', 'PHP', $otherUser);
        $myBudget = $this->createBudget('My budget');

        $crawler = $this->client->request('GET', '/transaction');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('#createTransactionModal select[name="transaction[budget]"] option[value="'.$otherBudget->getBudgetId().'"]'));
        $this->assertCount(1, $crawler->filter('#createTransactionModal select[name="transaction[budget]"] option[value="'.$myBudget->getBudgetId().'"]'));
    }
}
