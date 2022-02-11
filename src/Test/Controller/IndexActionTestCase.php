<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Psl\Str;
use Psl\Vec;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class IndexActionTestCase extends AdminControllerWebTestCase
{
    protected static ?string $expectedPageTitle = null;

    protected function actionName(): string
    {
        return Action::INDEX;
    }

    protected function expectedPageTitle(): ?string
    {
        if (static::$expectedPageTitle === null) {
            throw new LogicException(
                Str\format(
                    <<<'MSG'
                        Expected page title was not set.
                        Please set static::$expectedPageTitle property in your test or overwrite %1$s method.
                        If your index page does not have a title you need to overwrite %1$s method and return NULL.
                    MSG,
                    __METHOD__
                )
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
            $crawler->filter('#main table>tbody tr')->extract(['class'])
        );
    }

    /**
     * @param list<list<string|array<string,string>|bool>> $expectedRows
     * @param array<array-key, mixed>                      $queryParameters
     */
    protected function assertPage(array $expectedRows, array $queryParameters = []): void
    {
        $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $this->assertContentListHeaders();

        $this->assertContentListRows(...$expectedRows);
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
        $crawler = $this->getClient()->getCrawler();

        $actualHeaders = $crawler->filter('#main table>thead>tr>th')->each(
            static fn (Crawler $th): string => $th->text(normalizeWhitespace: true)
        );
        self::assertSame($this->expectedListHeader(), $actualHeaders);
    }

    /**
     * @param list<string|array<string,string>|bool> ...$expectedRows
     */
    protected function assertContentListRows(array ...$expectedRows): void
    {
        self::assertCount(
            $this->getClient()->getCrawler()->filter('#main table>tbody tr')->count(),
            $expectedRows
        );

        $rowNumber = 0;
        foreach ($expectedRows as $expectedRow) {
            $this->assertContentListRow($expectedRow, $rowNumber);
            $rowNumber++;
        }
    }

    /**
     * @param list<string|array<string,string>|bool> $expectedRowData
     * @param int                                    $rowNumber       The row number in the list (zero based).
     */
    private function assertContentListRow(array $expectedRowData, int $rowNumber): void
    {
        $rowData = $this->getClient()->getCrawler()->filter('#main table>tbody tr')->eq($rowNumber)->filter('td')->each(
            function (Crawler $column): array|string|bool {
                if ($column->matches('.actions')) {
                    return $this->mapActions($column->filter('[data-action-name]'));
                }

                if ($column->matches('.has-switch')) {
                    return $column->filter('input.form-check-input:checked')->count() > 0;
                }

                return $column->text(normalizeWhitespace: true);
            }
        );

        $this->assertMatchesPattern($expectedRowData, $rowData);
    }
}
