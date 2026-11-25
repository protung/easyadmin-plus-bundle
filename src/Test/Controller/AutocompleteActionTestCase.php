<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use Override;
use Psl\Json;
use Symfony\Component\HttpFoundation\JsonResponse;

use function array_merge;

/**
 * @template TEntity of object
 * @template TController of CrudControllerInterface<TEntity>
 * @template-extends CustomActionTestCase<TEntity, TController>
 */
abstract class AutocompleteActionTestCase extends CustomActionTestCase
{
    #[Override]
    final protected function actionName(): string
    {
        return 'autocomplete';
    }

    /**
     * @phpstan-return class-string<CrudControllerInterface<*>>
     */
    abstract protected function autocompleteContextCrudControllerFqcn(): string;

    abstract protected function autocompleteContextPropertyName(): string;

    protected function autocompleteContextOriginatingPage(): string
    {
        return Crud::PAGE_INDEX; // This is the page for filters and is the fallback in the entity field configurator.
    }

    /**
     * The ID of the entity on the originating page. EasyAdmin needs it since 4.29.18 and 5.10.4 to check whether the user may
     * open that page when it depends on the entity.
     */
    protected function autocompleteContextEntityId(): string|int|null
    {
        return null; // The index and new pages have no entity.
    }

    /** @param array<array-key, mixed> $urlParameters With pretty URLs, an entry named like a route placeholder fills it. */
    protected function assertPage(array $urlParameters = [], string $searchQuery = ''): void
    {
        $autocompleteContext = [
            'crudControllerFqcn' => $this->autocompleteContextCrudControllerFqcn(),
            'propertyName' => $this->autocompleteContextPropertyName(),
            'originatingPage' => $this->autocompleteContextOriginatingPage(),
            'entityDisplayField' => '1',
        ];

        $entityId = $this->autocompleteContextEntityId();
        if ($entityId !== null) {
            $autocompleteContext['entityId'] = (string) $entityId;
        }

        $this->assertRequestGet(
            array_merge(
                [
                    'autocompleteContext' => $autocompleteContext,
                    'query' => $searchQuery,
                ],
                $urlParameters,
            ),
        );

        $response = $this->getClient()->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);

        $content = $response->getContent();
        self::assertIsString($content);

        $actual = Json\decode($content);
        self::assertIsArray($actual);

        $this->assertArrayMatchesExpectedJson($actual);
    }
}
