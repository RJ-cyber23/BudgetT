<?php

namespace App\Tests\Support;

use App\Entity\BudgetEntity;
use App\Entity\TransactionEntity;
use App\Entity\UsersEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;
    protected UsersEntity $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->purgeData();

        $this->user = $this->createUser('juan_dela_cruz');
        $this->client->loginUser($this->user);
    }

    protected function purgeData(): void
    {
        foreach ($this->entityManager->getRepository(TransactionEntity::class)->findAll() as $transaction) {
            $this->entityManager->remove($transaction);
        }
        $this->entityManager->flush();

        foreach ($this->entityManager->getRepository(BudgetEntity::class)->findAll() as $budget) {
            $this->entityManager->remove($budget);
        }
        $this->entityManager->flush();

        foreach ($this->entityManager->getRepository(UsersEntity::class)->findAll() as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }

    protected function createUser(string $username, string $password = 'not-used-in-tests'): UsersEntity
    {
        $user = (new UsersEntity())
            ->setUsername($username)
            ->setPassword($password);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function createBudget(
        string $description,
        string $amount = '1000.00',
        string $currency = 'PHP',
        ?UsersEntity $owner = null,
    ): BudgetEntity {
        $budget = (new BudgetEntity())
            ->setUserId(($owner ?? $this->user)->getUserId())
            ->setDescription($description)
            ->setAmount($amount)
            ->setCurrencyType($currency);

        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        return $budget;
    }

    protected function createTransaction(
        string $description,
        string $amount,
        ?BudgetEntity $budget = null,
        ?UsersEntity $owner = null,
    ): TransactionEntity {
        $transaction = (new TransactionEntity())
            ->setUserId(($owner ?? $this->user)->getUserId())
            ->setDescription($description)
            ->setAmount($amount)
            ->setBudget($budget);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }
}
