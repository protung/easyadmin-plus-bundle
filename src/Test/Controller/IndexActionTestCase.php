<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Psl\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class IndexActionTestCase extends AdminControllerWebTestCase
{
    protected static ?string $expectedPageTitle = null;

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

    public function testPageLoadsWithEmptyList(): void
    {
        $crawler = $this->assertRequestGet([EA::CRUD_ACTION => Action::INDEX]);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        self::assertCount(1, $crawler->filter('#main table>tbody tr'));
        self::assertStringContainsString(
            'no-results',
            (string) $crawler->filter('#main table>tbody tr td')->first()->attr('class')
        );
    }

    /**
     * @param list<list<string|array<string,string>>> $expectedRows
     * @param array<array-key, mixed>                 $queryParameters
     */
    protected function assertPage(array $expectedRows, array $queryParameters = []): void
    {
        $queryParameters[EA::CRUD_ACTION] = Action::INDEX;
        $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $this->assertContentListHeaders();

        $this->assertContentListRows(...$expectedRows);
    }

    /**
     * @param array<array-key, mixed> $filters
     * @param list<string>            $expectedIds
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertFilters(array $filters, array $expectedIds, array $queryParameters = []): void
    {
        $queryParameters[EA::CRUD_ACTION] = Action::INDEX;
        $queryParameters[EA::FILTERS]     = $filters;

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
            static fn (Crawler $th): string => $th->text()
        );
        self::assertSame($this->expectedListHeader(), $actualHeaders);
    }

    /**
     * @param list<string|array<string,string>> ...$expectedRows
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
     * @param list<string|array<string,string>> $expectedRowData
     * @param int                               $rowNumber       The row number in the list (zero based).
     */
    private function assertContentListRow(array $expectedRowData, int $rowNumber): void
    {
        $rowData = $this->getClient()->getCrawler()->filter('#main table>tbody tr')->eq($rowNumber)->filter('td')->each(
            function (Crawler $column): array|string {
                $actions = $column->filter('.actions');

                if ($actions->count() === 0) {
                    return $column->text();
                }

                if ($actions->filter('.dropdown-actions')->count() > 0) {
                    $actions = $actions->filter('.dropdown-actions')->filter('.dropdown-menu');
                }

                return $this->mapActions($actions->children());
            }
        );

        $this->assertMatchesPattern($expectedRowData, $rowData);
    }
}
