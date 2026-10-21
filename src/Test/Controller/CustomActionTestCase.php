<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use LogicException;
use Override;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TEntity of object
 * @template TCrudController of CrudControllerInterface<TEntity>
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class CustomActionTestCase extends AdminControllerWebTestCase
{
    protected static string|null $expectedPageTitle = null;

    protected function expectedPageTitle(): string|null
    {
        return static::$expectedPageTitle;
    }

    #[Override]
    protected function mainContentSelector(): string
    {
        return '.content-wrapper';
    }

    /**
     * Null for a custom action that acts on no single entity; its route then declares no `{entityId}`.
     */
    protected function entityIdUnderTest(): string|int|null
    {
        return null;
    }

    /**
     * @return TEntity|null
     */
    protected function findEntityUnderTest(): object|null
    {
        $entityIdUnderTest = $this->entityIdUnderTest();
        if ($entityIdUnderTest === null) {
            throw new LogicException(
                Str\format(
                    <<<'MSG'
                        Entity ID under test was not set.
                        Please overwrite %s::entityIdUnderTest() method in your test.
                    MSG,
                    self::class,
                ),
            );
        }

        return $this->getObjectManager()->find(
            $this->controllerUnderTest()::getEntityFqcn(),
            $entityIdUnderTest,
        );
    }

    /**
     * @return TEntity
     */
    protected function getEntityUnderTest(): object
    {
        $entity = $this->findEntityUnderTest();

        self::assertNotNull($entity);

        return $entity;
    }

    /**
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function prepareAdminUrlRouteParameters(): array
    {
        $routeParameters = parent::prepareAdminUrlRouteParameters();

        $entityIdUnderTest = $this->entityIdUnderTest();
        if ($entityIdUnderTest !== null) {
            $routeParameters[EA::ENTITY_ID] ??= (string) $entityIdUnderTest;
        }

        return $routeParameters;
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    #[Override]
    protected function prepareAdminUrlQueryParameters(array $queryParameters): string
    {
        if (! static::usePrettyUrls()) {
            $entityIdUnderTest = $this->entityIdUnderTest();
            if ($entityIdUnderTest !== null) {
                $queryParameters[EA::ENTITY_ID] ??= $entityIdUnderTest;
            }
        }

        return parent::prepareAdminUrlQueryParameters($queryParameters);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $urlParameters         With pretty URLs, an entry named like a route placeholder fills it.
     * @param array<array-key, mixed> $redirectUrlParameters With pretty URLs, an entry named like a route placeholder fills it.
     */
    protected function assertSubmittingFormAndRedirectingToIndexAction(
        array $data,
        array $files = [],
        array $urlParameters = [],
        array $redirectUrlParameters = [],
    ): void {
        $this->submitFormRequest($data, $files, $urlParameters);

        $redirectUrlParameters[EA::CRUD_ACTION] = Action::INDEX;

        $this->assertResponseIsRedirect($redirectUrlParameters);
    }

    /**
     * @param array<array-key, mixed> $formExpectedErrors
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $urlParameters      With pretty URLs, an entry named like a route placeholder fills it.
     */
    protected function assertSubmittingFormAndShowingValidationErrors(
        array $formExpectedErrors,
        array $data,
        array $files = [],
        array $urlParameters = [],
    ): void {
        $crawler = $this->submitFormRequest($data, $files, $urlParameters);

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_UNPROCESSABLE_ENTITY);

        $form = $this->findForm($crawler);

        $actual = $this->mapErrors($crawler, $form);

        $formExpectedErrors['_token'] = [];
        $this->assertMatchesPattern($formExpectedErrors, $actual);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $urlParameters With pretty URLs, an entry named like a route placeholder fills it.
     */
    protected function submitFormRequest(
        array $data,
        array $files = [],
        array $urlParameters = [],
    ): Crawler {
        $crawler = $this->assertRequestGet($urlParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $form     = $this->findForm($crawler);
        $formName = $form->getFormNode()->getAttribute('name');

        $data['_token'] = Type\instance_of(FormField::class)->coerce($form->get($formName . '[_token]'))->getValue();

        $values = [$formName => $data];
        $files  = [$formName => $files];

        return $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($urlParameters),
            $values,
            $files,
        );
    }

    private function findForm(Crawler $crawler): Form
    {
        return $crawler->filter($this->mainContentSelector() . ' form')->form();
    }

    /**
     * @return list<array{text: string, uri: string}>
     */
    final protected function extractLinks(Crawler $crawler): array
    {
        return Vec\map(
            $crawler->filter('a')->links(),
            /** @return array{text: string, uri: string} */
            static fn (Link $link): array => [
                'text' => Str\trim($link->getNode()->textContent),
                'uri' => $link->getUri(),
            ],
        );
    }

    protected function extractPageTitle(): string
    {
        return $this->getClient()
            ->getCrawler()
            ->filter('h1.title')
            ->text(default: '', normalizeWhitespace: true);
    }
}
