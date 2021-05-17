<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\HttpFoundation\Request;

use function http_build_query;
use function is_array;
use function Psl\Dict\map;
use function Safe\sprintf;

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
     * @param array<string,mixed> $queryParameters
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
     * @param array<string,mixed> $queryParameters
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
}
