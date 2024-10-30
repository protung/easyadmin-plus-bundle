<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudController;
use Psl\Str;
use Psl\Type;
use ReflectionProperty;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TEntity of object
 * @template TCrudController of BaseCrudController<TEntity>
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class EditActionTestCase extends AdminControllerWebTestCase
{
    protected static string|int $expectedEntityIdUnderTest;

    protected function actionName(): string
    {
        return Action::EDIT;
    }

    protected function entityIdUnderTest(): string|int
    {
        $rp = new ReflectionProperty($this, 'expectedEntityIdUnderTest');
        if (! $rp->isInitialized()) {
            throw new LogicException(
                Str\format(
                    <<<'MSG'
                        Expected entity ID under test was not set.
                        Please set static::$expectedEntityIdUnderTest property in your test or overwrite %s method.
                    MSG,
                    __METHOD__,
                ),
            );
        }

        return static::$expectedEntityIdUnderTest;
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    public function assertShowingEntityToEditRespondsWithStatusCodeForbidden(array $queryParameters = []): void
    {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        $this->assertRequestGet($queryParameters, Response::HTTP_FORBIDDEN);
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertShowingEntityToEdit(array $queryParameters = []): void
    {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        $this->assertRequestGet($queryParameters);

        $actual = [
            'page_title' => $this->extractPageTitle(),
            'form_data' => $this->extractFormData(),
            'actions' => $this->extractActions(),
        ];

        $this->assertArrayMatchesExpectedJson($actual);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $queryParameters
     * @param array<array-key, mixed> $redirectQueryParameters
     */
    protected function assertSavingEntityAndRedirectingToIndexAction(
        array $data,
        array $files = [],
        array $queryParameters = [],
        array $redirectQueryParameters = [],
    ): void {
        $this->submitFormRequest($data, $files, $queryParameters);

        $redirectQueryParameters[EA::CRUD_ACTION] = Action::INDEX;

        $this->assertResponseIsRedirect($redirectQueryParameters);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $queryParameters
     * @param array<array-key, mixed> $redirectQueryParameters
     */
    protected function assertSavingEntityAndRedirectingToDetailAction(
        array $data,
        array $files = [],
        array $queryParameters = [],
        array $redirectQueryParameters = [],
    ): void {
        $this->submitFormRequest($data, $files, $queryParameters);

        $redirectQueryParameters[EA::CRUD_ACTION] = Action::DETAIL;
        $redirectQueryParameters[EA::ENTITY_ID]   = $this->entityIdUnderTest();

        $this->assertResponseIsRedirect($redirectQueryParameters);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertSubmittingFormAndShowingValidationErrors(
        array $data,
        array $files = [],
        array $queryParameters = [],
    ): void {
        $crawler = $this->submitFormRequest($data, $files, $queryParameters);

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_UNPROCESSABLE_ENTITY);

        $form = $this->findForm($crawler);

        $actual = $this->mapErrors($crawler, $form);

        $csrfTokenKey = '_token';
        self::assertArrayHasKey($csrfTokenKey, $actual);
        self::assertSame([], $actual[$csrfTokenKey]);

        unset($actual[$csrfTokenKey]);

        $this->assertArrayMatchesExpectedJson($actual);
    }

    protected function extractPageTitle(): string
    {
        $title = $this->getClient()->getCrawler()->filter('h1.title');

        self::assertCount(1, $title);

        return $title->text(normalizeWhitespace: true);
    }

    /** @return array<string, array<mixed>> */
    protected function extractActions(): array
    {
        $actionsCrawler = $this->getClient()->getCrawler()->filter('.page-actions')->children();

        return $this->mapActions($actionsCrawler);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $queryParameters
     * @param Action::SAVE_*          $submitButton
     */
    protected function submitFormRequest(
        array $data,
        array $files = [],
        array $queryParameters = [],
        string $submitButton = Action::SAVE_AND_RETURN,
    ): Crawler {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        $crawler = $this->assertRequestGet($queryParameters);

        $form     = $this->findForm($crawler);
        $formName = $form->getFormNode()->getAttribute('name');

        $data['_token'] = Type\instance_of(FormField::class)->coerce($form->get($formName . '[_token]'))->getValue();
        $data['btn']    = $submitButton;
        $values         = [
            $formName => $data,
            'ea' => [
                'newForm' => ['btn' => $submitButton],
            ],
        ];
        $files          = [$formName => $files];

        return $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($queryParameters),
            $values,
            $files,
        );
    }

    /** @return array<mixed> */
    protected function extractFormData(): array
    {
        $form     = $this->findForm($this->getClient()->getCrawler());
        $formName = $form->getFormNode()->getAttribute('name');

        $formData = $form->getPhpValues()[$formName];

        $csrfTokenKey = '_token';
        self::assertIsArray($formData);
        self::assertArrayHasKey($csrfTokenKey, $formData);
        self::assertIsString($formData[$csrfTokenKey]);

        unset($formData[$csrfTokenKey]);

        return $formData;
    }

    private function findForm(Crawler $crawler): Form
    {
        return $crawler->filter($this->mainContentSelector() . ' form')->form();
    }

    /** @return TEntity|null */
    protected function findEntityUnderTest(): object|null
    {
        return $this->getObjectManager()->find(
            $this->controllerUnderTest()::getEntityFqcn(),
            $this->entityIdUnderTest(),
        );
    }

    /** @return TEntity */
    protected function getEntityUnderTest(): object
    {
        $entity = $this->findEntityUnderTest();

        self::assertNotNull($entity);

        return $entity;
    }
}
