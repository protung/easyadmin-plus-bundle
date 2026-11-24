<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Filter;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Protung\EasyAdminPlusBundle\Filter\EnumFilter;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminControllerWebTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\TaskCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Task;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Enum\TaskStatus;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

/**
 * The filter as rendered and applied by EasyAdmin, which builds its form.
 *
 * @template-extends AdminControllerWebTestCase<TaskCrudController>
 */
#[CoversClass(EnumFilter::class)]
final class EnumFilterTest extends AdminControllerWebTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();
        $metadata      = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool    = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        foreach ([1 => TaskStatus::Open, 2 => TaskStatus::Done, 3 => null] as $id => $status) {
            $task         = new Task();
            $task->id     = $id;
            $task->status = $status;
            $entityManager->persist($task);
        }

        $entityManager->flush();
        $entityManager->clear();

        // a failure in building the form is reported as it is instead of as a response with status 500.
        $this->getClient()->catchExceptions(false);
    }

    #[Override]
    protected static function usePrettyUrls(): bool
    {
        return true;
    }

    #[Override]
    protected function controllerUnderTest(): string
    {
        return TaskCrudController::class;
    }

    #[Override]
    protected function actionName(): string
    {
        return 'renderFilters';
    }

    public function testFilterFormOffersTheCasesOfTheEnum(): void
    {
        $options = $this->assertRequestGet()->filter('select[name="filters[status][value]"] option');

        self::assertSame(
            [['is-open', 'Open'], ['is-done', 'Done']],
            $options->each(static fn (Crawler $option): array => [$option->attr('value'), $option->text(normalizeWhitespace: true)]),
        );
    }

    public function testFilterFormSelectsTheAppliedCase(): void
    {
        $crawler = $this->assertRequestGet(
            [EA::FILTERS => ['status' => ['comparison' => ComparisonType::EQ, 'value' => TaskStatus::Done->value]]],
        );

        self::assertSame(
            ['is-done'],
            $crawler->filter('select[name="filters[status][value]"] option[selected]')->extract(['value']),
        );
    }

    /**
     * @param list<string> $expectedIds
     */
    #[DataProvider('provideSubmittedFilters')]
    public function testSubmittingTheFilterFormFiltersTheList(string $comparison, string $value, array $expectedIds): void
    {
        $form = $this->assertRequestGet()->filter('form[name="filters"]')->form();

        $crawler = $this->getClient()->submit(
            $form,
            [EA::FILTERS => ['status' => ['comparison' => $comparison, 'value' => $value]]],
        );

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_OK);
        self::assertSame($expectedIds, $crawler->filter('#main table>tbody tr[data-id]')->extract(['data-id']));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideSubmittedFilters(): iterable
    {
        yield 'is a case' => [ComparisonType::EQ, TaskStatus::Open->value, ['1']];
        yield 'is another case' => [ComparisonType::EQ, TaskStatus::Done->value, ['2']];
        yield 'is not a case' => [ComparisonType::NEQ, TaskStatus::Open->value, ['2', '3']];
    }
}
