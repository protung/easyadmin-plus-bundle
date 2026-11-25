<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Router;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Psl\Iter;
use Psl\Type;
use Psl\Type\Exception\CoercionException;
use Symfony\Component\HttpFoundation\Request;

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
        AdminContextInterface $context,
        string $targetCrudControllerFqcn,
        string $propertyName,
        string $originatingPage,
        bool $hasOptionEntityFieldDisplayField,
    ): string {
        $autocompleteContext = [
            EA::CRUD_CONTROLLER_FQCN => Type\nonnull()->coerce($context->getCrud()?->getControllerFqcn()),
            'propertyName' => $propertyName,
            'originatingPage' => $originatingPage,
            EntityField::OPTION_ENTITY_DISPLAY_FIELD => $hasOptionEntityFieldDisplayField,
        ];

        $entityId = $this->originatingEntityId($context->getRequest(), $context->getEntity(), $originatingPage);
        if ($entityId !== null) {
            $autocompleteContext[EA::ENTITY_ID] = $entityId;
        }

        return $this->adminUrlGenerator
            ->set('page', 1) // The autocomplete should always start on the first page
            ->setController($targetCrudControllerFqcn)
            ->setAction('autocomplete')
            ->setEntityId(null)
            ->unset(EA::SORT) // Avoid passing the 'sort' param from the current entity to the autocompleted one
            ->set(EntityField::PARAM_AUTOCOMPLETE_CONTEXT, $autocompleteContext)
            ->generateUrl();
    }

    /**
     * Since EasyAdmin 4.29.18 and 5.10.4 the autocomplete action checks that the user may open the originating page, which
     * may depend on the entity of that page. The index and new pages have no entity, even if the query string has an ID.
     *
     * @param EntityDto<object> $entityDto
     */
    private function originatingEntityId(Request $request, EntityDto $entityDto, string $originatingPage): string|null
    {
        if (Iter\contains([Crud::PAGE_INDEX, Crud::PAGE_NEW], $originatingPage)) {
            return null;
        }

        // like EasyAdmin 5's EntityIdReader, which EasyAdmin 4 doesn't have
        $routeParameters = Type\dict(Type\array_key(), Type\mixed())->coerce($request->attributes->get('_route_params', []));

        try {
            return Type\nullable(Type\string())->coerce(
                $request->attributes->get(EA::ENTITY_ID)
                    ?? $request->attributes->get('id')
                    ?? $routeParameters[EA::ENTITY_ID]
                    ?? $routeParameters['id']
                    ?? $request->query->get(EA::ENTITY_ID)
                    ?? $entityDto->getPrimaryKeyValue(), // the request of a context that wasn't created for the current request has no ID
            );
        } catch (CoercionException) {
            return null;
        }
    }
}
