<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Generator;
use Override;
use Protung\EasyAdminPlusBundle\ProtungEasyAdminPlusBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\UX\TwigComponent\TwigComponentBundle;

use function dirname;

final class TestKernel extends SymfonyKernel
{
    use MicroKernelTrait;

    /**
     * The environment in which the admin of each tenant is served from its own subdomain.
     */
    public const string ENVIRONMENT_TENANT = 'test_tenant';

    #[Override]
    public function registerBundles(): Generator
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
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
        return $this->varDir() . '/cache/' . $this->getEnvironment();
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->varDir() . '/log/' . $this->getEnvironment();
    }

    /**
     * Inside the checkout, so that checkouts don't share a compiled container.
     */
    private function varDir(): string
    {
        return dirname(__DIR__, 3) . '/var';
    }

    public function configureRoutes(RoutingConfigurator $routes): void
    {
        $adminRoutes = $routes->import('.', 'easyadmin.routes');

        if ($this->getEnvironment() === self::ENVIRONMENT_TENANT) {
            $adminRoutes->host('{tenant}.localhost');
        }
    }

    public function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure()
            ->load(__NAMESPACE__ . '\\Controller\\', __DIR__ . '/Controller');
        $container->parameters()->set('locale', 'en');

        $container->extension(
            'framework',
            [
                'router' => ['utf8' => true],
                // @mago-expect lint:no-literal-password The kernel secret of the test application.
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
                    'mappings' => [
                        'TestApplication' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => '%kernel.project_dir%/Entity',
                            'prefix' => __NAMESPACE__ . '\\Entity',
                        ],
                    ],
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
                                // the user the admin test cases log in as by default.
                                'admin-test' => [
                                    // @mago-expect lint:no-literal-password The password of the in-memory test user.
                                    'password' => 'admin-test',
                                    'roles' => ['ROLE_ADMIN'],
                                ],
                            ],
                        ],
                    ],
                ],
                'firewalls' => [
                    // the firewall the admin test cases log in to by default.
                    'easyadmin' => [
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

        $container->extension(
            'twig_component',
            ['anonymous_template_directory' => 'components/'],
        );
    }
}
