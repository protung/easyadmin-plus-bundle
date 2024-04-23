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

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class DetailActionTestCase extends AdminControllerWebTestCase
{
    protected static string|int $expectedEntityIdUnderTest;

    protected function actionName(): string
    {
        return Action::DETAIL;
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
    protected function assertPage(array $queryParameters = []): void
    {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        $this->assertRequestGet($queryParameters);

        $actual = [
            'page_title' => $this->extractPageTitle(),
            'data' => $this->extractData(),
            'actions' => $this->extractActions(),
        ];

        $this->assertArrayMatchesExpectedJson($actual);
    }

    protected function extractPageTitle(): string
    {
        $title = $this->getClient()->getCrawler()->filter('h1.title');

        self::assertCount(1, $title);

        return $title->text(normalizeWhitespace: true);
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function extractActions(): array
    {
        $actionsCrawler = $this->getClient()->getCrawler()->filter('.page-actions')->children();

        return $this->mapActions($actionsCrawler);
    }

    /**
     * @return array<mixed>
     */
    protected function extractData(): array
    {
        $tabs =  $this->getClient()->getCrawler()->filter($this->mainContentSelector() . ' .nav-tabs');

        if ($tabs->count() > 0) {
            return $tabs->filter('.nav-item a')->each(
                function (Crawler $tab): array {
                    $tabDataSelector = Type\non_empty_string()->coerce($tab->attr('href'));

                    $tabData = $this->getClient()->getCrawler()->filter($tabDataSelector);

                    $fieldsets = $tabData->filter($tabDataSelector . ' fieldset');
                    if ($fieldsets->count() > 0) {
                        return [
                            'tab' => $tab->text(normalizeWhitespace: true),
                            'fieldsets' => $fieldsets->each(
                                $this->extractFieldsFromFieldset(...),
                            ),
                        ];
                    }

                    return [
                        'tab' => $tab->text(normalizeWhitespace: true),
                        'fields' => $tabData->filter($this->fieldSelector())->each(
                            $this->extractField(...),
                        ),
                    ];
                },
            );
        }

        return $this->getClient()->getCrawler()->filter($this->mainContentSelector() . ' fieldset')->each(
            $this->extractFieldsFromFieldset(...),
        );
    }

    /**
     * @return array<mixed>
     */
    protected function extractFieldsFromFieldset(Crawler $fieldset): array
    {
        $fieldsetTitle = $fieldset->filter('div.form-fieldset-title')->text('', normalizeWhitespace: true);

        return [
            'title' =>  $fieldsetTitle !== '' ? $fieldsetTitle : null,
            'fields' => $fieldset->filter($this->fieldSelector())->each(
                $this->extractField(...),
            ),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function extractField(Crawler $field): array
    {
        $label = $field->filter('div.field-label')->text('', normalizeWhitespace: true);

        return [
            'label' => $label !== '' ? $label : null,
            'value' => $field->filter('div.field-value')->text(normalizeWhitespace: true),
        ];
    }

    protected function fieldSelector(): string
    {
        return 'div.field-group';
    }
}
