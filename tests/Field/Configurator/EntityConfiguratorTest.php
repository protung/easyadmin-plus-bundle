<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Field\Configurator;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Protung\EasyAdminPlusBundle\Field\Configurator\EntityConfigurator;
use Protung\EasyAdminPlusBundle\Test\Controller\DetailActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportLinkCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportLinkDashboardController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The links of entity fields to the detail page of the linked entity.
 *
 * @template-extends DetailActionTestCase<ReportLinkCrudController>
 */
#[CoversClass(EntityConfigurator::class)]
final class EntityConfiguratorTest extends DetailActionTestCase
{
    protected static string|int $expectedEntityIdUnderTest = 1;

    /** @var class-string<DashboardControllerInterface>|null */
    private string|null $dashboardControllerFqcn = null;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();
        $metadata      = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool    = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $report     = new Report();
        $report->id = 1;
        $entityManager->persist($report);
        $entityManager->flush();
        $entityManager->clear();
    }

    #[Override]
    protected static function usePrettyUrls(): bool
    {
        return true;
    }

    #[Override]
    protected function controllerUnderTest(): string
    {
        return ReportLinkCrudController::class;
    }

    #[Override]
    protected function dashboardControllerFqcn(): string|null
    {
        return $this->dashboardControllerFqcn;
    }

    public function testLinkGoesThroughTheCurrentDashboardWhenItHasARouteForTheTarget(): void
    {
        $this->assertRequestGet();

        self::assertSame('http://localhost/admin/report/1', $this->renderedField('Report')->filter('a')->attr('href'));
    }

    public function testLinkGoesThroughAnotherDashboardWhenOnlyItHasARouteForTheTarget(): void
    {
        $this->dashboardControllerFqcn = ReportLinkDashboardController::class;

        $this->assertRequestGet();

        self::assertSame('http://localhost/admin/report/1', $this->renderedField('Report')->filter('a')->attr('href'));
    }

    public function testNoLinkWhenNoDashboardHasARouteForTheTarget(): void
    {
        $this->assertRequestGet();

        $field = $this->renderedField('Unrouted report');
        self::assertSame('1', $this->extractFieldValue($field));
        self::assertCount(0, $field->filter('a'));
    }

    private function renderedField(string $label): Crawler
    {
        $field = $this->getClient()->getCrawler()
            ->filter($this->mainContentSelector() . ' ' . $this->fieldSelector())
            ->reduce(static fn (Crawler $field): bool => $field->filter('div.field-label')->text(normalizeWhitespace: true) === $label);

        self::assertCount(1, $field);

        return $field;
    }
}
