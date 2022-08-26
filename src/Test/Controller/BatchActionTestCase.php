<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Psl\Type;
use Symfony\Component\HttpFoundation\Request;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class BatchActionTestCase extends AdminControllerWebTestCase
{
    abstract protected function getBatchActionName(): string;

    /**
     * @param array<string>        $entityIds
     * @param array<string, mixed> $indexPageQueryParameters
     * @param array<string, mixed> $expectedRedirectUrlParameters
     */
    public function assertBatchActionForEntityIds(
        array $entityIds,
        array $indexPageQueryParameters = [],
        array $expectedRedirectUrlParameters = [],
    ): void {
        $listingPageCrawler = $this->getClient()->request(
            Request::METHOD_GET,
            $this->prepareAdminUrl($indexPageQueryParameters),
        );

        $actionAnchorElement = $listingPageCrawler
            ->filter('[data-action-name="' . $this->getBatchActionName() . '"]')
            ->first();

        $actionRequestUrl = $actionAnchorElement->attr('data-action-url');

        $this->getClient()
            ->request(
                Request::METHOD_POST,
                Type\string()->coerce($actionRequestUrl),
                [
                    EA::BATCH_ACTION_NAME => $this->getBatchActionName(),
                    EA::ENTITY_FQCN => $actionAnchorElement->attr('data-entity-fqcn'),
                    EA::BATCH_ACTION_ENTITY_IDS => $entityIds,
                    EA::BATCH_ACTION_CSRF_TOKEN => $actionAnchorElement->attr('data-action-csrf-token'),
                ],
            );

        $this->assertResponseIsRedirect($expectedRedirectUrlParameters);
    }

    protected function actionName(): string
    {
        return Action::INDEX;
    }
}
