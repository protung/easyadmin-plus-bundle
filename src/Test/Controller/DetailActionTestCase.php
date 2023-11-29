<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use PHPUnit\Framework\ExpectationFailedException;
use Psl\Iter;
use Psl\Str;
use Psl\File;
use Psl\Json;
use ReflectionProperty;

use function is_countable;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class DetailActionTestCase extends AdminControllerWebTestCase
{
    protected static string $expectedEntityIdUnderTest;

    protected static string|null $expectedPageTitle = null;

    protected function actionName(): string
    {
        return Action::DETAIL;
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
     * @param iterable<string,string> $expectedDetails
     * @param array<mixed>            $queryParameters
     * @param array<string,string>    $expectedActions
     */
    protected function assertPage(iterable $expectedDetails, array $expectedActions = [], array $queryParameters = []): void
    {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        $this->assertRequestGet($queryParameters);
        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $this->assertActions($expectedActions);
        $this->assertDetails($expectedDetails);
    }

    /**
     * @param iterable<string,string> $expectedDetails
     * @param array<mixed>            $queryParameters
     * @param array<string,string>    $expectedActions
     */
    protected function assertPageFromJsonExpectedFile(array $expectedActions = [], array $queryParameters = []): void
    {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();

        $this->assertRequestGet($queryParameters);
        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $expectedDetails = Json\decode(File\read($this->getCurrentExpectedResponseContentFile('json')));

        $this->assertActions($expectedActions);
        $this->assertDetailsFromJsonExpectedFile($expectedDetails);
    }

    /**
     * @param array<string,string> $expectedDetails
     */
    protected function assertDetailsFromJsonExpectedFile(array $expectedDetails): void
    {
        $actualDetailsCount = $this->getClient()->getCrawler()->filter('#main div.row > .field-group')->count();

        self::assertCount(
            $actualDetailsCount,
            $expectedDetails,
        );

        $actualDetails = [];
        for ($i = 0; $i < $actualDetailsCount; $i++) {
            $actualDetails[$this->getDetailLabel($i)] = $this->getDetailValue($i);
        }

        try {
            self::assertEquals($expectedDetails, $actualDetails);
        } catch (ExpectationFailedException $e) {
//         file_put_contents($this->getCurrentExpectedResponseContentFile('json'), Json\encode($actualDetails, true));

            throw $e;
        }
    }

    /**
     * @param array<string,string> $expectedActions
     */
    protected function assertActions(array $expectedActions): void
    {
        $actionsCrawler = $this->getClient()->getCrawler()->filter('.page-actions a');

        $actualActions = $this->mapActions($actionsCrawler);

        self::assertSame($expectedActions, $actualActions);
    }

    /**
     * @param iterable<string,string> $expectedDetails
     */
    protected function assertDetails(iterable $expectedDetails): void
    {
        if (! is_countable($expectedDetails)) {
            $expectedDetails = Iter\to_iterator($expectedDetails);
        }

        self::assertCount(
            $this->getClient()->getCrawler()->filter('#main div.row > .field-group')->count(),
            $expectedDetails,
        );

        $index = 0;
        foreach ($expectedDetails as $label => $value) {
            $this->assertDetail($index++, $label, $value);
        }
    }

    private function assertDetail(int $index, string $label, string $value): void
    {
        self::assertSame($label, $this->getDetailLabel($index));
        $this->assertMatchesPattern($value, $this->getDetailValue($index));
    }

    private function getDetailLabel(int $index): string
    {
        $field = $this->getClient()->getCrawler()->filter('#main div.row > .field-group')->eq($index);

        return $field->filter('.field-label')->text(normalizeWhitespace: true);
    }

    private function getDetailValue(int $index): string
    {
        $field = $this->getClient()->getCrawler()->filter('#main div.row > .field-group')->eq($index);

        return $field->filter('.field-value')->text(normalizeWhitespace: true);
    }
}
