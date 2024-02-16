<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psl\Dict;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TDashboardController
 */
abstract class DashboardControllerTestCase extends AdminWebTestCase
{
    /**
     * @param array<mixed> $routeParameters
     */
    protected function assertMenu(array $routeParameters = []): void
    {
        $client                  = $this->getClient();
        $originalFollowRedirects = $client->isFollowingRedirects();
        $client->followRedirects();
        $crawler = $client->request(Request::METHOD_GET, $this->prepareDashboardUrl($routeParameters));
        $client->followRedirects($originalFollowRedirects);

        /** @var list<array{label: string, url: string}> $actualMenuItems */
        $actualMenuItems = $crawler->filter('#main-menu ul.menu li.menu-item>a')->each(
            static fn (Crawler $menuElementLink) => [
                'label' => $menuElementLink->text(normalizeWhitespace: true),
                'url' => $menuElementLink->attr('href'),
            ],
        );

        $this->assertArrayMatchesExpectedJson($actualMenuItems);

        foreach ($actualMenuItems as ['url' => $url]) {
            $client->request(Request::METHOD_GET, $url);
            self::assertResponseStatusCode($client->getResponse(), Response::HTTP_OK, 'Menu link is not accessible.');
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
