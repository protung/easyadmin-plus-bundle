<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Symfony\Component\DomCrawler\Crawler;

use function assert;
use function explode;
use function implode;
use function Psl\Iter\first;
use function Psl\Vec\filter;
use function Safe\sprintf;
use function Safe\substr;
use function str_starts_with;
use function trim;

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
                sprintf(
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
     * @param list<list<string>> $expectedRows
     * @param array<mixed>       $queryParameters
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
     * @param array<mixed> $filters
     * @param list<string> $expectedIds
     * @param array<mixed> $queryParameters
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
     * @param list<string> ...$expectedRows
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
     * @param list<string> $expectedRowData
     * @param int          $rowNumber       The row number in the list (zero based).
     */
    private function assertContentListRow(array $expectedRowData, int $rowNumber = 0): void
    {
        $rowData = $this->getClient()->getCrawler()->filter('#main table>tbody tr')->eq($rowNumber)->filter('td')->each(
            static function (Crawler $column): string {
                $actions = $column->filter('.actions');

                if ($actions->count() === 0) {
                    return $column->text();
                }

                if ($actions->filter('.dropdown-actions')->count() > 0) {
                    $actions = $actions->filter('.dropdown-actions')->filter('.dropdown-menu');
                }

                return implode(
                    ' ',
                    $actions->children()->each(
                        static function (Crawler $crawler): string {
                            $actionClassName = first(
                                filter(
                                    explode(' ', trim((string) first($crawler->extract(['class'])))),
                                    static fn (string $class) => str_starts_with(trim($class), 'action-'),
                                )
                            );

                            assert($actionClassName !== null);

                            return substr($actionClassName, 7);
                        }
                    )
                );
            }
        );

        $this->assertMatchesPattern($expectedRowData, $rowData);
    }
}
