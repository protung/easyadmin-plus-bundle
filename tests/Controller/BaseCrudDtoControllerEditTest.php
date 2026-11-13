<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudDtoController;
use Protung\EasyAdminPlusBundle\Test\Controller\EditActionCrudDtoTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\NoteCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Dto\NoteDto;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Note;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saving a note through its DTO, with and without a listener that answers the after-update event itself.
 *
 * @template-extends EditActionCrudDtoTestCase<Note, NoteDto, NoteCrudController>
 */
#[CoversClass(BaseCrudDtoController::class)]
final class BaseCrudDtoControllerEditTest extends EditActionCrudDtoTestCase
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

        $note        = new Note();
        $note->title = 'Original';
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

    public function testSavingRedirectsToIndexWhenNoListenerStopsTheAfterEntityUpdatedEvent(): void
    {
        $this->assertSavingEntityAndRedirectingToIndexAction(['title' => 'Updated']);

        $this->clearObjectManager();
        self::assertSame('Updated', $this->getEntityUnderTest()->title);
    }

    public function testSavingReturnsTheResponseOfTheListenerThatStoppedTheAfterEntityUpdatedEvent(): void
    {
        $this->getContainerService(EventDispatcherInterface::class, 'event_dispatcher')->addListener(
            AfterEntityUpdatedEvent::class,
            static function (AfterEntityUpdatedEvent $event): void {
                $event->setResponse(new Response('Answered by the listener', Response::HTTP_ACCEPTED));
            },
        );

        $this->submitFormRequest(['title' => 'Updated']);

        $response = $this->getClient()->getResponse();
        self::assertResponseStatusCode($response, Response::HTTP_ACCEPTED);
        self::assertSame('Answered by the listener', $response->getContent());

        $this->clearObjectManager();
        self::assertSame('Updated', $this->getEntityUnderTest()->title);
    }
}
