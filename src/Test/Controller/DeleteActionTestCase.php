<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Override;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudController;
use Psl\Str;
use ReflectionProperty;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_key_exists;
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
     * @param array<array-key, mixed> $urlParameters         With pretty URLs, an entry named like a route placeholder fills it.
     * @param array<array-key, mixed> $redirectUrlParameters With pretty URLs, an entry named like a route placeholder fills it.
     */
    protected function assertRemovingEntityFromIndexPageAndRedirectingToIndexAction(
        array $urlParameters = [],
        array $redirectUrlParameters = [],
    ): void {
        $indexPageUrlParameters = array_merge($urlParameters, [EA::CRUD_ACTION => Action::INDEX]);
        $crawler                = $this->assertRequestGet($indexPageUrlParameters);

        $form = $this->findForm($crawler);
        if (! static::usePrettyUrls() && ! array_key_exists(EA::ENTITY_ID, $urlParameters)) {
            $urlParameters[EA::ENTITY_ID] = $this->entityIdUnderTest();
        }

        $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($urlParameters),
            $form->getValues(),
        );

        $redirectUrlParameters[EA::CRUD_ACTION] ??= Action::INDEX;
        $this->assertResponseIsRedirect($redirectUrlParameters);
    }

    /**
     * @param array<array-key, mixed> $urlParameters         With pretty URLs, an entry named like a route placeholder fills it.
     * @param array<array-key, mixed> $redirectUrlParameters With pretty URLs, an entry named like a route placeholder fills it.
     */
    protected function assertRemovingEntityFromDetailPageAndRedirectingToIndexAction(
        array $urlParameters = [],
        array $redirectUrlParameters = [],
    ): void {
        $detailPageUrlParameters = array_merge($urlParameters, [EA::CRUD_ACTION => Action::DETAIL]);
        if (! static::usePrettyUrls()) {
            // when using pretty URLs the entity ID is part of the route path, not a query parameter.
            $detailPageUrlParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();
        }

        $crawler = $this->assertRequestGet($detailPageUrlParameters);

        $form = $this->findForm($crawler);
        if (! static::usePrettyUrls() && ! array_key_exists(EA::ENTITY_ID, $urlParameters)) {
            $urlParameters[EA::ENTITY_ID] = $this->entityIdUnderTest();
        }

        $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($urlParameters),
            $form->getValues(),
        );

        $redirectUrlParameters[EA::CRUD_ACTION] ??= Action::INDEX;
        $this->assertResponseIsRedirect($redirectUrlParameters);
    }

    /**
     * @param array<array-key, mixed> $urlParameters With pretty URLs, an entry named like a route placeholder fills it.
     */
    protected function assertDeleteEntityRespondsWithStatusCodeForbidden(array $urlParameters = []): void
    {
        if (! static::usePrettyUrls() && ! array_key_exists(EA::ENTITY_ID, $urlParameters)) {
            $urlParameters[EA::ENTITY_ID] = $this->entityIdUnderTest();
        }

        $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($urlParameters),
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
        return $this->mainContentSelector() . ' form#action-confirmation-form';
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
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function prepareAdminUrlRouteParameters(): array
    {
        $routeParameters = parent::prepareAdminUrlRouteParameters();

        $routeParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        return $routeParameters;
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

    #[Override]
    protected function actionName(): string
    {
        return Action::DELETE;
    }
}
