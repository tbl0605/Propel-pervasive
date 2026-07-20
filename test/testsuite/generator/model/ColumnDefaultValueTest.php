<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

require_once dirname(__FILE__) . '/../../../../generator/lib/model/ColumnDefaultValue.php';

/**
 * Tests for ColumnDefaultValue class.
 *
 * @version    $Revision$
 * @package    generator.model
 */
class ColumnDefaultValueTest extends PHPUnit_Framework_TestCase
{
    public static function equalsProvider()
    {
        return array(
            array(new ColumnDefaultValue('foo', 'bar'), new ColumnDefaultValue('foo', 'bar'), true),
            array(new ColumnDefaultValue('foo', 'bar'), new ColumnDefaultValue('foo1', 'bar'), false),
            array(new ColumnDefaultValue('foo', 'bar'), new ColumnDefaultValue('foo', 'bar1'), false),
            array(new ColumnDefaultValue('current_timestamp', 'bar'), new ColumnDefaultValue('now()', 'bar'), true),
            array(new ColumnDefaultValue('current_timestamp', 'bar'), new ColumnDefaultValue('now()', 'bar1'), false),
            array(new ColumnDefaultValue('getdate()', ColumnDefaultValue::TYPE_EXPR), new ColumnDefaultValue('CURRENT_TIMESTAMP', ColumnDefaultValue::TYPE_EXPR), true),
            array(new ColumnDefaultValue('GETDATE()', ColumnDefaultValue::TYPE_EXPR), new ColumnDefaultValue('now()', ColumnDefaultValue::TYPE_EXPR), true),
            array(new ColumnDefaultValue('sysdatetime()', ColumnDefaultValue::TYPE_EXPR), new ColumnDefaultValue('CURRENT_TIMESTAMP', ColumnDefaultValue::TYPE_EXPR), true),
            array(new ColumnDefaultValue('getdate()', ColumnDefaultValue::TYPE_EXPR), new ColumnDefaultValue('CURRENT_TIMESTAMP', ColumnDefaultValue::TYPE_VALUE), false),
        );
    }

    /**
     * @dataProvider equalsProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('equalsProvider')]
    public function testEquals($def1, $def2, $test)
    {
        if ($test) {
            $this->assertTrue($def1->equals($def2));
        } else {
            $this->assertFalse($def1->equals($def2));
        }
    }
}
