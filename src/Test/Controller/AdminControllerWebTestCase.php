<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Psl\Dict;
use Psl\Iter;
use Psl\Str;
use Psl\Type;
use Psl\Vec;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function http_build_query;

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

    abstract protected function actionName(): string;

    /**
     * @param array<array-key, mixed> $queryParameters
     * @param positive-int            $expectedResponseStatusCode
     */
    protected function assertRequestGet(array $queryParameters = [], int $expectedResponseStatusCode = Response::HTTP_OK): Crawler
    {
        $crawler = $this->getClient()->request(Request::METHOD_GET, $this->prepareAdminUrl($queryParameters));

        self::assertResponseStatusCode($this->getClient()->getResponse(), $expectedResponseStatusCode);

        return $crawler;
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    protected function prepareAdminUrl(array $queryParameters): string
    {
        $queryParameters[EA::CRUD_CONTROLLER_FQCN] ??= $this->controllerUnderTest();
        $queryParameters[EA::CRUD_ACTION]          ??= $this->actionName();

        return $this->signUrl(static::$easyAdminRoutePath . '?' . http_build_query($queryParameters));
    }

    /**
     * @param FormField|array<array-key, FormField>|array<array-key, array<array-key, FormField>> $fields
     *
     * @return array<array-key, mixed>
     */
    protected function mapFieldsErrors(Crawler $crawler, FormField|array $fields): array
    {
        if ($fields instanceof FormField) {
            $currentFormWidget = $crawler
                ->filter(Str\format('input[name="%1$s"],select[name="%1$s"],textarea[name="%1$s"]', $fields->getName()))
                ->closest('.form-widget');

            if ($currentFormWidget === null) {
                return [];
            }

            return $currentFormWidget
                ->filter('.invalid-feedback span.form-error-message')
                ->extract(['_text']);
        }

        return Dict\map(
            $fields,
            /**
             * @param FormField|array<array-key, FormField> $fields
             */
            fn (FormField|array $fields): array => $this->mapFieldsErrors($crawler, $fields)
        );
    }

    /**
     * @return array<string,string>
     */
    protected function mapActions(Crawler $actionsCrawler): array
    {
        /** @var list<string> $actionsName */
        $actionsName = $actionsCrawler->each(
            static function (Crawler $crawler): string {
                $actionClassName = Iter\first(
                    Vec\filter(
                        Str\split(Str\trim(Type\string()->assert(Iter\first($crawler->extract(['class'])))), ' '),
                        static fn (string $class) => Str\starts_with(Str\trim($class), 'action-'),
                    )
                );

                return Str\slice(Type\non_empty_string()->assert($actionClassName), 7);
            }
        );

        /** @var list<string> $actionsLabel */
        $actionsLabel = $actionsCrawler->each(static fn (Crawler $crawler): string => $crawler->text());

        return Dict\associate($actionsName, $actionsLabel);
    }

    protected function assertPageTitle(string $expectedPageTitle): void
    {
        $crawler = $this->getClient()->getCrawler();

        self::assertCount(1, $crawler->filter('h1.title'));
        self::assertSame($expectedPageTitle, Str\trim($crawler->filter('h1.title')->text()));
    }
}
