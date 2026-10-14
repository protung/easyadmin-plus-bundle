# EasyAdmin Plus Bundle

Extensions for [EasyAdmin](https://github.com/EasyCorp/EasyAdminBundle): DTO-backed CRUD controllers, entity and JSON fields, an enum filter, menu item matchers, and PHPUnit test cases for dashboards and CRUD actions.

[![Build](https://github.com/protung/easyadmin-plus-bundle/actions/workflows/build.yml/badge.svg?branch=1.x)](https://github.com/protung/easyadmin-plus-bundle/actions/workflows/build.yml?query=branch%3A1.x)

[![Latest Stable Version](https://poser.pugx.org/protung/easyadmin-plus-bundle/v/stable)](https://packagist.org/packages/protung/easyadmin-plus-bundle)
[![Total Downloads](https://poser.pugx.org/protung/easyadmin-plus-bundle/downloads)](https://packagist.org/packages/protung/easyadmin-plus-bundle)

## Installation

Require using composer:

```shell
$ composer require protung/easyadmin-plus-bundle
```

Symfony Flex registers the bundle. Without Flex, add it to `config/bundles.php`:

```php
return [
    // ...
    Protung\EasyAdminPlusBundle\ProtungEasyAdminPlusBundle::class => ['all' => true],
    // ...
];
```

## Confirmation modal with a title and a body

Since EasyAdmin 5.7, a custom message passed to `askConfirmation()` is shown as body text and the modal's title is hidden, while the delete confirmation keeps a title and a description. To give another action's confirmation a title and a body like the delete one's, pass a `ConfirmationModal`:

```php
use Protung\EasyAdminPlusBundle\Action\ConfirmationModal;

use function Symfony\Component\Translation\t;

Action::new('close')
    ->linkToCrudAction('close')
    ->askConfirmation(new ConfirmationModal(t('close.confirmation_modal.title'), t('close.confirmation_modal.content')));
```

A label for the confirmation button can still be passed as `askConfirmation()`'s second argument. Before EasyAdmin 5.7, and for the delete action, the title and the body are shown joined as a single message. Batch actions keep EasyAdmin's batch confirmation.

## License

This package is licensed using the MIT License.

Please have a look at [`LICENSE.md`](LICENSE.md).
