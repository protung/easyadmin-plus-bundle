<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use Psl\Dict;
use Psl\Iter;
use Psl\Str;
use Psl\Type;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

use function count;

/**
 * @template TDashboardController
 */
abstract class DashboardControllerTestCase extends AdminWebTestCase
{
    /**
     * @param array<string,string|null> $expectedMenuItems
     */
    protected function assertMenu(array $expectedMenuItems): void
    {
        $router = Type\object(Router::class)->coerce($this->getContainerService('router'));

        $dashboardRoutes = Dict\filter(
            $router->getRouteCollection()->all(),
            fn (Route $route): bool => Str\starts_with((string) $route->getDefault('_controller'), $this->getDashboardControllerFqcn())
        );

        $dashboardRouteName = Iter\first_key($dashboardRoutes);

        self::assertIsString($dashboardRouteName, 'Could not find route for the dashboard controller.');

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

    /**
     * @return class-string<TDashboardController>
     */
    abstract protected function getDashboardControllerFqcn(): string;
}
