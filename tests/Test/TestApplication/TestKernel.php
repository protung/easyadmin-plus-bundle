<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Generator;
use Override;
use Protung\EasyAdminPlusBundle\ProtungEasyAdminPlusBundle;
use Psl\Env;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class TestKernel extends SymfonyKernel
{
    use MicroKernelTrait;

    /**
     * @return Generator<BundleInterface>
     */
    #[Override]
    public function registerBundles(): Generator
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new EasyAdminBundle();
        yield new ProtungEasyAdminPlusBundle();
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return Env\temp_dir() . '/com.github.protung.easyadmin-plus-bundle/tests/var/' . $this->getEnvironment() . '/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return Env\temp_dir() . '/com.github.protung.easyadmin-plus-bundle/tests/var/' . $this->getEnvironment() . '/log';
    }

    public function configureRoutes(RoutingConfigurator $routes): void
    {
    }

    public function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();
        $container->parameters()->set('locale', 'en');

        $container->extension(
            'framework',
            [
                'router' => ['utf8' => true],
                'secret' => '$3cr3t',
                'session' => [
                    'handler_id' => null,
                    'storage_factory_id' => 'session.storage.factory.mock_file',
                ],
                'csrf_protection' => true,
                'test' => true,
            ],
        );

        $container->extension(
            'doctrine',
            [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'path' => '%kernel.cache_dir%/test_database.sqlite',
                ],
                'orm' => [
                    'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                    'auto_mapping' => true,
                ],
            ],
        );

        $container->extension(
            'security',
            [
                'password_hashers' => [InMemoryUser::class => 'plaintext'],
                'providers' => [
                    'test_users' => [
                        'memory' => [
                            'users' => [
                                'admin' => [
                                    'password' => '1234',
                                    'roles' => ['ROLE_ADMIN'],
                                ],
                            ],
                        ],
                    ],
                ],
                'firewalls' => [
                    'main' => [
                        'pattern' => '^/',
                        'provider' => 'test_users',
                        'http_basic' => null,
                        'logout' => null,
                    ],
                ],
                'access_control' => [
                    ['path' => '^/', 'roles' => ['ROLE_ADMIN']],
                ],
            ],
        );

        $container->extension(
            'twig',
            [],
        );
    }
}
