<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\HttpFoundation\Request;

use function assert;
use function explode;
use function http_build_query;
use function is_array;
use function Psl\Dict\associate;
use function Psl\Dict\map;
use function Psl\Iter\first;
use function Psl\Vec\filter;
use function Safe\sprintf;
use function Safe\substr;
use function str_starts_with;
use function trim;

/**
 * @template TCrudController
 */
abstract class AdminControllerWebTestCase extends AdminWebTestCase
{
    protected static string $easyAdminRoutePath = '/admin';

    /**
     * @return class-string<TCrudController>
     */
    abstract protected function controllerUnderTest(): string;

    /**
     * @param array<mixed> $queryParameters
     */
    protected function assertRequestGet(array $queryParameters): Crawler
    {
        $crawler = $this->getClient()->request(Request::METHOD_GET, $this->prepareAdminUrl($queryParameters));

        self::assertTrue(
            $this->getClient()->getResponse()->isOk(),
            sprintf('Expected response was 200, got %s', $this->getClient()->getResponse()->getStatusCode())
        );

        return $crawler;
    }

    /**
     * @param array<mixed> $queryParameters
     */
    protected function prepareAdminUrl(array $queryParameters): string
    {
        $queryParameters[EA::CRUD_CONTROLLER_FQCN] = $this->controllerUnderTest();

        return $this->signUrl(static::$easyAdminRoutePath . '?' . http_build_query($queryParameters));
    }

    /**
     * @param FormField|array<FormField>|FormField[][] $fields
     *
     * @return array<string|int,mixed>
     */
    protected function mapFieldsErrors(Crawler $crawler, FormField|array $fields): array
    {
        if (is_array($fields)) {
            return map(
                $fields,
                /**
                 * @param FormField|array<FormField>|FormField[][] $fields
                 */
                fn (FormField|array $fields): array => $this->mapFieldsErrors($crawler, $fields)
            );
        }

        $currentFormWidget = $crawler
            ->filter(sprintf('input[name="%1$s"],select[name="%1$s"],textarea[name="%1$s"]', $fields->getName()))
            ->closest('.form-widget');

        if ($currentFormWidget === null) {
            return [];
        }

        return $currentFormWidget
            ->filter('.invalid-feedback span.form-error-message')
            ->extract(['_text']);
    }

    /**
     * @return array<string,string>
     */
    protected function mapActions(Crawler $actionsCrawler): array
    {
        /** @var list<string> $actionsName */
        $actionsName = $actionsCrawler->each(
            static function (Crawler $crawler): string {
                $actionClassName = first(
                    filter(
                        explode(' ', trim((string) first($crawler->extract(['class'])))),
                        static fn (string $class) => str_starts_with(trim($class), 'action-'),
                    )
                );

                assert($actionClassName !== null);

                return substr($actionClassName, 7);
            }
        );

        /** @var list<string> $actionsLabel */
        $actionsLabel = $actionsCrawler->each(static fn (Crawler $crawler): string => $crawler->text());

        return associate($actionsName, $actionsLabel);
    }

    protected function assertPageTitle(string $expectedPageTitle): void
    {
        $crawler = $this->getClient()->getCrawler();

        self::assertCount(1, $crawler->filter('h1.title'));
        self::assertSame($expectedPageTitle, trim($crawler->filter('h1.title')->text()));
    }
}
