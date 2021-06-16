<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use Psl\Dict;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

use function array_keys;
use function assert;
use function count;
use function str_starts_with;

abstract class DashboardControllerTestCase extends AdminWebTestCase
{
    /**
     * @param array<string,string|null> $expectedMenuItems
     */
    protected function assertMenu(array $expectedMenuItems): void
    {
        $router = $this->getContainerService(RouterInterface::class);
        assert($router instanceof Router);

        /** @var iterable<string, Route> $routes */
        $routes = $router->getRouteCollection();

        $dashboardRoutes = Dict\filter(
            $routes,
            fn (Route $route): bool => str_starts_with((string) $route->getDefault('_controller'), $this->getDashboardControllerFqcn())
        );

        self::assertCount(1, $dashboardRoutes);

        $dashboardRouteName = array_keys($dashboardRoutes)[0];

        $client                  = $this->getClient();
        $originalFollowRedirects = $client->isFollowingRedirects();
        $client->followRedirects();
        $crawler = $client->request(Request::METHOD_GET, $router->generate($dashboardRouteName));
        $client->followRedirects($originalFollowRedirects);

        self::assertGreaterThan(0, $crawler->filter('#main-menu ul.menu')->count(), 'Menu DOM element does not exist.');

        /** @var array<int,array<string,string|null>> $actualMenuItems */
        $actualMenuItems = $crawler->filter('#main-menu ul.menu li')->each(
            static function (Crawler $menuElement): array {
                $url          = null;
                $menuItemLink = $menuElement->filter('a')->first();

                if (count($menuItemLink) > 0) {
                    $url = $menuItemLink->attr('href');
                }

                return [$menuElement->text() => $url];
            }
        );

        self::assertEquals($expectedMenuItems, Dict\flatten($actualMenuItems));
    }

    abstract protected function getDashboardControllerFqcn(): string;
}
