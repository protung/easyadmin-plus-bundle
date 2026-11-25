<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Protung\EasyAdminPlusBundle\Test\Controller\RenderFiltersActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ReportCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\TaskCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Enum\TaskStatus;

/**
 * The filters of a controller, applied or not, and of a controller without filters.
 *
 * @template-extends RenderFiltersActionTestCase<ReportCrudController|TaskCrudController>
 */
#[CoversClass(RenderFiltersActionTestCase::class)]
final class RenderFiltersActionTest extends RenderFiltersActionTestCase
{
    /** @var class-string<ReportCrudController|TaskCrudController> */
    private string $controllerUnderTest = TaskCrudController::class;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();
        $metadata      = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool    = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    #[Override]
    protected static function usePrettyUrls(): bool
    {
        return true;
    }

    #[Override]
    protected function controllerUnderTest(): string
    {
        return $this->controllerUnderTest;
    }

    public function testRendersFilters(): void
    {
        $this->assertRenderingFilters();
    }

    public function testRendersAppliedFilters(): void
    {
        $this->assertRenderingFilters(
            [EA::FILTERS => ['status' => ['comparison' => ComparisonType::NEQ, 'value' => TaskStatus::Done->value]]],
        );
    }

    public function testRendersNoFilters(): void
    {
        $this->controllerUnderTest = ReportCrudController::class;

        $this->assertRenderingFilters();
    }
}
