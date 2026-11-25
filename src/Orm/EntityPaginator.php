<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Orm;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityPaginatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\PaginatorDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FieldFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Override;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Psl\Iter;
use Psl\Type;
use Psl\Type\Exception\CoercionException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function method_exists;

final readonly class EntityPaginator implements EntityPaginatorInterface
{
    public function __construct(
        private EntityPaginatorInterface $decoratedPaginator,
        private AdminContextProviderInterface $adminContextProvider,
        private ControllerFactory $controllerFactory,
        private EntityFactory $entityFactory,
        private AdminUrlGeneratorInterface $adminUrlGenerator,
        private PropertyAccessorInterface $propertyAccessor,
        private TranslatorInterface $translator,
        private Environment $twig,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Override]
    public function paginate(PaginatorDto $paginatorDto, QueryBuilder $queryBuilder): EntityPaginatorInterface
    {
        return new self(
            $this->decoratedPaginator->paginate($paginatorDto, $queryBuilder),
            $this->adminContextProvider,
            $this->controllerFactory,
            $this->entityFactory,
            $this->adminUrlGenerator,
            $this->propertyAccessor,
            $this->translator,
            $this->twig,
            $this->authorizationChecker,
        );
    }

    #[Override]
    public function generateUrlForPage(int $page): string
    {
        return $this->decoratedPaginator->generateUrlForPage($page);
    }

    #[Override]
    public function getCurrentPage(): int
    {
        return $this->decoratedPaginator->getCurrentPage();
    }

    #[Override]
    public function getLastPage(): int
    {
        return $this->decoratedPaginator->getLastPage();
    }

    /**
     * @return iterable<int|null>
     */
    #[Override]
    public function getPageRange(int|null $pagesOnEachSide = null, int|null $pagesOnEdges = null): iterable
    {
        return $this->decoratedPaginator->getPageRange($pagesOnEachSide, $pagesOnEdges);
    }

    #[Override]
    public function getPageSize(): int
    {
        return $this->decoratedPaginator->getPageSize();
    }

    #[Override]
    public function hasPreviousPage(): bool
    {
        return $this->decoratedPaginator->hasPreviousPage();
    }

    #[Override]
    public function getPreviousPage(): int
    {
        return $this->decoratedPaginator->getPreviousPage();
    }

    #[Override]
    public function hasNextPage(): bool
    {
        return $this->decoratedPaginator->hasNextPage();
    }

    #[Override]
    public function getNextPage(): int
    {
        return $this->decoratedPaginator->getNextPage();
    }

    #[Override]
    public function hasToPaginate(): bool
    {
        return $this->decoratedPaginator->hasToPaginate();
    }

    #[Override]
    public function isOutOfRange(): bool
    {
        return $this->decoratedPaginator->isOutOfRange();
    }

    #[Override]
    public function getNumResults(): int
    {
        return $this->decoratedPaginator->getNumResults();
    }

    #[Override]
    public function getResults(): iterable|null
    {
        return $this->decoratedPaginator->getResults();
    }

    #[Override]
    public function getResultsAsJson(callable|null $callback = null, string|null $twigTemplate = null, bool $renderAsHtml = false): string
    {
        if ($callback !== null) {
            return $this->decoratedPaginator->getResultsAsJson($callback, $twigTemplate, $renderAsHtml);
        }

        $context = $this->adminContextProvider->getContext();

        if ($context === null) {
            return $this->decoratedPaginator->getResultsAsJson($callback, $twigTemplate, $renderAsHtml);
        }

        try {
            $autocompleteContext = Type\shape(
                [
                    EA::CRUD_CONTROLLER_FQCN => Type\string(),
                    'propertyName' => Type\string(),
                    'originatingPage' => Type\string(),
                    EntityField::OPTION_ENTITY_DISPLAY_FIELD => Type\bool(),
                ],
            )->coerce($context->getRequest()->query->all(AssociationField::PARAM_AUTOCOMPLETE_CONTEXT));
        } catch (CoercionException) {
            return $this->decoratedPaginator->getResultsAsJson($callback, $twigTemplate, $renderAsHtml);
        }

        $controller = $this->controllerFactory->getCrudControllerInstance(
            $autocompleteContext[EA::CRUD_CONTROLLER_FQCN],
            Action::INDEX,
            $context->getRequest(),
        );

        $field = null;
        if ($controller !== null) {
            $fields = Type\vec(Type\instance_of(FieldInterface::class))->coerce($controller->configureFields($autocompleteContext['originatingPage']));
            $field  = $this->findVisibleField(new FieldCollection($fields), $autocompleteContext['propertyName']);
        }

        if ($field === null) {
            return $this->decoratedPaginator->getResultsAsJson($callback, $twigTemplate, $renderAsHtml);
        }

        return $this->decoratedPaginator->getResultsAsJson(
            fn (object $entityInstance): string|null => EntityField::formatAsString(
                $entityInstance,
                $field,
                $this->translator,
                $this->twig,
                $context,
            ),
            $twigTemplate,
            $renderAsHtml,
        );
    }

    /**
     * The autocomplete context comes from the query string, so only a field that the user may see is used, like in
     * EasyAdmin's autocomplete action.
     */
    private function findVisibleField(FieldCollection $fields, string $propertyName): FieldDto|null
    {
        // since EasyAdmin 4.29.18 and 5.10.4, before them the permission of a tab, column or fieldset doesn't apply to its fields
        $fieldsOfDeniedLayoutContainers = method_exists(FieldFactory::class, 'getFieldsOfDeniedLayoutContainers')
            ? Type\dict(Type\string(), Type\instance_of(FieldDto::class))->coerce(FieldFactory::getFieldsOfDeniedLayoutContainers($fields, $this->authorizationChecker))
            : [];

        foreach ($fields as $field) {
            if ($field->getProperty() !== $propertyName || Iter\contains_key($fieldsOfDeniedLayoutContainers, $field->getUniqueId())) {
                continue;
            }

            if ($this->authorizationChecker->isGranted(Permission::EA_VIEW_FIELD, $field)) {
                return $field;
            }
        }

        return null;
    }
}
