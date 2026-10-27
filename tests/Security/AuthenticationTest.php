<?php

namespace App\Tests\Security;

use App\Entity\BudgetEntity;
use App\Entity\TransactionEntity;
use App\Entity\UsersEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthenticationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

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

    public function testLandingPageRedirectsAnonymousUsersToLogin(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseRedirects('/login');
    }

    public function testLoginPageRendersTheFormAndRegistrationLink(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action="/login"]');
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
        $this->assertSelectorExists('input[name="_csrf_token"]');
        $this->assertCount(1, $crawler->filter('a[href="/register"]'));
    }

    public function testRegistrationFormStylesBothPasswordFieldsAsAuthFields(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('.auth-field input#registration_form_plainPassword_first'));
        $this->assertCount(1, $crawler->filter('.auth-field input#registration_form_plainPassword_second'));
    }

    public function testProtectedPagesRedirectAnonymousUsersToLogin(): void
    {
        foreach (['/home', '/budget', '/transaction', '/report'] as $path) {
            $this->client->request('GET', $path);

            $this->assertResponseRedirects('/login', null, sprintf('Expected %s to require authentication.', $path));
        }
    }

    public function testRegistrationCreatesHashedUserAndLogsIn(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'new_user',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/home');

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('new_user', $crawler->filter('#rightNav')->text());

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(UsersEntity::class)->findOneBy(['username' => 'new_user']);

        $this->assertNotNull($user);
        $this->assertNotSame('password123', $user->getPassword());
        $this->assertTrue(password_verify('password123', (string) $user->getPassword()));
    }

    public function testRegistrationRejectsDuplicateUsername(): void
    {
        $this->createUser('taken_user');

        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'taken_user',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);

        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('This username is already taken.', $crawler->filter('main')->text());
        $this->assertSame(1, $this->entityManager->getRepository(UsersEntity::class)->count([]));
    }

    public function testRegistrationRejectsAShortPassword(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'short_password_user',
            'registration_form[plainPassword][first]' => 'short',
            'registration_form[plainPassword][second]' => 'short',
        ]);

        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Your password should be at least 8 characters long.', $crawler->filter('main')->text());
        $this->assertSame(0, $this->entityManager->getRepository(UsersEntity::class)->count([]));
    }

    public function testRegistrationRejectsMismatchedPasswords(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'mismatch_user',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password124',
        ]);

        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('The password fields must match.', $crawler->filter('main')->text());
        $this->assertSame(0, $this->entityManager->getRepository(UsersEntity::class)->count([]));
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->createUser('login_user', 'password123');

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'login_user',
            '_password' => 'password123',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/home');

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('login_user', $crawler->filter('#rightNav')->text());
    }

    public function testLoginWithInvalidCredentialsShowsAnError(): void
    {
        $this->createUser('login_user', 'password123');

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'login_user',
            '_password' => 'wrong-password',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/login');

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.auth-alert');
        $this->assertStringContainsString('Invalid credentials', $crawler->filter('.auth-alert')->text());
    }

    public function testLogoutClearsTheSession(): void
    {
        $user = $this->createUser('logout_user', 'password123');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/home');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form.logout-form')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/login');

        $this->client->request('GET', '/home');
        $this->assertResponseRedirects('/login');
    }

    public function testAuthenticatedUsersAreRedirectedAwayFromLoginAndRegistration(): void
    {
        $user = $this->createUser('already_in', 'password123');
        $this->client->loginUser($user);

        $this->client->request('GET', '/login');
        $this->assertResponseRedirects('/home');

        $this->client->request('GET', '/register');
        $this->assertResponseRedirects('/home');

        $this->client->request('GET', '/');
        $this->assertResponseRedirects('/home');
    }

    private function createUser(string $username, string $plainPassword = 'password123'): UsersEntity
    {
        $user = (new UsersEntity())
            ->setUsername($username)
            ->setPassword(password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 4]));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
