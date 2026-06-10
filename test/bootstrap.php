<?php

if (file_exists($file = dirname(__FILE__) . '/../vendor/autoload.php')) {
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/../vendor/phing/phing/classes');

    require_once $file;
}

require_once dirname(__FILE__) . '/tools/helpers/PropelTestCase.php';

if (!class_exists('PHPUnit_Framework_TestCase', false)) {
    class_alias('PropelTestCase', 'PHPUnit_Framework_TestCase');
}
