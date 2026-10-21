<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator;
use Override;
use Psl\Dict;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_merge;
use function http_build_query;
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

    /** @return non-empty-string */
    protected static function serverHost(): string
    {
        return 'localhost';
    }

    /** @return non-empty-string */
    protected static function authenticationFirewallContext(): string
    {
        return 'easyadmin';
    }

    protected static function usePrettyUrls(): bool
    {
        return false;
    }

    /** @param array<mixed> $server */
    #[Override]
    protected static function createClient(array $server = []): KernelBrowser
    {
        return parent::createClient(
            array_merge(
                ['HTTP_HOST' => static::serverHost()],
                $server,
            ),
        );
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

    protected static function loginAsDefaultUser(): UserInterface
    {
        return static::loginAsAdmin();
    }

    protected static function loginAsAdmin(): UserInterface
    {
        $user = new InMemoryUser('admin-test', 'admin-test', ['ROLE_ADMIN']);
        static::loginAs($user);

        return $user;
    }

    /**
     * A URL parameter named like a placeholder of the route (in its path or in its host) fills it, taking precedence over
     * the same placeholder in $routeParameters. All the other URL parameters are added to the query string.
     *
     * @param class-string<DashboardControllerInterface>|null $dashboardFqcn
     * @param class-string<CrudControllerInterface<*>>        $crudControllerFqcn
     * @param non-empty-string                                $actionName
     * @param array<array-key, mixed>                         $routeParameters    Only the ones the route uses fill its placeholders.
     * @param array<array-key, mixed>                         $urlParameters
     */
    protected function generateAdminPrettyUrl(
        string|null $dashboardFqcn,
        string $crudControllerFqcn,
        string $actionName,
        array $routeParameters = [],
        array $urlParameters = [],
        string|null $fragment = null,
    ): string {
        // EasyAdmin caches the names of its routes while the router loads them, which has not happened yet with a cold cache.
        $routes = $this->getContainerService(RouterInterface::class, 'router')->getRouteCollection();

        $routeName = Type\non_empty_string()->assert(
            $this->getContainerService(AdminRouteGenerator::class)->findRouteName(
                dashboardFqcn: $dashboardFqcn,
                crudControllerFqcn: $crudControllerFqcn,
                actionName: $actionName,
            ),
        );

        // both the path and the host of a route can define placeholders and both are mandatory when generating it.
        $routeVariables = Type\vec(Type\string())->coerce(
            Type\instance_of(Route::class)->assert($routes->get($routeName))->compile()->getVariables(),
        );

        // the same route parameters are prepared for every action, but each action route defines its own placeholders,
        // so keep only the ones the route actually uses, as the generator would append the others as stray query parameters.
        $placeholderParameters = Dict\select_keys(Dict\merge($routeParameters, $urlParameters), $routeVariables);

        $path = $this->getContainerService(UrlGeneratorInterface::class)->generate($routeName, $placeholderParameters);

        $queryString = $this->prepareAdminUrlQueryParameters(Dict\diff_by_key($urlParameters, $placeholderParameters));
        if ($queryString !== '') {
            $path .= '?' . $queryString;
        }

        return $path . ($fragment ?? '');
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function prepareAdminUrlQueryParameters(array $queryParameters): string
    {
        // we need to prepare the URL having some query parameters in a specific order
        $queryParameters = Dict\sort_by_key($queryParameters);

        return http_build_query($queryParameters);
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
    protected function assertFlashMessageInfo(string $expectedMessage, string ...$expectedMessages): void
    {
        $this->assertFlashMessage('info', $expectedMessage, ...$expectedMessages);
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
