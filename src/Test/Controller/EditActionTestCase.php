<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Psl\Str;
use ReflectionProperty;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class EditActionTestCase extends AdminControllerWebTestCase
{
    protected static string $expectedEntityIdUnderTest;

    protected static ?string $expectedPageTitle = null;

    protected function actionName(): string
    {
        return Action::EDIT;
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

    protected function entityIdUnderTest(): string
    {
        $rp = new ReflectionProperty($this, 'expectedEntityIdUnderTest');
        $rp->setAccessible(true);
        if (! $rp->isInitialized()) {
            throw new LogicException(
                Str\format(
                    <<<'MSG'
                        Expected entity ID under test was not set.
                        Please set static::$expectedEntityIdUnderTest property in your test or overwrite %s method.
                    MSG,
                    __METHOD__
                )
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

        $form     = $this->getClient()->getCrawler()->filter('#main form')->form();
        $formName = $form->getFormNode()->getAttribute('name');

        $formExpectedFields['_token'] = $this->getCsrfToken($formName);

        $this->assertMatchesPattern(
            $formExpectedFields,
            $form->getPhpValues()[$formName]
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
        array $redirectQueryParameters = []
    ): void {
        $this->makeRequest($data, $files, $queryParameters);

        $redirectQueryParameters[EA::CRUD_ACTION] = Action::INDEX;
        $redirectQueryParameters[EA::ENTITY_ID]   = $this->entityIdUnderTest(); // If there is no referrer query parameter set, the redirect will contain the entityId query parameter

        $expectedRedirectUrl = 'http://localhost' . $this->prepareAdminUrl($redirectQueryParameters);
        self::assertTrue(
            $this->getClient()->getResponse()->isRedirect($expectedRedirectUrl),
            'Expected redirect to the index page after edit.'
        );
    }

    /**
     * @param array<string,mixed>     $formExpectedErrors
     * @param array<string,mixed>     $data
     * @param array<string,mixed>     $files
     * @param array<array-key, mixed> $queryParameters
     */
    protected function assertShowingValidationErrors(
        array $formExpectedErrors,
        array $data,
        array $files = [],
        array $queryParameters = []
    ): void {
        $crawler = $this->makeRequest($data, $files, $queryParameters);

        $form     = $this->getClient()->getCrawler()->filter('#main form')->form();
        $formName = $form->getFormNode()->getAttribute('name');

        self::assertTrue(
            $this->getClient()->getResponse()->isOk(),
            Str\format('Expected response was 200, got %s', $this->getClient()->getResponse()->getStatusCode())
        );

        $fields = $form->get($formName);

        $actual = $this->mapFieldsErrors($crawler, $fields);

        $formExpectedErrors['_token'] = [];
        $this->assertMatchesPattern($formExpectedErrors, $actual);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $queryParameters
     * @param Action::SAVE_*          $submitButton
     */
    protected function makeRequest(
        array $data,
        array $files = [],
        array $queryParameters = [],
        string $submitButton = Action::SAVE_AND_RETURN
    ): Crawler {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        $crawler = $this->assertRequestGet($queryParameters);

        $form     = $crawler->filter('#main form')->form();
        $formName = $form->getFormNode()->getAttribute('name');

        $data['_token'] = $this->getCsrfToken($formName);
        $data['btn']    = $submitButton;
        $values         = [
            $formName => $data,
            'ea' => [
                'newForm' => ['btn' => $submitButton],
            ],
        ];

        return $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($queryParameters),
            $values,
            $files
        );
    }
}
