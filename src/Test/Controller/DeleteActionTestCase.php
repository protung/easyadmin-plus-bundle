<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Test\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use LogicException;
use Psl\Str;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;

/**
 * @template TCrudController
 * @template-extends AdminControllerWebTestCase<TCrudController>
 */
abstract class DeleteActionTestCase extends AdminControllerWebTestCase
{
    protected static string $expectedEntityIdUnderTest;

    /**
     * @param array<array-key, mixed> $queryParameters
     * @param array<array-key, mixed> $redirectQueryParameters
     */
    protected function assertRemovingEntityAndRedirectingToIndexAction(array $queryParameters = [], array $redirectQueryParameters = []): void
    {
        $queryParameters[EA::ENTITY_ID] ??= $this->entityIdUnderTest();
        $this->getClient()->request(
            Request::METHOD_POST,
            $this->prepareAdminUrl($queryParameters),
            ['token' => $this->getCsrfToken('ea-delete')]
        );

        $redirectQueryParameters[EA::CRUD_ACTION] ??= Action::INDEX;
        $this->assertResponseIsRedirect($redirectQueryParameters);
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

    protected function actionName(): string
    {
        return Action::DELETE;
    }
}
