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

## License

This package is licensed using the MIT License.

Please have a look at [`LICENSE.md`](LICENSE.md).
