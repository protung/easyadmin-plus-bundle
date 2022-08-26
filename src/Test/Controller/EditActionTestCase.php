<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Psl\Str;
use Psl\Type;
use ReflectionProperty;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class EditActionTestCase extends AdminControllerWebTestCase
{
    protected static string $expectedEntityIdUnderTest;

    protected static string|null $expectedPageTitle = null;

    protected function actionName(): string
    {
        return Action::EDIT;
    }

    protected function expectedPageTitle(): string|null
    {
        if (static::$expectedPageTitle === null) {
            throw new LogicException(
                Str\format(
                    <<<'MSG'
                        Expected page title was not set.
                        Please set static::$expectedPageTitle property in your test or overwrite %1$s method.
                        If your index page does not have a title you need to overwrite %1$s method and return NULL.
                    MSG,
                    __METHOD__,
                ),
            );
        }

        return static::$expectedPageTitle;
    }

    protected function entityIdUnderTest(): string
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
     * @param array<string, mixed>    $formExpectedFields
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertShowingEntityToEdit(array $formExpectedFields, array $queryParameters = []): void
    {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

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
            $form->getPhpValues()[$formName],
        );
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
        $redirectQueryParameters[EA::ENTITY_ID]   = $this->entityIdUnderTest(); // If there is no referrer query parameter set, the redirect will contain the entityId query parameter

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
        array $queryParameters = [],
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
        string $submitButton = Action::SAVE_AND_RETURN,
    ): Crawler {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        $crawler = $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

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

    private function findForm(Crawler $crawler): Form
    {
        return $crawler->filter('#main form')->form();
    }
}
