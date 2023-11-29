<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use Psl\Dict;
use Psl\Iter;
use Psl\Str;
use Psl\Type;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

use function count;
use function is_countable;

/**
 * @template TDashboardController
 */
abstract class DashboardControllerTestCase extends AdminWebTestCase
{
    /**
     * @param iterable<string,string|null> $expectedMenuItems
     */
    protected function assertMenu(iterable $expectedMenuItems): void
    {
        $router = Type\instance_of(Router::class)->coerce($this->getContainerService(Router::class));

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

        if (! is_countable($expectedMenuItems)) {
            $expectedMenuItems = Iter\to_iterator($expectedMenuItems);
        }

        self::assertCount(
            $crawler->filter('#main-menu ul.menu li')->count(),
            $expectedMenuItems,
        );

        $index = 0;
        foreach ($expectedMenuItems as $label => $value) {
            $menuElement = $crawler->filter('#main-menu ul.menu li')->eq($index++);

            $menuItemLink = $menuElement->filter('a')->first();

            $url = null;
            if (count($menuItemLink) > 0) {
                $url = $menuItemLink->attr('href');
            }

            self::assertSame($label, $menuElement->text(normalizeWhitespace: true));
            self::assertSame($value, $url);
        }
    }

    /**
     * @return class-string<TDashboardController>
     */
    abstract protected function getDashboardControllerFqcn(): string;
}
