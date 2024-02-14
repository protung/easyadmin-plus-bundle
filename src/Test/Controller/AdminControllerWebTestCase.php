<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use DOMElement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Psl\Dict;
use Psl\Iter;
use Psl\Str;
use Psl\Type;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_merge;
use function http_build_query;
use function is_array;
use function iterator_to_array;

/**
 * @template TCrudController
 */
abstract class AdminControllerWebTestCase extends AdminWebTestCase
{
    /**
     * @return class-string<TCrudController>
     */
    abstract protected function controllerUnderTest(): string;

    abstract protected function actionName(): string;

    protected static function easyAdminRoutePath(): string
    {
        return '/admin';
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
    protected function prepareAdminUrl(array $queryParameters): string
    {
        $queryParameters[EA::CRUD_CONTROLLER_FQCN] ??= $this->controllerUnderTest();
        $queryParameters[EA::CRUD_ACTION]          ??= $this->actionName();

        // we need to prepare the URL having some query parameters in a specific order
        $queryParameters = Dict\sort_by_key($queryParameters);

        return static::easyAdminRoutePath() . '?' . http_build_query($queryParameters);
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
            static fn (Crawler $crawler): bool => ! Iter\contains($flatFieldErrors, $crawler->getNode(0))
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
                ->filter(Str\format('input[name="%1$s"],select[name="%1$s"],textarea[name="%1$s"]', $fields->getName()))
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
            fn (FormField|array $fields): array => $this->mapFieldsErrors($crawler, $fields)
        );
    }

    /**
     * @return array<string, string>
     */
    protected function mapActions(Crawler $actionsCrawler): array
    {
        return Dict\from_entries(
            Type\vec(Type\shape([0 => Type\non_empty_string(), 1 => Type\string()]))->coerce(
                $actionsCrawler->each(
                    static fn (Crawler $crawler): array => [
                        Type\non_empty_string()->assert($crawler->attr('data-action-name')),
                        $crawler->text(normalizeWhitespace: true) !== '' ?: $crawler->attr('title') ?? '',
                    ]
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
     * @param array<array-key, mixed> $redirectQueryParameters
     */
    protected function assertResponseIsRedirect(array $redirectQueryParameters): void
    {
        $expectedRedirectUrl = 'http://localhost' . $this->prepareAdminUrl($redirectQueryParameters);

        $this->assertResponseRedirectsToUrl($expectedRedirectUrl);
    }
}
