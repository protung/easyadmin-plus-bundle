<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psl\Dict;
use Psl\Str;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_key_exists;
use function version_compare;

/**
 * @template TDashboardController of DashboardControllerInterface
 */
abstract class DashboardControllerTestCase extends AdminWebTestCase
{
    /**
     * @param array<string, mixed> $routeParameters
     */
    protected function assertMenu(array $routeParameters = []): void
    {
        $crawler = $this->makeGetRequestAndFollowRedirects(
            $this->prepareDashboardUrl($routeParameters),
        );

        $mainMenu = $crawler->filter('#main-menu');
        // EasyAdmin 5.3 replaced the menu markup with the sidebar component.
        /** @var list<array{label: string, url?: string, submenu?: list<array{label: string, url: string}>}> $actualMenuItems */
        $actualMenuItems = version_compare(EasyAdminBundle::VERSION, '5.3.0', '<')
            ? $this->extractMenuItemsBeforeEasyAdmin53($mainMenu)
            : $this->extractMenuItems($mainMenu);

        $this->assertArrayMatchesExpectedJson($actualMenuItems);
        $this->assertMenuItems($actualMenuItems);
    }

    /**
     * @param list<array{label: string, url?: string, submenu?: list<array{label: string, url: string}>}> $menuItems
     */
    protected function assertMenuItems(array $menuItems): void
    {
        foreach ($menuItems as $menuItem) {
            if (array_key_exists('url', $menuItem)) {
                $url = $menuItem['url'];

                self::ensureKernelShutdown();

                $this->makeGetRequestAndFollowRedirects($url);

                self::assertResponseStatusCode(
                    $this->getClient()->getResponse(),
                    Response::HTTP_OK,
                    Str\format('Menu link "%s" is not accessible.', $url),
                );
            }

            if (array_key_exists('submenu', $menuItem)) {
                $this->assertMenuItems($menuItem['submenu']);
            }
        }
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    protected function assertRequestGet(
        array $routeParameters = [],
        int $expectedResponseStatusCode = Response::HTTP_OK,
    ): Crawler {
        $crawler = $this->getClient()->request(Request::METHOD_GET, $this->prepareDashboardUrl($routeParameters));

        self::assertResponseStatusCode($this->getClient()->getResponse(), $expectedResponseStatusCode);

        return $crawler;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    protected function prepareDashboardUrl(array $routeParameters): string
    {
        return $this->getContainerService(AdminUrlGenerator::class)
            ->setAll(Dict\sort_by_key($routeParameters))
            ->setDashboard($this->dashboardControllerFqcn())
            ->generateUrl();
    }

    /**
     * @return list<array{label: string, url?: string|null, submenu?: list<array{label: string, url: string|null}>}>
     */
    private function extractMenuItems(Crawler $mainMenu): array
    {
        return $mainMenu->filter('ul.ea-sidebar-group-items > li.ea-sidebar-item')->each(
            static function (Crawler $menuItem) {
                $link  = $menuItem->children('.ea-sidebar-item-link');
                $label = $link->filter('.ea-sidebar-item-label')->text(normalizeWhitespace: true);

                if ($menuItem->matches('.has-submenu')) {
                    return [
                        'label' => $label,
                        'submenu' => $menuItem->filter('ul.ea-sidebar-submenu-items > li.ea-sidebar-item > a.ea-sidebar-item-link')->each(
                            static fn (Crawler $submenuLink) => [
                                'label' => $submenuLink->filter('.ea-sidebar-item-label')->text(normalizeWhitespace: true),
                                'url' => $submenuLink->attr('href'),
                            ],
                        ),
                    ];
                }

                return [
                    'label' => $label,
                    'url' => $link->attr('href'),
                ];
            },
        );
    }

    /**
     * @return list<array{label: string, url?: string|null, submenu?: list<array{label: string, url: string|null}>}>
     */
    private function extractMenuItemsBeforeEasyAdmin53(Crawler $mainMenu): array
    {
        return $mainMenu->filter('ul.menu > li.menu-item > a')->each(
            static function (Crawler $menuElementLink) {
                if (Str\contains($menuElementLink->attr('class') ?? '', 'submenu-toggle')) {
                    return [
                        'label' => $menuElementLink->text(normalizeWhitespace: true),
                        'submenu' => $menuElementLink->ancestors()->first()->filter('ul.submenu > li.menu-item > a')->each(
                            static fn (Crawler $menuElementLink) => [
                                'label' => $menuElementLink->text(normalizeWhitespace: true),
                                'url' => $menuElementLink->attr('href'),
                            ],
                        ),
                    ];
                }

                return [
                    'label' => $menuElementLink->text(normalizeWhitespace: true),
                    'url' => $menuElementLink->attr('href'),
                ];
            },
        );
    }

    private function makeGetRequestAndFollowRedirects(string $url): Crawler
    {
        $client                  = $this->getClient();
        $originalFollowRedirects = $client->isFollowingRedirects();
        $client->followRedirects();
        $crawler = $client->request(Request::METHOD_GET, $url);
        $client->followRedirects($originalFollowRedirects);

        return $crawler;
    }

    /**
     * @return class-string<TDashboardController>
     */
    abstract protected function dashboardControllerFqcn(): string;
}
