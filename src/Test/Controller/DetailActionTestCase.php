<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use ReflectionProperty;

use function Safe\sprintf;
use function trim;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class DetailActionTestCase extends AdminControllerWebTestCase
{
    protected static string $expectedEntityIdUnderTest;

    protected static ?string $expectedPageTitle = null;

    protected function expectedPageTitle(): ?string
    {
        if (static::$expectedPageTitle === null) {
            throw new LogicException(
                sprintf(
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
                sprintf(
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

    public function testPageLoadsForShow(): void
    {
        $queryParameters = [EA::CRUD_ACTION => Action::DETAIL, EA::ENTITY_ID => $this->entityIdUnderTest()];

        $this->assertRequestGet($queryParameters);

        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }
    }

    /**
     * @param array<string,string> $expectedDetails
     * @param array<mixed>         $queryParameters
     * @param array<string,string> $expectedActions
     */
    protected function assertPage(array $expectedDetails, array $expectedActions = [], array $queryParameters = []): void
    {
        $queryParameters[EA::CRUD_ACTION] = Action::DETAIL;
        $queryParameters[EA::ENTITY_ID]   = $this->entityIdUnderTest();

        $this->assertRequestGet($queryParameters);
        $expectedTitle = $this->expectedPageTitle();
        if ($expectedTitle !== null) {
            $this->assertPageTitle($expectedTitle);
        }

        $this->assertActions($expectedActions);
        $this->assertDetails($expectedDetails);
    }

    protected function assertPageTitle(string $expectedPageTitle): void
    {
        $crawler = $this->getClient()->getCrawler();

        self::assertCount(1, $crawler->filter('h1'));
        self::assertSame($expectedPageTitle, trim($crawler->filter('h1')->text()));
    }

    /**
     * @param array<string,string> $expectedActions
     */
    protected function assertActions(array $expectedActions): void
    {
        self::assertCount(
            $this->getClient()->getCrawler()->filter('.page-actions a')->count(),
            $expectedActions
        );

        foreach ($expectedActions as $actionName => $actionLabel) {
            $action = $this->getClient()->getCrawler()->filter('.page-actions a.action-' . $actionName)->first();

            self::assertCount(1, $action, 'No action "' . $actionName . '" found.');
            self::assertSame($actionLabel, $action->text());
        }
    }

    /**
     * @param array<string,string> $expectedDetails
     */
    protected function assertDetails(array $expectedDetails): void
    {
        self::assertCount(
            $this->getClient()->getCrawler()->filter('#main dl.datalist > div')->count(),
            $expectedDetails
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
        $field = $this->getClient()->getCrawler()->filter('#main dl.datalist > div')->eq($index);

        return $field->filter('dd')->text();
    }

    private function getDetailValue(int $index): string
    {
        $field = $this->getClient()->getCrawler()->filter('#main dl.datalist > div')->eq($index);

        return $field->filter('dt')->text();
    }
}