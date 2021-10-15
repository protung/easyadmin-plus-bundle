<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Psl\Str;
use Psl\Type;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class NewActionTestCase extends AdminControllerWebTestCase
{
    protected static ?string $expectedPageTitle = null;

    protected function actionName(): string
    {
        return Action::NEW;
    }

    protected function expectedPageTitle(): ?string
    {
        if (static::$expectedPageTitle === null) {
            throw new LogicException(
                Str\format(
                    <<<'MSG'
                        Expected page title was not set.
                        Please set static::$expectedPageTitle property in your test or overwrite %1$s method.
                        If your index page does not have a title you need to overwrite %1$s method and return NULL.
                    MSG,
                    __METHOD__
                )
            );
        }

        return static::$expectedPageTitle;
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    public function testPageLoads(array $queryParameters = []): void
    {
        $queryParameters[EA::CRUD_ACTION] ??= $this->actionName();

        $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }
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
        array $redirectQueryParameters = []
    ): void {
        $this->submitFormRequest($data, $files, $queryParameters);

        $redirectQueryParameters[EA::CRUD_ACTION] = Action::INDEX;

        $this->assertResponseIsRedirect($redirectQueryParameters);
    }

    /**
     * @param array<array-key, mixed> $formExpectedErrors
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertSubmittingFormAndShowingValidationErrors(
        array $formExpectedErrors,
        array $data,
        array $files = [],
        array $queryParameters = []
    ): void {
        $crawler = $this->submitFormRequest($data, $files, $queryParameters);

        self::assertResponseStatusCode($this->getClient()->getResponse(), Response::HTTP_OK);

        $form = $this->findForm($crawler);

        $actual = $this->mapErrors($crawler, $form);

        $formExpectedErrors['_token'] = [];
        $this->assertMatchesPattern($formExpectedErrors, $actual);
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
        string $submitButton = Action::SAVE_AND_RETURN
    ): Crawler {
        $crawler = $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $form     = $this->findForm($crawler);
        $formName = $form->getFormNode()->getAttribute('name');

        $data['_token'] = Type\object(FormField::class)->coerce($form->get($formName . '[_token]'))->getValue();
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
            $files
        );
    }

    /**
     * @param array<string, mixed>    $formExpectedFields
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertShowingEntityDefaultData(array $formExpectedFields, array $queryParameters = []): void
    {
        $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $form     = $this->findForm($this->getClient()->getCrawler());
        $formName = $form->getFormNode()->getAttribute('name');

        $formExpectedFields['_token'] = '@string@';

        $this->assertMatchesPattern(
            $formExpectedFields,
            $form->getPhpValues()[$formName]
        );
    }

    private function findForm(Crawler $crawler): Form
    {
        return $crawler->filter('#main form')->form();
    }
}
