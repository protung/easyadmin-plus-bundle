<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudDtoController;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminControllerWebTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\NoteCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\SaveForbiddenNoteCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\SwitchNoteCrudController;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Note;
use Psl\Type;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function version_compare;

/**
 * Toggling a boolean switch of the list of a DTO controller.
 *
 * @template-extends AdminControllerWebTestCase<NoteCrudController>
 */
#[CoversClass(BaseCrudDtoController::class)]
final class BaseCrudDtoControllerSwitchTest extends AdminControllerWebTestCase
{
    /** The ID of the CSRF token shared by all switches, the only one before EasyAdmin 5.10.1. */
    private const string SHARED_ID = 'ea-toggle';

    /** @var class-string<NoteCrudController> */
    private string $controllerUnderTest = SwitchNoteCrudController::class;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();
        $metadata      = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool    = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $entityManager->persist(new Note());
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
        return $this->controllerUnderTest;
    }

    #[Override]
    protected function actionName(): string
    {
        return Action::INDEX;
    }

    public function testSwitchOfTheListSavesWhenAjaxEditIsOverridden(): void
    {
        $this->toggle($this->renderedToggleUrl() . '&newValue=true');

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_OK);
        self::assertSame('1', $this->getClient()->getResponse()->getContent());
        self::assertTrue($this->isNotePinned());
    }

    public function testSwitchOfTheListSavesNothingWhenAjaxEditIsNotOverridden(): void
    {
        $this->controllerUnderTest = NoteCrudController::class;

        $this->toggle($this->renderedToggleUrl() . '&newValue=true');

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_BAD_REQUEST);
        self::assertFalse($this->isNotePinned());
    }

    public function testTokenSharedByAllSwitchesIsAcceptedAndDeprecatedWhereEasyAdminDoes(): void
    {
        if (version_compare(EasyAdminBundle::VERSION, '5.10.1', '>=')) {
            $this->expectUserDeprecationMessageMatches('/Using the "ea-toggle" CSRF token ID in boolean switches is deprecated/');
        }

        $this->toggle($this->toggleUrlWithToken(self::SHARED_ID));

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_OK);
        self::assertTrue($this->isNotePinned());
    }

    #[DataProvider('provideTokensOfOtherSwitches')]
    public function testTokenOfAnotherSwitchIsRefused(string $csrfTokenId): void
    {
        $this->toggle($this->toggleUrlWithToken($csrfTokenId));

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_UNAUTHORIZED);
        self::assertFalse($this->isNotePinned());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTokensOfOtherSwitches(): iterable
    {
        yield 'another property' => [self::SHARED_ID . '-' . SwitchNoteCrudController::class . '-title'];
        yield 'another controller' => [self::SHARED_ID . '-' . NoteCrudController::class . '-pinned'];
        yield 'not a switch' => ['something-else'];
    }

    public function testSwitchSavesNothingWhenTheUserMayNotSaveTheEditForm(): void
    {
        if (version_compare(EasyAdminBundle::VERSION, '5.10.3', '<')) {
            self::markTestSkipped('EasyAdmin only refuses switches to users who may not save the edit form since 5.10.3.');
        }

        $this->controllerUnderTest = SaveForbiddenNoteCrudController::class;

        $this->toggle($this->toggleUrlWithToken(self::SHARED_ID));

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_FORBIDDEN);
        self::assertFalse($this->isNotePinned());

        // refused before the CSRF token is checked, like EasyAdmin does.
        $this->toggle($this->toggleUrl('invalid'));

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_FORBIDDEN);
    }

    /**
     * The URL in the list, with the token EasyAdmin generated for it.
     */
    private function renderedToggleUrl(): string
    {
        return Type\non_empty_string()->coerce($this->assertRequestGet()->filter('input[data-toggle-url]')->attr('data-toggle-url'));
    }

    /**
     * A URL like the ones in the list, with a valid token for the given ID.
     */
    private function toggleUrlWithToken(string $csrfTokenId): string
    {
        $this->prepareSession($this->getClient(), ['_csrf/' . $csrfTokenId => 'valid']);

        return $this->toggleUrl('valid');
    }

    private function toggleUrl(
        #[SensitiveParameter]
        string $csrfToken,
    ): string {
        return $this->prepareAdminUrl(
            [
                EA::CRUD_ACTION => Action::EDIT,
                EA::ENTITY_ID => 1,
                'fieldName' => 'pinned',
                'csrfToken' => $csrfToken,
                'newValue' => 'true',
            ],
        );
    }

    private function toggle(string $url): void
    {
        $this->getClient()->request(Request::METHOD_PATCH, $url, server: ['HTTP_X-Requested-With' => 'XMLHttpRequest']);
    }

    private function isNotePinned(): bool
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        return Type\instance_of(Note::class)->coerce($entityManager->find(Note::class, 1))->pinned;
    }
}
