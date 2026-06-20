# Propel #

Propel is an open-source Object-Relational Mapping (ORM) for PHP. **This repository is a fork** of [Propel 1.x](https://github.com/propelorm/Propel) with experimental support for **Pervasive DB**, PHP **7.4+**, and modern PHPUnit versions.

[![PHPUnit](https://github.com/tbl0605/Propel-pervasive/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/tbl0605/Propel-pervasive/actions/workflows/phpunit.yml)
[![Total Downloads](https://poser.pugx.org/tbl0605/propel1-pervasive/downloads)](https://packagist.org/packages/tbl0605/propel1-pervasive)
[![Latest Stable Version](https://poser.pugx.org/tbl0605/propel1-pervasive/v/stable)](https://packagist.org/packages/tbl0605/propel1-pervasive)
[![License](https://poser.pugx.org/tbl0605/propel1-pervasive/license)](https://packagist.org/packages/tbl0605/propel1-pervasive)

## A quick tour of the features ##

Propel has some nice features you should know about:

 - It's a fast and easy way to manage your database;
 - It provides command line tools for generating code (well documented with an IDE-friendly syntax);
 - It's very flexible: you can simply extend Propel;
 - It uses PDO (PHP Data Objects) so it allows you to use the RDBMS of your choice (MySQL, SQLite, PostgreSQL, Oracle and MSSQL are supported);
 - Propel is an open-source project which is [well documented](http://propelorm.org/Propel/documentation/).

THIS IS AN EXPERIMENTAL FORK TO PROVIDE SUPPORT FOR PERVASIVE DB.

What's new:

 - Introduced a new database type called "pervasive";
 - Included most pending pull requests from the Propel's upstream (master) branch;

What's working:

 - Create an XML Schema from an existing Pervasive DB Structure;

What's not working:

 - Testsuite for Pervasive DB is missing;

What's partially working:

 - All the rest is untested but is probably working (concerning the Pervasive DB support);

## Installation ##

### Composer (recommended) ###

```bash
composer require tbl0605/propel1-pervasive
```

The `propel-gen` CLI is available from `vendor/bin/propel-gen` (Unix) or `vendor/bin/propel-gen.bat` (Windows).

### From source ###

```bash
git clone https://github.com/tbl0605/Propel-pervasive.git
cd Propel-pervasive
composer install
```

Legacy PEAR `.tgz` packages (`propel_generator`, `propel_runtime`) and a standalone **`propel_generator-*.phar`** (generator + runtime + Phing) are attached to [GitHub releases](https://github.com/tbl0605/Propel-pervasive/releases) when published. Run the PHAR with:

```bash
php propel_generator-X.Y.Z.phar om
php propel_generator-X.Y.Z.phar /path/to/project insert-sql
```

Build the PHAR locally (requires PHP `phar` extension and a Phing 2.17 PHAR). Use the release tag or `propel.version` from `generator/default.properties`:

```bash
VERSION=X.Y.Z
php -d phar.readonly=0 test/tools/build_propel_gen_phar.php --version="$VERSION"
```

For original Propel 1.x concepts (schemas, behaviors, migrations), the [Propel 1 documentation](http://propelorm.org/documentation/) remains useful background reading.

## Running tests

Tests require **PHP 7.4+**, **Composer**, **MySQL**, and the **Phing** build tool (installed via Composer).

```bash
composer install
composer test:setup    # create MySQL databases
test/reset_tests.sh    # rebuild fixtures (use test\reset_tests.cmd on Windows)
composer test          # run PHPUnit (requires phpunit in PATH or use CI PHAR flow)
```

GitHub Actions runs the full matrix automatically: see [.github/workflows/phpunit.yml](.github/workflows/phpunit.yml).

**Note:** Test fixtures use `root` with an empty password on `127.0.0.1` by default (`test/fixtures/bookstore/runtime-conf.xml`). Do not reuse these settings outside a local/CI test environment.

## License ##

Propel is an open-source project released under the MIT license. See the [LICENSE](LICENSE) file for more information.
