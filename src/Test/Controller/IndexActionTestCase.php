<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Psl\Dict;
use Psl\Type;
use Psl\Vec;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class IndexActionTestCase extends AdminControllerWebTestCase
{
    protected function actionName(): string
    {
        return Action::INDEX;
    }

    protected function datagridRowSelector(): string
    {
        return $this->mainContentSelector() . ' table>tbody tr';
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    public function testPageLoadsWithEmptyList(array $queryParameters = []): void
    {
        $crawler = $this->assertRequestGet($queryParameters);

        self::assertSame(
            Vec\concat(Vec\fill(3, 'empty-row'), ['no-results'], Vec\fill(11, 'empty-row')),
            $crawler->filter($this->datagridRowSelector())->extract(['class']),
        );
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertPage(array $queryParameters = []): void
    {
        $this->assertRequestGet($queryParameters);

        $actual = [
            'page_title' => $this->extractPageTitle(),
            'datagrid_headers' => $this->extractDatagridHeaders(),
            'datagrid_rows' => $this->extractDatagridRows(),
            'global_actions' => $this->extractGlobalActions(),
        ];

        $this->assertArrayMatchesExpectedJson($actual);
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertSearch(string $searchQuery, array $queryParameters = []): void
    {
        $queryParameters[EA::QUERY] = $searchQuery;
        $this->assertListOfIds($queryParameters);

        self::assertCount(
            1,
            $this->getClient()->getCrawler()->filter('form.form-action-search'),
            'Search form field was not present on page.',
        );
    }

    /**
     * @param array<array-key, mixed> $filters
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertFilters(array $filters, array $queryParameters = []): void
    {
        $queryParameters[EA::FILTERS] = $filters;
        $this->assertListOfIds($queryParameters);
    }

    /**
     * @param array<array-key, mixed> $sorting
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertSorting(array $sorting, array $queryParameters = []): void
    {
        $queryParameters[EA::SORT] = $sorting;
        $this->assertListOfIds($queryParameters);
    }

    protected function extractPageTitle(): string|null
    {
        $title = $this->getClient()->getCrawler()->filter('h1.title');

        if ($title->count() > 0) {
            return $title->text(normalizeWhitespace: true);
        }

        return null;
    }

    /**
     * @return list<array<mixed>>
     */
    protected function extractDatagridRows(): array
    {
        $headers = $this->extractDatagridHeaders();

        $rows = $this->getClient()->getCrawler()->filter($this->datagridRowSelector())->each(
            function (Crawler $tr): array {
                return $tr->filter('td')->each(
                    function (Crawler $column): array|string|bool {
                        if ($column->matches('.actions')) {
                            return $this->mapActions($column->filter('[data-action-name]'));
                        }

                        if ($column->matches('.has-switch')) {
                            return $column->filter('input.form-check-input:checked')->count() > 0;
                        }

                        return $column->text(normalizeWhitespace: true);
                    },
                );
            },
        );

        return Vec\map(
            $rows,
            static fn (array $row): array => Dict\associate($headers, $row),
        );
    }

    /**
     * @return non-empty-list<string>
     */
    protected function extractDatagridHeaders(): array
    {
        $datagridHeaders = $this->getClient()->getCrawler()->filter($this->mainContentSelector() . ' table>thead>tr>th');

        return Type\non_empty_vec(Type\string())->coerce(
            $datagridHeaders->each(
                static fn (Crawler $th): string => $th->text(normalizeWhitespace: true),
            ),
        );
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function extractGlobalActions(): array
    {
        return $this->mapActions(
            $this->getClient()->getCrawler()->filter('.global-actions')->filter('[data-action-name]'),
        );
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    private function assertListOfIds(array $queryParameters): void
    {
        $this->assertRequestGet($queryParameters);

        $rows = $this->getClient()->getCrawler()->filter($this->datagridRowSelector())->each(
            static fn (Crawler $row): string => $row->attr('data-id') ?? '',
        );

        $this->assertArrayMatchesExpectedJson($rows);
    }

    /**
     * @return array<mixed>|string
     */
    protected function extractDataFromElement(Crawler $elementToScrapeCrawler): array|string
    {
        if ($elementToScrapeCrawler->nodeName() === 'table') {
            return $elementToScrapeCrawler->filter('tr')->each(
                static function (Crawler $crawler): array {
                    return $crawler->filter('td')->each(
                        static function (Crawler $tdCrawler): string {
                            return $tdCrawler->text(normalizeWhitespace: true);
                        },
                    );
                },
            );
        }

        if ($elementToScrapeCrawler->nodeName() === 'ul') {
            return $elementToScrapeCrawler->filter('li')->each(
                static function (Crawler $crawler): string {
                    return $crawler->text(normalizeWhitespace: true);
                },
            );
        }

        if ($elementToScrapeCrawler->nodeName() === 'img') {
            return $elementToScrapeCrawler->extract(['src', 'alt']);
        }

        // we fall back on getting all text inside element
        return $elementToScrapeCrawler->text(normalizeWhitespace: true);
    }
}
