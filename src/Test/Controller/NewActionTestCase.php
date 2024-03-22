<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudController;
use Psl\Type;
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
abstract class NewActionTestCase extends AdminControllerWebTestCase
{
    protected function actionName(): string
    {
        return Action::NEW;
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertShowingEntityDefaultData(array $queryParameters = []): void
    {
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

    /** @return array<string, string> */
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
    protected function findEntity(int|string|object $id): object|null
    {
        return $this->getObjectManager()->find(
            $this->controllerUnderTest()::getEntityFqcn(),
            $id,
        );
    }

    /** @return TEntity */
    protected function getEntity(int|string|object $id): object
    {
        $entity = $this->findEntity($id);

        self::assertNotNull($entity);

        return $entity;
    }
}
