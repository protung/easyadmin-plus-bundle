<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psl\Dict;
use Psl\Iter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function count;
use function is_countable;

/**
 * @template TDashboardController
 */
abstract class DashboardControllerTestCase extends AdminWebTestCase
{
    /**
     * @param iterable<string,string|null> $expectedMenuItems
     * @param array<mixed>                 $routeParameters
     */
    protected function assertMenu(iterable $expectedMenuItems, array $routeParameters = []): void
    {
        $client                  = $this->getClient();
        $originalFollowRedirects = $client->isFollowingRedirects();
        $client->followRedirects();
        $crawler = $client->request(Request::METHOD_GET, $this->prepareDashboardUrl($routeParameters));
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
     * @param array<mixed> $routeParameters
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
     * @param array<mixed> $routeParameters
     */
    protected function prepareDashboardUrl(array $routeParameters): string
    {
        return $this->getContainerService(AdminUrlGenerator::class)
            ->setAll(Dict\sort_by_key($routeParameters))
            ->setDashboard($this->getDashboardControllerFqcn())
            ->generateUrl();
    }

    /**
     * @return class-string<TDashboardController>
     */
    abstract protected function getDashboardControllerFqcn(): string;
}
