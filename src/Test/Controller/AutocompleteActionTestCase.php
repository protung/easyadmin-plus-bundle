<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use Override;
use Psl\Json;
use Symfony\Component\HttpFoundation\JsonResponse;

use function array_merge;

/**
 * @template TController
 * @template-extends CustomActionTestCase<TController>
 */
abstract class AutocompleteActionTestCase extends CustomActionTestCase
{
    #[Override]
    final protected function actionName(): string
    {
        return 'autocomplete';
    }

    /** @return class-string<CrudControllerInterface> */
    abstract protected function autocompleteContextCrudControllerFqcn(): string;

    abstract protected function autocompleteContextPropertyName(): string;

    protected function autocompleteContextOriginatingPage(): string
    {
        return 'index';
    }

    /** @param array<array-key, mixed> $queryParameters */
    protected function assertPage(array $queryParameters = [], string $searchQuery = ''): void
    {
        $this->assertRequestGet(
            array_merge(
                [
                    'autocompleteContext' => [
                        'crudControllerFqcn' => $this->autocompleteContextCrudControllerFqcn(),
                        'propertyName' => $this->autocompleteContextPropertyName(),
                        'originatingPage' => $this->autocompleteContextOriginatingPage(),
                        'entityDisplayField' => '1',
                    ],
                    'query' => $searchQuery,
                ],
                $queryParameters,
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
