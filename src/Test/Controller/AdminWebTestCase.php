<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use Override;
use Psl\Iter;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use Speicher210\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

use function serialize;

abstract class AdminWebTestCase extends WebTestCase
{
    private KernelBrowser|null $client = null;

    /**
     * The authenticated user for the test.
     */
    private static UserInterface|null $authentication = null;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        static::loginAsDefaultUser();
    }

    protected static function authenticationFirewallContext(): string
    {
        return 'easyadmin';
    }

    #[Override]
    protected function getClient(): KernelBrowser
    {
        if ($this->client === null) {
            $this->client = static::createClient();
            $this->client->disableReboot();

            if (static::$authentication !== null) {
                $this->client->loginUser(static::$authentication, static::authenticationFirewallContext());
            }
        }

        return $this->client;
    }

    /**
     * Set up a session before making a request.
     *
     * @param array<string, mixed> $sessionAttributes
     */
    #[Override]
    public function prepareSession(KernelBrowser $client, array $sessionAttributes): void
    {
        $token = $this->getContainerService(TokenStorage::class, 'security.untracked_token_storage')->getToken();

        $sessionStorageFactory = $this->getContainerService(SessionFactoryInterface::class, 'session.factory');

        $session = $sessionStorageFactory->createSession();
        $session->replace($sessionAttributes);
        if ($token !== null) {
            $session->set('_security_' . static::authenticationFirewallContext(), serialize($token));
        }

        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function submitForm(string $url, string $formName, array $data): Crawler
    {
        $crawler = $this->getClient()->request(Request::METHOD_GET, $url);

        $form = $crawler->filter(Str\format('form[name="%s"]', $formName))->form();

        $formData = [$formName => $data];

        return $this->getClient()->submit($form, $formData);
    }

    protected function followRedirect(): void
    {
        $this->getClient()->followRedirect();
    }

    protected static function loginAs(UserInterface|null $user): void
    {
        self::$authentication = $user;
    }

    protected static function loginAsDefaultUser(): void
    {
        static::loginAsAdmin();
    }

    protected static function loginAsAdmin(): void
    {
        $user = new InMemoryUser('admin-test', 'admin-test', ['ROLE_ADMIN']);
        static::loginAs($user);
    }

    /**
     * @param non-empty-string $expectedMessage
     * @param non-empty-string ...$expectedMessages
     */
    protected function assertFlashMessageSuccess(string $expectedMessage, string ...$expectedMessages): void
    {
        $this->assertFlashMessage('success', $expectedMessage, ...$expectedMessages);
    }

    /**
     * @param non-empty-string $expectedMessage
     * @param non-empty-string ...$expectedMessages
     */
    protected function assertFlashMessageWarning(string $expectedMessage, string ...$expectedMessages): void
    {
        $this->assertFlashMessage('warning', $expectedMessage, ...$expectedMessages);
    }

    /**
     * @param non-empty-string $expectedMessage
     * @param non-empty-string ...$expectedMessages
     */
    protected function assertFlashMessageError(string $expectedMessage, string ...$expectedMessages): void
    {
        $this->assertFlashMessage('danger', $expectedMessage, ...$expectedMessages);
    }

    protected function assertNoFlashMessage(): void
    {
        $crawler = $this->getClient()->getCrawler();

        $flashMessages = $crawler->filter('#flash-messages [class*="alert-"]');

        self::assertCount(
            0,
            $flashMessages,
            Str\format('Expected no flash messages, found %d.', $flashMessages->count()),
        );
    }

    /**
     * @param non-empty-string ...$expectedMessages
     */
    private function assertFlashMessage(string $type, string ...$expectedMessages): void
    {
        $crawler        = $this->getClient()->getCrawler();
        $actualMessages = Type\vec(Type\string())->assert(
            $crawler->filter('#flash-messages .alert-' . $type)
                ->each(static fn (Crawler $crawler): string => $crawler->text(normalizeWhitespace: true)),
        );

        self::assertCount(Iter\count($expectedMessages), $actualMessages);

        Vec\map(
            Vec\zip($actualMessages, $expectedMessages),
            /**
             * @param array{0: string, 1: non-empty-string} $data
             */
            static function (array $data): void {
                [$actualMessage, $expectedMessage] = $data;
                self::assertStringStartsWith($expectedMessage, $actualMessage);
            },
        );
    }
}
