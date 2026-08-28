<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use DOMElement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use Override;
use Psl\Dict;
use Psl\Iter;
use Psl\Str;
use Psl\Type;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_merge;
use function is_array;
use function iterator_to_array;

/**
 * @template TCrudController of CrudControllerInterface
 */
abstract class AdminControllerWebTestCase extends AdminWebTestCase
{
    /**
     * @return class-string<TCrudController>
     */
    abstract protected function controllerUnderTest(): string;

    /**
     * @return non-empty-string
     */
    abstract protected function actionName(): string;

    protected static function easyAdminRoutePath(): string
    {
        return '/admin';
    }

    protected function mainContentSelector(): string
    {
        return '#main';
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     * @param positive-int            $expectedResponseStatusCode
     */
    protected function assertRequestGet(
        array $queryParameters = [],
        int $expectedResponseStatusCode = Response::HTTP_OK,
    ): Crawler {
        $crawler = $this->getClient()->request(Request::METHOD_GET, $this->prepareAdminUrl($queryParameters));

        self::assertResponseStatusCode($this->getClient()->getResponse(), $expectedResponseStatusCode);

        return $crawler;
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function prepareAdminUrl(array $queryParameters, string|null $fragment = null): string
    {
        if (! static::usePrettyUrls()) {
            return $this->prepareLegacyAdminUrl($queryParameters, $fragment);
        }

        return $this->generateAdminPrettyUrl(
            dashboardFqcn: $this->dashboardControllerFqcn(),
            crudControllerFqcn: $this->controllerUnderTest(),
            actionName: $this->actionName(),
            routeParameters: $this->prepareAdminUrlRouteParameters(),
            queryParameters: $queryParameters,
            fragment: $fragment,
        );
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function prepareLegacyAdminUrl(array $queryParameters, string|null $fragment = null): string
    {
        return static::easyAdminRoutePath() . '?' . $this->prepareAdminUrlQueryParameters($queryParameters) . ($fragment ?? '');
    }

    /**
     * @return array<array-key, mixed> $routeParameters
     */
    protected function prepareAdminUrlRouteParameters(): array
    {
        return [];
    }

    /** @return class-string<DashboardControllerInterface>|null */
    protected function dashboardControllerFqcn(): string|null
    {
        return null;
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    #[Override]
    protected function prepareAdminUrlQueryParameters(array $queryParameters): string
    {
        if (! static::usePrettyUrls()) {
            $queryParameters[EA::CRUD_CONTROLLER_FQCN] ??= $this->controllerUnderTest();
            $queryParameters[EA::CRUD_ACTION]          ??= $this->actionName();
        }

        return parent::prepareAdminUrlQueryParameters($queryParameters);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function mapErrors(Crawler $crawler, Form $form): array
    {
        $formName = $form->getFormNode()->getAttribute('name');

        $fields = $form->get($formName);

        $fieldErrors = $this->mapFieldsErrors($crawler, $fields);

        $flatFieldErrors = $this->flattenFieldErrors($fieldErrors);

        $genericErrors = $crawler->filter('.invalid-feedback')->reduce(
            static fn (Crawler $crawler): bool => ! Iter\contains($flatFieldErrors, $crawler->getNode(0)),
        );

        return array_merge(
            $genericErrors->extract(['_text']),
            $this->extractFieldErrorsTexts($fieldErrors),
        );
    }

    /**
     * @param array<DOMElement>|array<array<DOMElement>> $fieldErrors
     *
     * @return array<DOMElement>
     */
    private function flattenFieldErrors(array $fieldErrors): array
    {
        $flattenErrors = [];
        foreach ($fieldErrors as $element) {
            if (is_array($element)) {
                $flattenErrors = array_merge($flattenErrors, $this->flattenFieldErrors($element));
            } else {
                $flattenErrors[] = $element;
            }
        }

        return $flattenErrors;
    }

    /**
     * @param array<DOMElement>|array<array<DOMElement>> $fieldErrors
     *
     * @return array<array-key, mixed>
     */
    private function extractFieldErrorsTexts(array $fieldErrors): array
    {
        return Dict\map(
            $fieldErrors,
            /**
             * @param array<DOMElement>|DOMElement $element
             */
            function (DOMElement|array $element) {
                if (is_array($element)) {
                    return $this->extractFieldErrorsTexts($element);
                }

                return $element->textContent;
            },
        );
    }

    /**
     * @param FormField|array<array-key, FormField>|array<array-key, array<array-key, FormField>> $fields
     *
     * @return array<array-key, mixed>
     */
    protected function mapFieldsErrors(Crawler $crawler, FormField|array $fields): array
    {
        if ($fields instanceof FormField) {
            $currentFormWidget = $crawler
                ->filter(Str\format('input[name="%1$s"],select[name="%1$s"],select[name="%1$s[]"],textarea[name="%1$s"]', $fields->getName()))
                ->closest('.form-widget');

            if ($currentFormWidget === null) {
                return [];
            }

            return iterator_to_array($currentFormWidget->filter('.invalid-feedback'));
        }

        return Dict\map(
            $fields,
            /**
             * @param FormField|array<array-key, FormField> $fields
             */
            fn (FormField|array $fields): array => $this->mapFieldsErrors($crawler, $fields),
        );
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function mapActions(Crawler $actionsCrawler): array
    {
        return Dict\from_entries(
            Type\vec(Type\shape([0 => Type\non_empty_string(), 1 => Type\mixed_dict()]))->coerce(
                $actionsCrawler->each(
                    static fn (Crawler $crawler): array => [
                        Type\non_empty_string()->assert($crawler->attr('data-action-name')),
                        [
                            'title' => $crawler->text(normalizeWhitespace: true) !== '' ? $crawler->text(normalizeWhitespace: true) : $crawler->attr('title') ?? '',
                            'url' => $crawler->attr('href'),
                        ],
                    ],
                ),
            ),
        );
    }

    protected function assertPageTitle(string $expectedPageTitle): void
    {
        $title = $this->getClient()->getCrawler()->filter('h1.title');

        self::assertCount(1, $title);
        self::assertSame($expectedPageTitle, $title->text(normalizeWhitespace: true));
    }

    /**
     * @param class-string<CrudControllerInterface> $crudControllerFqcn
     * @param non-empty-string                      $actionName
     * @param array<array-key, mixed>               $redirectQueryParameters
     */
    protected function assertResponseRedirectsToCrudController(
        string $crudControllerFqcn,
        string $actionName,
        string|int|null $entityId = null,
        array $redirectQueryParameters = [],
        string|null $fragment = null,
    ): void {
        if (static::usePrettyUrls()) {
            $redirectRouteParameters = [];
            if ($entityId !== null) {
                $redirectRouteParameters[EA::ENTITY_ID] = $entityId;
            }

            $expectedRedirectUrl = 'http://' . static::serverHost() . $this->generateAdminPrettyUrl(
                $this->dashboardControllerFqcn(),
                $crudControllerFqcn,
                $actionName,
                $redirectRouteParameters,
                $redirectQueryParameters,
                $fragment,
            );

            self::assertResponseRedirectsToUrl($this->getClient()->getResponse(), $expectedRedirectUrl);
        } else {
            $redirectQueryParameters[EA::CRUD_CONTROLLER_FQCN] ??= $crudControllerFqcn;
            $redirectQueryParameters[EA::CRUD_ACTION]          ??= $actionName;

            if ($entityId !== null) {
                $redirectQueryParameters[EA::ENTITY_ID] = $entityId;
            }

            $this->assertResponseIsRedirect($redirectQueryParameters, $fragment);
        }
    }

    /**
     * @param array<array-key, mixed> $redirectQueryParameters
     */
    protected function assertResponseIsRedirect(array $redirectQueryParameters, string|null $fragment = null): void
    {
        $expectedRedirectUrl = 'http://' . static::serverHost() . $this->prepareAdminUrl($redirectQueryParameters, $fragment);

        self::assertResponseRedirectsToUrl($this->getClient()->getResponse(), $expectedRedirectUrl);
    }

    protected function getAdminContextFromLastRequest(): AdminContext
    {
        $context = $this->getClient()->getRequest()->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);
        if (! $context instanceof AdminContext) {
            throw new RuntimeException('Admin context was not stored in the request.');
        }

        return $context;
    }
}
