<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Override;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudController;
use Psl\Type;
use SensitiveParameter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

use function version_compare;

/**
 * @template TEntity of object
 * @template TCrudController of BaseCrudController<TEntity>
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class BatchActionTestCase extends AdminControllerWebTestCase
{
    abstract protected function getBatchActionName(): string;

    /**
     * @param array<string>        $entityIds
     * @param array<string, mixed> $indexPageQueryParameters
     * @param string|null          $csrfToken                The CSRF token to submit instead of the one from the index page.
     * @param array<string, mixed> $server                   Server parameters for the batch action request (e.g. HTTP_REFERER).
     */
    public function submitFormRequest(
        array $entityIds,
        array $indexPageQueryParameters = [],
        #[SensitiveParameter]
        string|null $csrfToken = null,
        array $server = [],
    ): Crawler {
        $listingPageCrawler = $this->getClient()->request(
            Request::METHOD_GET,
            $this->prepareAdminUrl($indexPageQueryParameters),
        );

        $actionAnchorElement = $listingPageCrawler
            ->filter('[data-action-name="' . $this->getBatchActionName() . '"]')
            ->first();

        $actionRequestUrl = $actionAnchorElement->attr('data-action-url');

        return $this->getClient()
            ->request(
                Request::METHOD_POST,
                Type\string()->coerce($actionRequestUrl),
                [
                    EA::BATCH_ACTION_NAME => $this->getBatchActionName(),
                    EA::ENTITY_FQCN => $actionAnchorElement->attr('data-entity-fqcn'),
                    EA::BATCH_ACTION_ENTITY_IDS => $entityIds,
                    EA::BATCH_ACTION_CSRF_TOKEN => $csrfToken ?? $actionAnchorElement->attr('data-action-csrf-token'),
                ],
                server: $server,
            );
    }

    /**
     * @param array<string>        $entityIds
     * @param array<string, mixed> $indexPageQueryParameters
     * @param array<string, mixed> $expectedRedirectUrlParameters
     * @param string|null          $csrfToken                     The CSRF token to submit instead of the one from the index page.
     * @param array<string, mixed> $server                        Server parameters for the batch action request (e.g. HTTP_REFERER).
     */
    public function assertBatchActionForEntityIds(
        array $entityIds,
        array $indexPageQueryParameters = [],
        array $expectedRedirectUrlParameters = [],
        #[SensitiveParameter]
        string|null $csrfToken = null,
        array $server = [],
    ): void {
        $this->submitFormRequest($entityIds, $indexPageQueryParameters, $csrfToken, $server);

        // EasyAdmin 4 redirects to the first page after a batch action, EasyAdmin 5 leaves the page out of the URL.
        if (version_compare(EasyAdminBundle::VERSION, '5.0.0', '<')) {
            $expectedRedirectUrlParameters[EA::PAGE] ??= '1';
        }

        $this->assertResponseIsRedirect($expectedRedirectUrlParameters);
    }

    /**
     * @return TEntity|null
     */
    protected function findEntityUnderTest(string|int $id): object|null
    {
        return $this->findEntity($this->controllerUnderTest()::getEntityFqcn(), $id);
    }

    #[Override]
    protected function actionName(): string
    {
        return Action::INDEX;
    }
}
