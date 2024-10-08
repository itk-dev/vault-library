# Contributing

This document describes various tools used during development of this library.

## Tests

We use the [PHPUnit](https://phpunit.de/) testing framework.

To run tests execute the following command:

```shell
vendor/bin/phpunit --coverage-clover=coverage/unit.xml
```

## Check coding standards

The following commands let you test that the code follows the coding
standards we decided to adhere to in this project.

### PHP

```shell
composer install
composer coding-standards-check
```

### Markdown

```shell
yarn install
yarn run coding-standards-check
```

## Apply coding standards

You can automatically fix some coding styles issues by running

### PHP

```shell
composer install
composer coding-standards-apply
```
