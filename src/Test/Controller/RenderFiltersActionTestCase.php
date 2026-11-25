<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use Override;
use Psl\Str;
use Psl\Type;

/**
 * The form with the filters, which the index page loads into a modal.
 *
 * @template TCrudController of CrudControllerInterface
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class RenderFiltersActionTestCase extends AdminControllerWebTestCase
{
    #[Override]
    final protected function actionName(): string
    {
        return 'renderFilters';
    }

    /**
     * @param array<array-key, mixed> $urlParameters With pretty URLs, an entry named like a route placeholder fills it.
     */
    protected function assertRenderingFilters(array $urlParameters = []): void
    {
        $this->assertRequestGet($urlParameters);

        $this->assertArrayMatchesExpectedJson($this->extractFormData());
    }

    /**
     * The values a browser submits, so without the ones that have none, like a boolean filter that is not applied.
     *
     * @return array<array-key, mixed>
     */
    protected function extractFormData(): array
    {
        $form = $this->getClient()->getCrawler()->filter(Str\format('form[name="%s"]', EA::FILTERS))->form();

        return Type\dict(Type\array_key(), Type\mixed())->coerce($form->getPhpValues()[EA::FILTERS] ?? []);
    }
}
