[![Latest Stable Version](https://img.shields.io/packagist/v/loophp/phptree-ast-generator.svg?style=flat-square)](https://packagist.org/packages/loophp/phptree-ast-generator)
 [![GitHub stars](https://img.shields.io/github/stars/loophp/phptree-ast-generator.svg?style=flat-square)](https://packagist.org/packages/loophp/phptree-ast-generator)
 [![Total Downloads](https://img.shields.io/packagist/dt/loophp/phptree-ast-generator.svg?style=flat-square)](https://packagist.org/packages/loophp/phptree-ast-generator)
 [![GitHub Workflow Status](https://img.shields.io/github/workflow/status/loophp/phptree-ast-generator/Continuous%20Integration?style=flat-square)](https://github.com/loophp/phptree-ast-generator/actions)
 [![Scrutinizer code quality](https://img.shields.io/scrutinizer/quality/g/loophp/phptree-ast-generator/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/loophp/phptree-ast-generator/?branch=master)
 [![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/loophp/phptree-ast-generator/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/loophp/phptree-ast-generator/?branch=master)
 [![Mutation testing badge](https://badge.stryker-mutator.io/github.com/loophp/phptree-ast-generator/master)](https://stryker-mutator.github.io)
 [![License](https://img.shields.io/packagist/l/loophp/phptree-ast-generator.svg?style=flat-square)](https://packagist.org/packages/loophp/phptree-ast-generator)
 [![Donate!](https://img.shields.io/badge/Donate-Paypal-brightgreen.svg?style=flat-square)](https://paypal.me/loophp)
 
# PHPTree AST Generator

## Description

An AST generator based on [loophp/phptree](https://packagist.org/packages/loophp/phptree).

![Demo](resources/src-Command-Generator.png)

## Requirements

* PHP >= 7.1
* A PHP Parser:
  * [nikic/php-parser](https://github.com/nikic/php-parser)
  * [microsoft/tolerant-php-parser](https://github.com/microsoft/tolerant-php-parser)
  
## Installation

```composer require loophp/phptree-ast-generator```

## Usage

Very basic usage

```shell script
./path/to/bin/ast generate /path/to/php/file.php
```

To generate the `dot` script for Graphviz

```shell script
./path/to/bin/ast generate src/Command/Generator.php
```

Use the `-c` option to generate a fancy export, user-friendly and less verbose.

```shell script
./path/to/bin/ast generate -c src/Command/Generator.php
```

To generate an image

```shell script
./path/to/bin/ast generate -c -t image -f png -d graph.png src/Command/Generator.php
```

The generator supports 2 PHP parsers:
* [nikic/php-parser](https://github.com/nikic/php-parser)
* [microsoft/tolerant-php-parser](https://github.com/microsoft/tolerant-php-parser)

Use the `-p` option to change it, default is `nikic`.

```shell script
./path/to/bin/ast generate -p microsoft -t image -d graph.svg src/Command/Generator.php
```

You will find more documentation within the help of the command:

```shell script
./path/to/bin/ast generate -h
```

## Contributing

Feel free to contribute to this library by sending Github pull requests. I'm quite reactive :-)
