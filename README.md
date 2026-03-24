# Vault library

[![Github](https://img.shields.io/badge/source-itk--dev/vault--library-blue?style=flat-square)](https://github.com/itk-dev/vault-library)
[![Release](https://img.shields.io/packagist/v/itk-dev/vault.svg?style=flat-square&label=release)](https://packagist.org/packages/itk-dev/vault)
[![PHP Version](https://img.shields.io/packagist/php-v/itk-dev/vault.svg?style=flat-square&colorB=%238892BF)](https://www.php.net/downloads)
[![Build Status](https://img.shields.io/github/actions/workflow/status/itk-dev/vault-library/pr.yaml?label=CI&logo=github&style=flat-square)](https://github.com/itk-dev/vault-library/actions?query=workflow%3A%22Test+%26+Code+Style+Review%22)
[![Codecov Code Coverage](https://img.shields.io/codecov/c/gh/itk-dev/vault-library?label=codecov&logo=codecov&style=flat-square)](https://codecov.io/gh/itk-dev/vault-library)
[![Read License](https://img.shields.io/packagist/l/itk-dev/vault-library.svg?style=flat-square&colorB=darkcyan)](https://github.com/itk-dev/vault-library/blob/master/LICENSE.md)
[![Package downloads on Packagist](https://img.shields.io/packagist/dt/itk-dev/vault.svg?style=flat-square&colorB=darkmagenta)](https://packagist.org/packages/itk-dev/vault/stats)

A PHP library for authenticating and fetching secrets with HashiCorp Vault
using the `approle` method. This library implements the PSR-18 and PSR-17 interfaces,
so you will need to provide your own HTTP client.

## Install

You can install this library by utilizing PHP Composer, which is the recommended
dependency management tool for PHP.

```shell
composer require itk-dev/vault
```

## Usage

See [itk-dev/vault-bundle](https://github.com/itk-dev/vault-bundle)

## Developing

See details on contributing in the [contributing docs](/docs/CONTRIBUTING.md).
