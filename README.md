# Propel #

Propel is an open-source Object-Relational Mapping (ORM) for PHP5.

[![Build Status](https://secure.travis-ci.org/propelorm/Propel.png?branch=master)](http://travis-ci.org/propelorm/Propel)
[![Total Downloads](https://poser.pugx.org/propel/propel1/downloads.png)](https://packagist.org/packages/propel/propel1)
[![Latest Stable Version](https://poser.pugx.org/propel/propel1/v/stable.png)](https://packagist.org/packages/propel/propel1)

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
 - Included some pending pull requests from the Propel's upstream (master) branch;

What's working:

 - Create an XML Schema from an existing Pervasive DB Structure;

What's not working:

 - Testsuite for Pervasive DB is missing;

What's partially working:

 - All the rest;

## Installation ##

Read the [Propel documentation](http://propelorm.org/Propel/).


## License ##

Propel is an open-source project released under the MIT license. See the `LICENSE` file for more information.
