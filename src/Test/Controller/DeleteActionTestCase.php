<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudController;
use Psl\Str;
use ReflectionProperty;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_merge;

/**
 * @template TEntity of object
 * @template TCrudController of BaseCrudController<TEntity>
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class DeleteActionTestCase extends AdminControllerWebTestCase
{
    protected static string|int $expectedEntityIdUnderTest;

    /**
     * @param array<array-key, mixed> $queryParameters
     * @param array<array-key, mixed> $redirectQueryParameters
     */
    protected function assertRemovingEntityFromIndexPageAndRedirectingToIndexAction(
        array $queryParameters = [],
        array $redirectQueryParameters = [],
    ): void {
        $indexPageQueryParameters = array_merge($queryParameters, [EA::CRUD_ACTION => Action::INDEX]);
        $crawler                  = $this->assertRequestGet($indexPageQueryParameters);

        $form                             = $this->findForm($crawler);
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();
        $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($queryParameters),
            $form->getValues(),
        );

        $redirectQueryParameters[EA::CRUD_ACTION] ??= Action::INDEX;
        $this->assertResponseIsRedirect($redirectQueryParameters);
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertDeleteEntityRespondsWithStatusCodeForbidden(array $queryParameters = []): void
    {
        $indexPageQueryParameters = array_merge($queryParameters, [EA::CRUD_ACTION => Action::INDEX]);
        $crawler                  = $this->assertRequestGet($indexPageQueryParameters);

        $form                             = $this->findForm($crawler);
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();
        $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($queryParameters),
            $form->getValues(),
        );

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_FORBIDDEN);

        $this->clearObjectManager();
    }

    private function findForm(Crawler $crawler): Form
    {
        return $crawler->filter($this->deleteFormSelector())->form();
    }

    protected function deleteFormSelector(): string
    {
        return $this->mainContentSelector() . ' form#delete-form';
    }

    protected function entityIdUnderTest(): string|int
    {
        $rp = new ReflectionProperty($this, 'expectedEntityIdUnderTest');
        if (! $rp->isInitialized()) {
            throw new LogicException(
                Str\format(
                    <<<'MSG'
                        Expected entity ID under test was not set.
                        Please set static::$expectedEntityIdUnderTest property in your test or overwrite %s method.
                    MSG,
                    __METHOD__,
                ),
            );
        }

        return static::$expectedEntityIdUnderTest;
    }

    /**
     * @return TEntity|null
     */
    protected function findEntityUnderTest(): object|null
    {
        return $this->getObjectManager()->find(
            $this->controllerUnderTest()::getEntityFqcn(),
            $this->entityIdUnderTest(),
        );
    }

    /**
     * @return TEntity
     */
    protected function getEntityUnderTest(): object
    {
        $entity = $this->findEntityUnderTest();

        self::assertNotNull($entity);

        return $entity;
    }

    protected function actionName(): string
    {
        return Action::DELETE;
    }
}
