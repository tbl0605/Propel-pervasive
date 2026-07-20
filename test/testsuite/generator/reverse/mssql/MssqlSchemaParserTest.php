<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

require_once dirname(__FILE__) . '/../../../../../generator/lib/reverse/mssql/MssqlSchemaParser.php';
require_once dirname(__FILE__) . '/../../../../../generator/lib/model/PropelTypes.php';
require_once dirname(__FILE__) . '/../../../../../generator/lib/model/ColumnDefaultValue.php';

/**
 * Tests for Mssql database schema parser.
 *
 * @author      Pierre Tachoire
 * @version     $Revision$
 * @package     propel.generator.reverse.mssql
 */
class MssqlSchemaParserTest extends PHPUnit_Framework_TestCase
{
  public function testCleanDelimitedIdentifiers()
  {
    $parser = new TestableMssqlSchemaParser(null);

    $expected = 'this is a tablename';

    $tested = $parser->cleanDelimitedIdentifiers('\''.$expected.'\'');
    $this->assertEquals($expected, $tested);

    $tested = $parser->cleanDelimitedIdentifiers('\''.$expected);
    $this->assertEquals('\''.$expected, $tested);

    $tested = $parser->cleanDelimitedIdentifiers($expected.'\'');
    $this->assertEquals($expected.'\'', $tested);

    $expected = 'this is a tabl\'ename';

    $tested = $parser->cleanDelimitedIdentifiers('\''.$expected.'\'');
    $this->assertEquals($expected, $tested);

    $tested = $parser->cleanDelimitedIdentifiers('\''.$expected);
    $this->assertEquals('\''.$expected, $tested);

    $tested = $parser->cleanDelimitedIdentifiers($expected.'\'');
    $this->assertEquals($expected.'\'', $tested);

    $expected = 'this is a\'tabl\'ename';

    $tested = $parser->cleanDelimitedIdentifiers('\''.$expected.'\'');
    $this->assertEquals($expected, $tested);

    $tested = $parser->cleanDelimitedIdentifiers('\''.$expected);
    $this->assertEquals('\''.$expected, $tested);

    $tested = $parser->cleanDelimitedIdentifiers($expected.'\'');
    $this->assertEquals($expected.'\'', $tested);

  }

  /**
   * @dataProvider providerForTestCleanColumnDefault
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestCleanColumnDefault')]
  public function testCleanColumnDefault($input, $expected)
  {
    $parser = new TestableMssqlSchemaParser(null);
    $this->assertEquals($expected, $parser->cleanColumnDefault($input));
  }

  public static function providerForTestCleanColumnDefault()
  {
    return array(
      array('0', '0'),
      array('(0)', '0'),
      array('((0))', '0'),
      array('(((0)))', '0'),
      array("('foo')", "'foo'"),
      array("(('foo'))", "'foo'"),
      array("(N'foo')", "'foo'"),
      array("((N'foo'))", "'foo'"),
      array("(N'99991231')", "'99991231'"),
      array('(getdate())', 'getdate()'),
      array('((getdate()))', 'getdate()'),
      array('(1)+(2)', '(1)+(2)'),
      array('((1)+(2))', '(1)+(2)'),
      array("('foo(bar)')", "'foo(bar)'"),
      array("('it''s')", "'it''s'"),
      array("(N'it''s')", "'it''s'"),
      array(' ( ( 0 ) ) ', '0'),
      array('(NULL)', 'NULL'),
      array('((NULL))', 'NULL'),
      array(' NULL ', 'NULL'),
    );
  }

  /**
   * @dataProvider providerForTestCreateColumnDefaultValue
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestCreateColumnDefaultValue')]
  public function testCreateColumnDefaultValue($input, $expectedValue, $expectedType)
  {
    $parser = new TestableMssqlSchemaParser(null);
    $default = $parser->createColumnDefaultValue($input);
    if ($expectedValue === null) {
      $this->assertNull($default);
      return;
    }
    $this->assertEquals($expectedValue, $default->getValue());
    $this->assertEquals($expectedType, $default->getType());
  }

  public static function providerForTestCreateColumnDefaultValue()
  {
    return array(
      array('((0))', '0', ColumnDefaultValue::TYPE_VALUE),
      array('((1))', '1', ColumnDefaultValue::TYPE_VALUE),
      array('(-1)', '-1', ColumnDefaultValue::TYPE_VALUE),
      array('(3.14)', '3.14', ColumnDefaultValue::TYPE_VALUE),
      array('(1e3)', '1e3', ColumnDefaultValue::TYPE_VALUE),
      array('(0x4141)', '0x4141', ColumnDefaultValue::TYPE_VALUE),
      array("('foo')", "'foo'", ColumnDefaultValue::TYPE_VALUE),
      array("(N'foo')", "'foo'", ColumnDefaultValue::TYPE_VALUE),
      array("(N'99991231')", "'99991231'", ColumnDefaultValue::TYPE_VALUE),
      array("('getdate()')", "'getdate()'", ColumnDefaultValue::TYPE_VALUE),
      array('(getdate())', 'getdate()', ColumnDefaultValue::TYPE_EXPR),
      array('((getdate()))', 'getdate()', ColumnDefaultValue::TYPE_EXPR),
      array('(GETDATE())', 'GETDATE()', ColumnDefaultValue::TYPE_EXPR),
      array('(sysdatetime())', 'sysdatetime()', ColumnDefaultValue::TYPE_EXPR),
      array('(current_timestamp)', 'current_timestamp', ColumnDefaultValue::TYPE_EXPR),
      array('(newid())', 'newid()', ColumnDefaultValue::TYPE_EXPR),
      array('(user_name())', 'user_name()', ColumnDefaultValue::TYPE_EXPR),
      array('(convert(varchar(10), getdate(), 101))', 'convert(varchar(10), getdate(), 101)', ColumnDefaultValue::TYPE_EXPR),
      array('((1)+(2))', '(1)+(2)', ColumnDefaultValue::TYPE_EXPR),
      array('(NULL)', null, null),
      array('((NULL))', null, null),
      array('NULL', null, null),
    );
  }

  /**
   * @dataProvider providerForTestIsColumnDefaultLiteral
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestIsColumnDefaultLiteral')]
  public function testIsColumnDefaultLiteral($default, $expected)
  {
    $parser = new TestableMssqlSchemaParser(null);
    $this->assertSame($expected, $parser->isColumnDefaultLiteral($default));
  }

  public static function providerForTestIsColumnDefaultLiteral()
  {
    return array(
      array('0', true),
      array('-1', true),
      array('3.14', true),
      array('1e3', true),
      array('0xDEAD', true),
      array("'foo'", true),
      array("'it''s'", true),
      array("'getdate()'", true),
      array("N'foo'", false),
      array('getdate()', false),
      array('newid()', false),
      array('convert(varchar(10), getdate(), 101)', false),
      array('(1)+(2)', false),
      array('', false),
    );
  }
}

class TestableMssqlSchemaParser extends MssqlSchemaParser
{
  public function cleanDelimitedIdentifiers($identifier)
  {
    return parent::cleanDelimitedIdentifiers($identifier);
  }

  public function cleanColumnDefault($default)
  {
    return parent::cleanColumnDefault($default);
  }

  public function createColumnDefaultValue($default)
  {
    return parent::createColumnDefaultValue($default);
  }

  public function isColumnDefaultLiteral($default)
  {
    return parent::isColumnDefaultLiteral($default);
  }
}
