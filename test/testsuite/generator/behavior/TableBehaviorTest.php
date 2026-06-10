<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

require_once dirname(__FILE__) . '/../../../../generator/lib/util/PropelQuickBuilder.php';
require_once dirname(__FILE__) . '/../../../../generator/lib/config/GeneratorConfig.php';
require_once dirname(__FILE__) . '/../../../tools/helpers/bookstore/behavior/Testallhooksbehavior.php';

/**
 * Tests the table structure behavior hooks.
 *
 * @author     Francois Zaninotto
 * @package    generator.behavior
 */
class TableBehaviorTest extends PHPUnit_Framework_TestCase
{
    public function testModifyTable()
    {
        if (!class_exists('Table3Peer')) {
            $schema = <<<EOF
<database name="bookstore-behavior" defaultIdMethod="native" package="behavior.alternative_coding_standards">
    <table name="table3">
        <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
        <column name="title" type="VARCHAR" size="100" primaryString="true" />
        <behavior name="test_all_hooks" />
    </table>
</database>
EOF;
            $builder = new PropelQuickBuilder();
            $builder->setSchema($schema);
            $config = $builder->getConfig();
            $config->setBuildProperty(
                'propel.behavior.test_all_hooks.class',
                'TestAllHooksBehavior'
            );
            $builder->build();
        }

        $t = Table3Peer::getTableMap();
        $this->assertTrue($t->hasColumn('test'), 'modifyTable hook is called when building the model structure');
    }
}
