<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Override;
use PHPUnit\Framework\TestCase;
use Protung\EasyAdminPlusBundle\Controller\BaseCrudController;
use stdClass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BaseCrudControllerTest extends TestCase
{
    /**
     * @see https://github.com/protung/easyadmin-plus-bundle/issues/17
     */
    public function testFlashMessageIsTranslatedInTheDefaultDomainWithoutAdminContext(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Entity saved.' => 'The entity was saved.'], 'en');

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set(TranslatorInterface::class, $translator);
        $container->set(AdminContextProviderInterface::class, new AdminContextProvider($requestStack));

        $controller = new /** @extends BaseCrudController<stdClass> */ class extends BaseCrudController {
            #[Override]
            public static function getEntityFqcn(): string
            {
                return stdClass::class;
            }

            public function addSuccessFlash(string $message): void
            {
                $this->addFlashMessageSuccess($message);
            }
        };
        $controller->setContainer($container);

        $controller->addSuccessFlash('Entity saved.');

        self::assertSame(['success' => ['The entity was saved.']], $session->getFlashBag()->all());
    }
}
