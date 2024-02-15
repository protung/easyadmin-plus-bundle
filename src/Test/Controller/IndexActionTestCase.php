<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Psl\Dict;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class IndexActionTestCase extends AdminControllerWebTestCase
{
    protected static string|null $expectedPageTitle = null;

    protected function actionName(): string
    {
        return Action::INDEX;
    }

    protected function expectedPageTitle(): string|null
    {
        if (static::$expectedPageTitle === null) {
            throw new LogicException(
                Str\format(
                    <<<'MSG'
                        Expected page title was not set.
                        Please set static::$expectedPageTitle property in your test or overwrite %1$s method.
                        If your index page does not have a title you need to overwrite %1$s method and return NULL.
                    MSG,
                    __METHOD__,
                ),
            );
        }

        return static::$expectedPageTitle;
    }

    /**
     * @return list<string>
     */
    abstract protected function expectedListHeader(): array;

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    public function testPageLoadsWithEmptyList(array $queryParameters = []): void
    {
        $crawler = $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        self::assertSame(
            Vec\concat(Vec\fill(3, 'empty-row'), ['no-results'], Vec\fill(11, 'empty-row')),
            $crawler->filter('#main table>tbody tr')->extract(['class']),
        );
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertPage(array $queryParameters = []): void
    {
        $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $this->assertContentListRows();
    }

    /**
     * @param list<string>            $expectedIds
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertSearch(string $searchQuery, array $expectedIds, array $queryParameters = []): void
    {
        $queryParameters[EA::QUERY] = $searchQuery;

        $this->assertRequestGet($queryParameters);
        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $rowData = $this->getClient()->getCrawler()->filter('#main table>tbody tr')->each(
            static fn (Crawler $row): string => $row->attr('data-id') ?? ''
        );

        self::assertSame($expectedIds, $rowData);
    }

    /**
     * @param array<array-key, mixed> $filters
     * @param list<string>            $expectedIds
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertFilters(array $filters, array $expectedIds, array $queryParameters = []): void
    {
        $queryParameters[EA::FILTERS] = $filters;

        $this->assertRequestGet($queryParameters);
        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $rowData = $this->getClient()->getCrawler()->filter('#main table>tbody tr')->each(
            static fn (Crawler $row): string => $row->attr('data-id') ?? ''
        );

        self::assertSame($expectedIds, $rowData);
    }

    protected function assertContentListHeaders(): void
    {
        self::assertSame($this->expectedListHeader(), $this->responseListHeaders());
    }

    protected function assertContentListRows(): void
    {
        $actualHeaders = $this->responseListHeaders();

        $actualListContentRows = $this->responseListContentRows();

        $actual = Vec\map(
            $actualListContentRows,
            static fn (array $actualListContentRow): array => Dict\associate($actualHeaders, $actualListContentRow),
        );

        $this->assertArrayMatchesExpectedJson($actual);
    }

    /**
     * @return non-empty-list<string>
     */
    private function responseListHeaders(): array
    {
        $responseListHeadersCrawler = $this->getClient()->getCrawler()->filter('#main table>thead>tr>th');

        return Type\non_empty_vec(Type\string())->coerce(
            $responseListHeadersCrawler->each(
                static fn (Crawler $th): string => $th->text(normalizeWhitespace: true)
            ),
        );
    }

    /**
     * @return list<list<string|list<string>|array<mixed>>>
     */
    private function responseListContentRows(): array
    {
        return $this->getClient()->getCrawler()->filter('#main table>tbody tr')->each(
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
