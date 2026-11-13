<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudDtoController;
use Protung\EasyAdminPlusBundle\Test\Controller\NewActionCrudDtoTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\NoteCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Dto\NoteDto;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Note;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creating a note through its DTO, with and without a listener that answers the after-persist event itself.
 *
 * @template-extends NewActionCrudDtoTestCase<Note, NoteDto, NoteCrudController>
 */
#[CoversClass(BaseCrudDtoController::class)]
final class BaseCrudDtoControllerNewTest extends NewActionCrudDtoTestCase
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

    public function testSavingRedirectsToIndexWhenNoListenerStopsTheAfterEntityPersistedEvent(): void
    {
        $this->assertSavingEntityAndRedirectingToIndexAction(['title' => 'Created']);

        $this->assertNoteWasCreated();
    }

    public function testSavingReturnsTheResponseOfTheListenerThatStoppedTheAfterEntityPersistedEvent(): void
    {
        $this->getContainerService(EventDispatcherInterface::class, 'event_dispatcher')->addListener(
            AfterEntityPersistedEvent::class,
            static function (AfterEntityPersistedEvent $event): void {
                $event->setResponse(new Response('Answered by the listener', Response::HTTP_ACCEPTED));
            },
        );

        $this->submitFormRequest(['title' => 'Created']);

        $response = $this->getClient()->getResponse();
        self::assertResponseStatusCode($response, Response::HTTP_ACCEPTED);
        self::assertSame('Answered by the listener', $response->getContent());

        $this->assertNoteWasCreated();
    }

    private function assertNoteWasCreated(): void
    {
        $this->clearObjectManager();

        $notes = $this->getEntityManager()->getRepository(Note::class)->findAll();
        self::assertCount(1, $notes);
        self::assertSame('Created', $notes[0]->title);
    }
}
