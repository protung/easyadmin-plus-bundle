<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Override;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TEntity of object
 * @template TCrudController of BaseCrudController<TEntity>
 * @template-extends BatchActionTestCase<TEntity, TCrudController>
 */
abstract class BatchDeleteActionTestCase extends BatchActionTestCase
{
    /**
     * @param array<string|int>       $entityIds
     * @param array<array-key, mixed> $queryParameters
     */
    public function assertBatchDeleteRespondsWithStatusCodeForbidden(array $entityIds, array $queryParameters = []): void
    {
        if (static::usePrettyUrls()) {
            // when using pretty URLs the batch action has its own route, while in legacy mode
            // it is submitted to the index URL and dispatched from the request payload.
            $queryParameters[EA::CRUD_ACTION] ??= Action::BATCH_DELETE;
        }

        $this->getClient()
            ->request(
                Request::METHOD_POST,
                $this->prepareAdminUrl($queryParameters),
                [
                    EA::BATCH_ACTION_NAME => $this->getBatchActionName(),
                    EA::ENTITY_FQCN => $this->controllerUnderTest()::getEntityFqcn(),
                    EA::BATCH_ACTION_ENTITY_IDS => $entityIds,
                ],
            );

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_FORBIDDEN);

        $this->clearObjectManager();
    }

    #[Override]
    protected function getBatchActionName(): string
    {
        return Action::BATCH_DELETE;
    }
}
