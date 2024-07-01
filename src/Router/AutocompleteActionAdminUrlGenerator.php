<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Router;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Protung\EasyAdminPlusBundle\Field\EntityField;

final readonly class AutocompleteActionAdminUrlGenerator
{
    public function __construct(
        private AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    /**
     * @param class-string<CrudControllerInterface> $targetCrudControllerFqcn
     */
    public function generate(
        AdminContext $context,
        string $targetCrudControllerFqcn,
        string $propertyName,
        string $originatingPage,
        bool $hasOptionEntityFieldDisplayField,
    ): string {
        return $this->adminUrlGenerator
            ->set('page', 1) // The autocomplete should always start on the first page
            ->setController($targetCrudControllerFqcn)
            ->setAction('autocomplete')
            ->setEntityId(null)
            ->unset(EA::SORT) // Avoid passing the 'sort' param from the current entity to the autocompleted one
            ->set(
                EntityField::PARAM_AUTOCOMPLETE_CONTEXT,
                [
                    EA::CRUD_CONTROLLER_FQCN => $context->getRequest()->query->get(EA::CRUD_CONTROLLER_FQCN),
                    'propertyName' => $propertyName,
                    'originatingPage' => $originatingPage,
                    EntityField::OPTION_ENTITY_DISPLAY_FIELD => $hasOptionEntityFieldDisplayField,
                ],
            )
            ->generateUrl();
    }
}
