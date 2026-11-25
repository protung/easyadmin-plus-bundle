<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\ExpectationFailedException;
use Protung\EasyAdminPlusBundle\Test\Controller\EditActionCrudDtoTestCase;
use Protung\EasyAdminPlusBundle\Test\Controller\EditActionTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\NoteCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Dto\NoteDto;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Note;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saving the form of a note without changing anything.
 *
 * @template-extends EditActionCrudDtoTestCase<Note, NoteDto, NoteCrudController>
 */
#[CoversClass(EditActionTestCase::class)]
final class EditActionWithoutChangeTest extends EditActionCrudDtoTestCase
{
    protected static string|int $expectedEntityIdUnderTest = 1;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();
        $metadata      = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool    = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $note         = new Note();
        $note->title  = 'Original';
        $note->pinned = true;
        $entityManager->persist($note);
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
        return NoteCrudController::class;
    }

    public function testPassesWhenSavingKeepsTheValues(): void
    {
        $updates = 0;
        $this->listenToUpdates(static function () use (&$updates): void {
            ++$updates;
        });

        $this->assertSubmittingFormWithoutChangeKeepsValues();

        self::assertSame(1, $updates);
        $this->clearObjectManager();
        $note = $this->getEntityUnderTest();
        self::assertSame('Original', $note->title);
        self::assertTrue($note->pinned);
    }

    public function testFailsWhenSavingChangesAStoredValue(): void
    {
        // behind the back of the entity manager, so the change can only come from the database
        $this->listenToUpdates(function (): void {
            $this->getEntityManager()->getConnection()->update('note', ['title' => 'Changed'], ['id' => 1]);
        });

        $comparisonFailure = $this->failedAssertion()->getComparisonFailure();

        self::assertNotNull($comparisonFailure);
        self::assertSame(['title' => 'Original', 'pinned' => '1'], $comparisonFailure->getExpected());
        self::assertSame(['title' => 'Changed', 'pinned' => '1'], $comparisonFailure->getActual());
    }

    public function testFailsWhenSavingDoesNotRedirect(): void
    {
        $this->listenToUpdates(static function (AfterEntityUpdatedEvent $event): void {
            $event->setResponse(new Response('', Response::HTTP_ACCEPTED));
        });

        self::assertStringContainsString('response code "202" matches expected "302"', $this->failedAssertion()->getMessage());
    }

    private function failedAssertion(): ExpectationFailedException
    {
        try {
            $this->assertSubmittingFormWithoutChangeKeepsValues();
        } catch (ExpectationFailedException $exception) {
            return $exception;
        }

        self::fail('The assertion did not fail.');
    }

    /** @param callable(AfterEntityUpdatedEvent<Note>): void $listener */
    private function listenToUpdates(callable $listener): void
    {
        $this->getContainerService(EventDispatcherInterface::class, 'event_dispatcher')->addListener(AfterEntityUpdatedEvent::class, $listener);
    }
}
