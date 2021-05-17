<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\DomCrawler\Crawler;

use function assert;
use function explode;
use function implode;
use function Psl\Iter\first;
use function Psl\Vec\filter;
use function Safe\substr;
use function str_starts_with;
use function trim;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class IndexActionTestCase extends AdminControllerWebTestCase
{
    abstract protected function expectedPageTitle(): string;

    /**
     * @return list<string>
     */
    abstract protected function expectedListHeader(): array;

    public function testPageLoadsWithEmptyList(): void
    {
        $queryParameters = [EA::CRUD_ACTION => Action::INDEX];

        $crawler = $this->assertRequestGet($queryParameters);

        $this->assertPageTitle($this->expectedPageTitle());

        self::assertCount(1, $crawler->filter('#main table>tbody tr'));
        self::assertStringContainsString(
            'no-results',
            (string) $crawler->filter('#main table>tbody tr td')->first()->attr('class')
        );
    }

    /**
     * @param array<mixed> $filters
     * @param list<string> $expectedIds
     */
    protected function assertFilter(array $filters, array $expectedIds): void
    {
        $queryParameters = [
            EA::CRUD_ACTION => Action::INDEX,
            EA::FILTERS => $filters,
        ];

        $this->assertRequestGet($queryParameters);
        $this->assertPageTitle($this->expectedPageTitle());

        $rowData = $this->getClient()->getCrawler()->filter('#main table>tbody tr')->each(
            static fn (Crawler $row): string => $row->attr('data-id') ?? ''
        );

        self::assertSame($expectedIds, $rowData);
    }

    protected function assertPageTitle(string $expectedPageTitle): void
    {
        $crawler = $this->getClient()->getCrawler();

        self::assertCount(1, $crawler->filter('h1'));
        self::assertSame($expectedPageTitle, trim($crawler->filter('h1')->text()));
    }

    protected function assertHeaders(): void
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
    protected function assertRows(array ...$expectedRows): void
    {
        self::assertCount(
            $this->getClient()->getCrawler()->filter('#main table>tbody tr')->count(),
            $expectedRows
        );

        $rowNumber = 0;
        foreach ($expectedRows as $expectedRow) {
            $this->assertRow($expectedRow, $rowNumber);
            $rowNumber++;
        }
    }

    /**
     * @param list<string> $expectedRowData
     * @param int          $rowNumber       The row number in the list (zero based).
     */
    private function assertRow(array $expectedRowData, int $rowNumber = 0): void
    {
        $rowData = $this->getClient()->getCrawler()->filter('#main table>tbody tr')->eq($rowNumber)->filter('td')->each(
            static function (Crawler $column): string {
                $actions = $column->filter('.actions');

                if ($actions->count() === 0) {
                    return $column->text();
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
