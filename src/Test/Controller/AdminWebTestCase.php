<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use Psl\Iter;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use Speicher210\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AdminWebTestCase extends WebTestCase
{
    private KernelBrowser|null $client = null;

    /**
     * The authenticated user for the test.
     */
    private static UserInterface|null $authentication = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();
    }

    protected static function authenticationFirewallContext(): string
    {
        return 'easyadmin';
    }

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

    protected function loginAs(UserInterface|null $user): void
    {
        self::$authentication = $user;
    }

    protected function loginAsAdmin(): void
    {
        $user = new InMemoryUser('admin-test', 'admin-test', ['ROLE_ADMIN']);
        $this->loginAs($user);
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
