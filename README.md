# Vault library

[![Github](https://img.shields.io/badge/source-itk--dev/vault--library-blue?style=flat-square)](https://github.com/itk-dev/vault-library)
[![Release](https://img.shields.io/packagist/v/itk-dev/vault.svg?style=flat-square&label=release)](https://packagist.org/packages/itk-dev/vault)
[![PHP Version](https://img.shields.io/packagist/php-v/itk-dev/vault.svg?style=flat-square&colorB=%238892BF)](https://www.php.net/downloads)
[![Build Status](https://img.shields.io/github/actions/workflow/status/itk-dev/vault-library/php.yaml?label=CI&logo=github&style=flat-square)](https://github.com/itk-dev/vault-library/actions?query=workflow%3APHP)
[![Codecov Code Coverage](https://img.shields.io/codecov/c/gh/itk-dev/vault-library?label=codecov&logo=codecov&style=flat-square)](https://codecov.io/gh/itk-dev/vault-library)
[![Read License](https://img.shields.io/packagist/l/itk-dev/vault-library.svg?style=flat-square&colorB=darkcyan)](https://github.com/itk-dev/vault-library/blob/master/LICENSE.md)
[![Package downloads on Packagist](https://img.shields.io/packagist/dt/itk-dev/vault.svg?style=flat-square&colorB=darkmagenta)](https://packagist.org/packages/itk-dev/vault/stats)

A PHP library for authenticating and fetching secrets with HashiCorp Vault
using the `approle` method. This library implements the PSR-18 and PSR-17 interfaces,
so you will need to provide your own HTTP client.

## Usage

See [itk-dev/vault-bundle](https://github.com/itk-dev/vault-bundle) for usage in a Symfony application.

## Direct Install

You can install this library by utilizing PHP Composer, which is the recommended
dependency management tool for PHP.

```shell
composer require itk-dev/vault
```

## Developing

### Prerequisites

- [Docker](https://www.docker.com/) and [Docker Compose](https://docs.docker.com/compose/)
- [Task](https://taskfile.dev/) (task runner)

### Getting started

```shell
task setup
```

This starts the Docker containers and installs Composer dependencies.

### Available tasks

Run `task` to list all available tasks. Key tasks:

| Task                 | Description                                     |
|----------------------|-------------------------------------------------|
| `task test`          | Run unit tests                                  |
| `task test:coverage` | Run tests with coverage report                  |
| `task test:matrix`   | Run tests across PHP 8.3, 8.4, 8.5 (mirrors CI) |
| `task lint`          | Run all linters (PHP, Composer, Markdown, YAML) |
| `task lint:php:fix`  | Auto-fix PHP coding standards                   |
| `task analyze:php`   | Run PHPStan static analysis                     |
| `task pr:actions`    | Run all CI checks locally                       |

### Test matrix

The test matrix runs against PHP 8.3, 8.4, and 8.5 with both `prefer-lowest`
and `prefer-stable` dependency sets:

```shell
task test:matrix
```

To force a fresh dependency resolve (clearing cached vendor volumes):

```shell
task test:matrix:reset
task test:matrix
```
