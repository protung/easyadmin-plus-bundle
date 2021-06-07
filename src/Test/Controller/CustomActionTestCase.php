<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
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
abstract class CustomActionTestCase extends AdminControllerWebTestCase
{
    protected static ?string $expectedPageTitle = null;

    protected function expectedPageTitle(): ?string
    {
        return static::$expectedPageTitle;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $queryParameters
     * @param array<array-key, mixed> $redirectQueryParameters
     */
    protected function assertSubmittingFormAndRedirectingToIndexAction(
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

        $form     = $this->findForm($crawler);
        $formName = $form->getFormNode()->getAttribute('name');

        $fields = $form->get($formName);

        $actual = $this->mapFieldsErrors($crawler, $fields);

        $formExpectedErrors['_token'] = [];
        $this->assertMatchesPattern($formExpectedErrors, $actual);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $queryParameters
     */
    protected function submitFormRequest(
        array $data,
        array $files = [],
        array $queryParameters = []
    ): Crawler {
        $crawler = $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $form     = $this->findForm($crawler);
        $formName = $form->getFormNode()->getAttribute('name');

        $data['_token'] = Type\object(FormField::class)->coerce($form->get($formName . '[_token]'))->getValue();

        $values = [$formName => $data];
        $files  = [$formName => $files];

        return $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($queryParameters),
            $values,
            $files
        );
    }

    private function findForm(Crawler $crawler): Form
    {
        return $crawler->filter('.content-wrapper form')->form();
    }
}
