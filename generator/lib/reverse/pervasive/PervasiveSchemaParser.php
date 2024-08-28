<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */
require_once dirname(__FILE__) . '/../BaseSchemaParser.php';

/**
 * Pervasive database schema parser.
 *
 * @author     Thierry Blind
 * @version    $Revision$
 * @package    propel.generator.reverse.pervasive
 */
class PervasiveSchemaParser extends BaseSchemaParser
{

    /**
     * Map Pervasive native types to Propel types.
     *
     * @var array
     */
    // http://docs.pervasive.com/products/database/psqlv11/wwhelp/wwhimpl/js/html/wwhelp.htm#href=ODBC/SQLSysTb.12.3.html
    // http://docs.pervasive.com/products/database/psqlv11/wwhelp/wwhimpl/js/html/wwhelp.htm#href=ODBC/SQLDtype.10.2.html#134810
    private static $propelTypeMap = array(
        'AUTOINC2' => PropelTypes::SMALLINT, // Type code = 15
        'AUTOINC4' => PropelTypes::INTEGER, // Type code = 15
        'BFLOAT4' => PropelTypes::REAL, // Type code = 9
        'BFLOAT8' => PropelTypes::DOUBLE, // Type code = 9
        'CLOB/BLOB' => PropelTypes::LONGVARBINARY, // Type code = 21
        'CURRENCY' => PropelTypes::DECIMAL, // Type code = 19
        'DATE' => PropelTypes::DATE, // Type code = 3
        'DATETIME' => PropelTypes::TIMESTAMP, // Type code =  30 // TODO: Check...
        'DECIMAL' => PropelTypes::DECIMAL, // Type code = 5
        'REAL' => PropelTypes::REAL, // Type code = 2
        'DOUBLE' => PropelTypes::DOUBLE, // Type code = 2
        'GUID' => PropelTypes::LONGVARBINARY, // Type code = 27 // SQL_GUID (UNIQUEIDENTIFIER)
        'TINYINT' => PropelTypes::TINYINT, // Type code = 1
        'SMALLINT' => PropelTypes::SMALLINT, // Type code = 1
        'INTEGER' => PropelTypes::INTEGER, // Type code = 1
        'BIGINT' => PropelTypes::BIGINT, // Type code = 1 // TODO: PropelTypes::SQL_DECIMAL doesn't exist
        'MONEY' => PropelTypes::DECIMAL, // Type code = 6
        'NUMERIC' => PropelTypes::NUMERIC, // Type code = 8
        'NUMERICSA' => PropelTypes::NUMERIC, // Type code = 18
        'NUMERICSLB' => PropelTypes::NUMERIC, // Type code = 28
        'NUMERICSLS' => PropelTypes::NUMERIC, // Type code = 29
        'NUMERICSTB' => PropelTypes::NUMERIC, // Type code = 31
        'NUMERICSTS' => PropelTypes::NUMERIC, // Type code = 17
        'STRING' => PropelTypes::CHAR, // Type code = 0
        'BINARY' => PropelTypes::BINARY, // Type code = 0, Flag = 4096
        'TIME' => PropelTypes::TIME, // Type code = 4
        'TIMESTAMP' => PropelTypes::TIMESTAMP, // Type code = 20
        'UNSIGNED1 BINARY' => PropelTypes::TINYINT, // Type code = 14
        'UNSIGNED2 BINARY' => PropelTypes::SMALLINT, // Type code = 14
        'UNSIGNED4 BINARY' => PropelTypes::INTEGER, // Type code = 14
        'UNSIGNED8 BINARY' => PropelTypes::DECIMAL, // Type code = 14
        // Type code = 25 or 26 not supported
        'VARCHAR' => PropelTypes::VARCHAR, // Type code = 11
        // http://docs.pervasive.com/products/database/psqlv11/pdac/wwhelp/wwhimpl/common/html/wwhelp.htm#href=pdacref.2.6.html&single=true
        'BIT' => PropelTypes::BOOLEAN, // Type code = 16 // TODO: SQL_BIT doesn't exist -> ftBoolean
        'LOGICAL' => PropelTypes::SMALLINT // Type code = 7 // TODO: SQL_BIT doesn't exist -> ftBoolean or ftSmallint ?
    );

    // TODO: Remove this hack...
    protected function cleanMethod($schemaName, Task $task)
    {
        if ($task instanceof PropelSchemaReverseTask && $task->isSamePhpName()) {
            return $schemaName;
        }

        $name = '';
        $regexp = '/([a-z0-9]+)/i';
        $matches = array();
        $first = true;
        if (preg_match_all($regexp, $schemaName, $matches)) {
            foreach ($matches[1] as $tok) {
                if ($first) {
                    $name = $tok;
                    $first = false;
                    continue;
                }
                $name .= NameGenerator::STD_SEPARATOR_CHAR . ucfirst($tok);
            }
        } else {
            return $schemaName;
        }

        // A variable name cannot start with a number:
        if (preg_match('/^[0-9]/', $name)) {
            $name = NameGenerator::STD_SEPARATOR_CHAR . $name;
        }

        return $name;
    }

    /**
     *
     * @see BaseSchemaParser::getTypeMapping()
     */
    protected function getTypeMapping()
    {
        return self::$propelTypeMap;
    }

    public function parse(Database $database, Task $task = null)
    {
        // ftp://ftp.agris.com/Pervasive/PVSW10.3/Books/SQL_Engine_Reference.pdf
        // http://cs.pervasive.com/forums/p/951/3357.aspx
        // See also: call psp_tables(null, null, 'User table')
        $stmt = $this->dbh->query('SELECT DISTINCT XF$NAME FROM X$FILE, X$FIELD WHERE XF$ID = XE$FILE AND XF$FLAGS & 16 <> 16 AND XE$DATATYPE NOT IN (227, 255) ORDER BY XF$NAME');

        // First load the tables (important that this happen before filling out details of tables)
        $tables = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            array_walk($row, 'PervasiveSchemaParser::rtrim_to_utf8', $task);

            $name = $row[0];
            if ($name == $this->getMigrationTable()) {
                continue;
            }
            $table = new Table($name);
            // TODO: Remove...
            $table->setPhpName($this->cleanMethod($name, $task));
            $table->setIdMethod($database->getDefaultIdMethod());
            $database->addTable($table);
            $tables[] = $table;
        }

        // Now populate only columns.
        foreach ($tables as $table) {
            $this->addColumns($table, $task);
        }

        // Now add indexes and constraints.
        foreach ($tables as $table) {
            $this->addForeignKeys($table, $task);
            $this->addIndexes($table, $task);
            $this->addPrimaryKey($table, $task);
        }

        return count($tables);
    }

    public final static function rtrim_to_utf8(&$valeur, $key, Task $task)
    {
        if (is_string($valeur)) {
            if ($task instanceof PropelSchemaReverseTask) {
                $encoding = $task->getDbEncoding($key);
                if (trim($encoding) !== '') {
                    $valeur = mb_convert_encoding($valeur, 'UTF-8', array(
                        $encoding
                    ));
                }
            }
            $valeur = rtrim($valeur);
        }
    }

    // http://www.pervasive.com/portals/55/documents/psqlv11/Accessing_the_DDF_Files_Through_Views.pdf
    private function calculatePropelTypeKey(& $row)
    {
        switch ($row['DATATYPE']) {
        case '0':
            switch ($row['IS_BINARY']) {
            case 'YES':
                $row['PROPEL_TYPE_KEY'] = 'BINARY';
                break;
            default:
                $row['PROPEL_TYPE_KEY'] = 'STRING';
                break;
            }
            break;
        case '1':
            switch ($row['COL_SIZE']) {
            case '1':
                $row['PROPEL_TYPE_KEY'] = 'TINYINT';
                break;
            case '2':
                $row['PROPEL_TYPE_KEY'] = 'SMALLINT';
                break;
            case '4':
                $row['PROPEL_TYPE_KEY'] = 'INTEGER';
                break;
            case '8':
                $row['PROPEL_TYPE_KEY'] = 'BIGINT';
                break;
            default:
                $row['PROPEL_TYPE_KEY'] = 'UNKNOWN INTEGER';
                break;
            }
            break;
        case '2':
            switch ($row['COL_SIZE']) {
            case '4':
                $row['PROPEL_TYPE_KEY'] = 'REAL';
                break;
            case '8':
                $row['PROPEL_TYPE_KEY'] = 'DOUBLE';
                break;
            default:
                $row['PROPEL_TYPE_KEY'] = 'UNKNOWN REAL';
                break;
            }
            break;
        case '3':
            $row['PROPEL_TYPE_KEY'] = 'DATE';
            break;
        case '4':
            $row['PROPEL_TYPE_KEY'] = 'TIME';
            break;
        case '5':
            $row['PROPEL_TYPE_KEY'] = 'DECIMAL';
            break;
        case '6':
            $row['PROPEL_TYPE_KEY'] = 'MONEY';
            break;
        case '7':
            $row['PROPEL_TYPE_KEY'] = 'LOGICAL';
            break;
        case '8':
            $row['PROPEL_TYPE_KEY'] = 'NUMERIC';
            break;
        case '9':
            switch ($row['COL_SIZE']) {
            case '4':
                $row['PROPEL_TYPE_KEY'] = 'BFLOAT4';
                break;
            case '8':
                $row['PROPEL_TYPE_KEY'] = 'BFLOAT8';
                break;
            default:
                $row['PROPEL_TYPE_KEY'] = 'UNKNOWN BFLOAT';
                break;
            }
            break;
        case '11':
            $row['PROPEL_TYPE_KEY'] = 'VARCHAR';
            break;
        case '14':
            switch ($row['COL_SIZE']) {
            case '1':
                $row['PROPEL_TYPE_KEY'] = 'UNSIGNED1 BINARY';
                break;
            case '2':
                $row['PROPEL_TYPE_KEY'] = 'UNSIGNED2 BINARY';
                break;
            case '4':
                $row['PROPEL_TYPE_KEY'] = 'UNSIGNED4 BINARY';
                break;
            case '8':
                $row['PROPEL_TYPE_KEY'] = 'UNSIGNED8 BINARY';
                break;
            default:
                $row['PROPEL_TYPE_KEY'] = 'UNKNOWN UNSIGNED BINARY';
                break;
            }
            break;
        case '15':
            switch ($row['COL_SIZE']) {
            case '2':
                $row['PROPEL_TYPE_KEY'] = 'AUTOINC2';
                break;
            case '4':
                $row['PROPEL_TYPE_KEY'] = 'AUTOINC4';
                break;
            default:
                $row['PROPEL_TYPE_KEY'] = 'UNKNOWN AUTOINC';
                break;
            }
            break;
        case '16':
            $row['PROPEL_TYPE_KEY'] = 'BIT';
            break;
        case '17':
            $row['PROPEL_TYPE_KEY'] = 'NUMERICSTS';
            break;
        case '18':
            $row['PROPEL_TYPE_KEY'] = 'NUMERICSA';
            break;
        case '19':
            $row['PROPEL_TYPE_KEY'] = 'CURRENCY';
            break;
        case '20':
            $row['PROPEL_TYPE_KEY'] = 'TIMESTAMP';
            break;
        case '21':
            $row['PROPEL_TYPE_KEY'] = 'CLOB/BLOB';
            break;
        case '25':
            $row['PROPEL_TYPE_KEY'] = 'WSTRING';
            break;
        case '26':
            $row['PROPEL_TYPE_KEY'] = 'WZTRING';
            break;
        case '27':
            $row['PROPEL_TYPE_KEY'] = 'GUID';
            break;
        case '28':
            $row['PROPEL_TYPE_KEY'] = 'NUMERICSLB';
            break;
        case '29':
            $row['PROPEL_TYPE_KEY'] = 'NUMERICSLS';
            break;
        case '30':
            $row['PROPEL_TYPE_KEY'] = 'DATETIME';
            break;
        case '31':
            $row['PROPEL_TYPE_KEY'] = 'NUMERICSTB';
            break;
        default:
            $row['PROPEL_TYPE_KEY'] = 'DATATYPE UNKNOWN = ' . $row['DATATYPE'];
            break;
        }

        // The original query sometimes crashed our Pervasive servers,
        // so do all calculations on PHP-side now...
        // And generation is also much faster now...
/*
        // http://www.pervasive.com/portals/55/documents/psqlv11/Accessing_the_DDF_Files_Through_Views.pdf
        $query = '
     , CASE XE$DATATYPE
          WHEN 0 THEN \'STRING\'
          WHEN 1 THEN
             CASE XE$SIZE
                WHEN 1 THEN \'TINYINT\'
                WHEN 2 THEN \'SMALLINT\'
                WHEN 4 THEN \'INTEGER\'
                WHEN 8 THEN \'BIGINT\'
                ELSE \'UNKNOWN INTEGER\'
             END
          WHEN 2 THEN
             CASE XE$SIZE
                WHEN 4 THEN \'REAL\'
                WHEN 8 THEN \'DOUBLE\'
                ELSE \'UNKNOWN REAL\'
             END
          WHEN 3 THEN \'DATE\'
          WHEN 4 THEN \'TIME\'
          WHEN 5 THEN \'DECIMAL\'
          WHEN 6 THEN \'MONEY\' -- added
          WHEN 7 THEN \'LOGICAL\' -- added
          WHEN 8 THEN \'NUMERIC\'
          WHEN 9 THEN
             CASE XE$SIZE
                WHEN 4 THEN \'BFLOAT4\'
                WHEN 8 THEN \'BFLOAT8\'
                ELSE \'UNKNOWN BFLOAT\'
             END
          WHEN 11 THEN \'VARCHAR\'
          WHEN 14 THEN
             CASE XE$SIZE
                WHEN 1 THEN \'UNSIGNED1 BINARY\'
                WHEN 2 THEN \'UNSIGNED2 BINARY\'
                WHEN 4 THEN \'UNSIGNED4 BINARY\'
                WHEN 8 THEN \'UNSIGNED8 BINARY\'
                ELSE \'UNKNOWN UNSIGNED BINARY\'
             END
          WHEN 15 THEN
             CASE XE$SIZE
                WHEN 2 THEN \'AUTOINC2\'
                WHEN 4 THEN \'AUTOINC4\'
                ELSE \'UNKNOWN AUTOINC\'
             END
          WHEN 16 THEN \'BIT\'
          WHEN 17 THEN \'NUMERICSTS\'
          WHEN 18 THEN \'NUMERICSA\'
          WHEN 19 THEN \'CURRENCY\'
          WHEN 20 THEN \'TIMESTAMP\'
          WHEN 21 THEN \'CLOB/BLOB\'
          WHEN 25 THEN \'WSTRING\'
          WHEN 26 THEN \'WZTRING\'
          WHEN 27 THEN \'GUID\'
          WHEN 28 THEN \'NUMERICSLB\' -- added
          WHEN 29 THEN \'NUMERICSLS\' -- added
          WHEN 30 THEN \'DATETIME\'
          WHEN 31 THEN \'NUMERICSTB\' -- added
          ELSE CONCAT(\'DATATYPE UNKNOWN = \', CAST(XE$DATATYPE AS CHAR(3)))
       END PROPEL_TYPE_KEY
';
*/
    }

    /**
     * Adds Columns to the specified table.
     *
     * @param Table $table
     *            The Table model class to add columns to.
     */
    protected function addColumns(Table $table, Task $task = null)
    {
        // ftp://ftp.agris.com/Pervasive/PVSW10.3/Books/SQL_Engine_Reference.pdf
        // http://www.pervasive.com/portals/55/documents/psqlv11/Accessing_the_DDF_Files_Through_Views.pdf
        // http://support.pervasive.com/t5/tkb/articleprintpage/tkb-id/Database_KnowledgeBase/article-id/162
        // TODO: XE$OFFSET is NOT unique (per table), find ordering used by Pervasive Control Center.
        // Actually it's neither by XE$ID nor by XE$NAME, arbitrarily we will use XE$NAME.
        // See also: call psp_columns(null, '$table->getName()', null)
        $query = '
SELECT TAB.XF$NAME TABLE_NAME
     , COL.XE$NAME COLUMN_NAME
     , COL.XE$DATATYPE DATATYPE
     , COL.XE$SIZE COL_SIZE
     , COL.XE$SIZE CHAR_LEN
     , COL.XE$SIZE PRECISION
     , COL.XE$DEC SCALE
     , COL.XE$OFFSET OFFSET
     , CASE
          WHEN COL.XE$FLAGS & 4096 = 4096 THEN \'YES\'
          ELSE \'NO\'
       END IS_BINARY
     , CASE
          WHEN COL.XE$FLAGS & 4 = 4 THEN \'YES\'
          ELSE \'NO\'
       END NULLABLE
     , CASE
          WHEN ATT.XA$ID IS NOT NULL AND ATT.XA$TYPE = \'D\' THEN \'YES\'
          ELSE \'NO\'
       END HAS_DEF_VAL
     , ATT.XA$ATTRS DEFAULT_VALUE
  FROM X$FILE AS TAB
 INNER JOIN X$FIELD AS COL
    ON COL.XE$FILE = TAB.XF$ID
  LEFT JOIN X$ATTRIB AS ATT
    ON ATT.XA$ID = COL.XE$ID
   AND ATT.XA$TYPE = \'D\'
 WHERE TAB.XF$FLAGS & 16 <> 16
   AND COL.XE$DATATYPE NOT IN (227, 255)
   AND TAB.XF$NAME = \'' . $table->getName() . '\'
 ORDER BY COL.XE$OFFSET
     , COL.XE$NAME
';
        $stmt = $this->dbh->query($query);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_walk($row, 'PervasiveSchemaParser::rtrim_to_utf8', $task);

            $this->calculatePropelTypeKey($row);

            $name = $row['COLUMN_NAME'];
            $type = $row['PROPEL_TYPE_KEY'];
            $size = $row['CHAR_LEN'];
            $is_nullable = $row['NULLABLE'] === 'YES';
            $has_default = $row['HAS_DEF_VAL'] === 'YES';
            $default = $row['DEFAULT_VALUE'];
            //$precision = $row['PRECISION'];
            $scale = $row['SCALE'];
            $autoincrement = false;
            if (strpos($type, 'AUTOINC') !== false) {
                $autoincrement = true;
            }

            $propelType = $this->getMappedPropelType($type);
            if ($propelType === null) {
                $propelType = Column::DEFAULT_TYPE;
                $this->warn('Column [' . $table->getName() . '.' . $name . '] has a column type (' . $type . ') that Propel does not support.');
            }

            $column = new Column($name);
            // TODO: Remove...
            $column->setPhpName($this->cleanMethod($name, $task));
            $column->setTable($table);
            $column->setDomainForType($propelType);
            // We may want to provide an option to include this:
            // $column->getDomain()->replaceSqlType($type);
            $column->getDomain()->replaceSize($size);
            $column->getDomain()->replaceScale($scale);
            if ($has_default) {
                $column->getDomain()->setDefaultValue(new ColumnDefaultValue($default, ColumnDefaultValue::TYPE_VALUE));
            }
            $column->setAutoIncrement($autoincrement);
            $column->setNotNull(! $is_nullable);

            $table->addColumn($column);
        }
    }

    /**
     * Load foreign keys for this table.
     */
    protected function addForeignKeys(Table $table, Task $task = null)
    {
        $database = $table->getDatabase();

        // ftp://ftp.agris.com/Pervasive/PVSW10.3/Books/SQL_Engine_Reference.pdf
        // See also: call psp_fkeys(null, '$table->getName()', null)
        $query = '
SELECT A.XR$NAME RULE_NAME
     , TABSRC.XF$NAME FKTABLE
     , IDXSRC.XI$PART FKPART
     , FLDSRC.XE$NAME FKCOLUMN
     , TABDEST.XF$NAME PKTABLE
     , IDXDEST.XI$PART PKPART
     , FLDDEST.XE$NAME PKCOLUMN
     , CASE
          WHEN A.XR$UPDATERULE = 1 THEN \'RESTRICT\'
          ELSE \'UNKNOWN\'
       END UPDATE_RULE
     , CASE
          WHEN A.XR$DELETERULE = 1 THEN \'RESTRICT\'
          WHEN A.XR$DELETERULE = 2 THEN \'CASCADE\'
          ELSE \'UNKNOWN\'
       END DELETE_RULE
  FROM X$RELATE A
     , X$FILE TABSRC
     , X$INDEX IDXSRC
     , X$FIELD FLDSRC
     , X$FILE TABDEST
     , X$INDEX IDXDEST
     , X$FIELD FLDDEST
 WHERE TABSRC.XF$ID = A.XR$PID
   AND IDXSRC.XI$FILE = TABSRC.XF$ID
   AND IDXSRC.XI$NUMBER = A.XR$INDEX
   AND FLDSRC.XE$ID = IDXSRC.XI$FIELD
   AND FLDSRC.XE$FILE = TABSRC.XF$ID
   AND TABDEST.XF$ID = A.XR$FID
   AND IDXDEST.XI$FILE = TABDEST.XF$ID
   AND IDXDEST.XI$NUMBER = A.XR$FINDEX
   AND IDXDEST.Xi$FLAGS & POWER(2,13) <> 0
   AND FLDDEST.XE$ID = IDXDEST.XI$FIELD
   AND FLDDEST.XE$FILE = TABDEST.XF$ID
   AND IDXDEST.XI$PART = IDXSRC.XI$PART
   AND TABDEST.XF$FLAGS & 16 <> 16
   AND TABDEST.XF$NAME = \'' . $table->getName() . '\'
 ORDER BY A.XR$NAME
     , IDXDEST.XI$PART
     , IDXSRC.XI$PART
';
        $stmt = $this->dbh->query($query);

        $foreignKeys = array(); // local store to avoid duplicates
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_walk($row, 'PervasiveSchemaParser::rtrim_to_utf8', $task);

            $name = $row['RULE_NAME'];
            $lcol = $row['PKCOLUMN'];
            $ftbl = $row['FKTABLE'];
            $fcol = $row['FKCOLUMN'];

            $foreignTable = $database->getTable($ftbl);
            $foreignColumn = $foreignTable->getColumn($fcol);
            $localColumn = $table->getColumn($lcol);

            if (! isset($foreignKeys[$name])) {
                $fk = new ForeignKey($name);
                $fk->setForeignTableCommonName($foreignTable->getCommonName());
                $fk->setForeignSchemaName($foreignTable->getSchema());
                $fk->setOnDelete($row['DELETE_RULE']);
                $fk->setOnUpdate($row['UPDATE_RULE']);
                $table->addForeignKey($fk);
                $foreignKeys[$name] = $fk;
            }
            $foreignKeys[$name]->addReference($localColumn, $foreignColumn);
        }
    }

    /**
     * Load indexes for this table
     */
    protected function addIndexes(Table $table, Task $task = null)
    {
        // ftp://ftp.agris.com/Pervasive/PVSW10.3/Books/SQL_Engine_Reference.pdf
        // See also: call psp_indexes(null, '$table->getName()')
        //
        // Final Note - not all indexes have names.  If the DDFs have been migrated forward
        // from older versions, there might be index definitions in X$Index that do not have
        // corresponding names in X$Field.  This is acceptable, from the engine's point of view.
        //
        // Use MOD(Xi$FLAGS,2) = 0 if you want to select only the named indexes.
        //
        // NB: XE$OFFSET *should* be unique (per index), so add DISTINCT to be sure.
        $query = '
SELECT DISTINCT
       COLIDX.XE$NAME INDEX_NAME
     , COL.XE$NAME COLUMN_NAME
     , CASE
          WHEN IDX.XI$FLAGS & 1 = 0 THEN \'YES\'
          ELSE \'NO\'
       END IS_UNIQUE
     , TAB.XF$NAME TABLE_NAME
     , IDX.XI$NUMBER INDEX_NUMBER
  FROM X$FILE AS TAB
     , X$INDEX AS IDX
     , X$FIELD AS COL
     , X$FIELD AS COLIDX
 WHERE TAB.XF$FLAGS & 16 <> 16
   AND TAB.XF$NAME = \'' . $table->getName() . '\'
   AND IDX.XI$FILE = TAB.XF$ID
   AND IDX.XI$FLAGS & POWER(2,13) = 0
   AND COL.XE$ID = IDX.XI$FIELD
   AND COLIDX.XE$FILE = TAB.XF$ID
   AND COLIDX.XE$DATATYPE IN (227, 255)
   AND COLIDX.XE$OFFSET = IDX.XI$NUMBER
 ORDER BY IDX.XI$NUMBER
     , IDX.XI$PART
';
        $stmt = $this->dbh->query($query);

        $indexes = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_walk($row, 'PervasiveSchemaParser::rtrim_to_utf8', $task);

            $colName = $row['COLUMN_NAME'];
            $name = $row['INDEX_NAME'];
            // Not all indexes have names.
            if (! strlen($name)) {
                $name = 'unnamed_index_' . $row['TABLE_NAME'] . '_' . $row['INDEX_NUMBER'];
            } elseif (strpos($name, 'unnamed_index_') === 0) {
                $name = '_' . $name;
            }
            $unique = $row['IS_UNIQUE'] === 'YES';

            if (! isset($indexes[$name])) {
                if ($unique) {
                    $indexes[$name] = new Unique($this->cleanMethod($name, $task));
                } else {
                    $indexes[$name] = new Index($this->cleanMethod($name, $task));
                }
                $table->addIndex($indexes[$name]);
            }

            $indexes[$name]->addColumn($table->getColumn($colName));
        }
    }

    /**
     * Loads the primary key for this table.
     */
    protected function addPrimaryKey(Table $table, Task $task = null)
    {
        // ftp://ftp.agris.com/Pervasive/PVSW10.3/Books/SQL_Engine_Reference.pdf
        // See also: call psp_indexes(null, '$table->getName()')
        //
        // NB: XE$OFFSET *should* be unique (per index), so put it in an EXISTS clause to be sure.
        $query = '
SELECT COL.XE$NAME
  FROM X$FILE AS TAB
     , X$INDEX AS IDX
     , X$FIELD AS COL
 WHERE TAB.XF$FLAGS & 16 <> 16
   AND TAB.XF$NAME = \'' . $table->getName() . '\'
   AND IDX.XI$FILE = TAB.XF$ID
   AND IDX.XI$FLAGS & POWER(2,13) = 0
   AND COL.XE$ID = IDX.XI$FIELD
   AND EXISTS(
       SELECT 1
         FROM X$FIELD AS COLIDX
        WHERE COLIDX.XE$FILE = TAB.XF$ID
          AND COLIDX.XE$DATATYPE IN (227, 255)
          AND COLIDX.XE$OFFSET = IDX.XI$NUMBER
       )
   AND IDX.XI$FLAGS & 1 = 0
 ORDER BY IDX.XI$NUMBER
     , IDX.XI$PART
';
        $stmt = $this->dbh->query($query);

        // Loop through the returned results, grouping the same key_name together
        // adding each column for that key.
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            array_walk($row, 'PervasiveSchemaParser::rtrim_to_utf8', $task);

            $name = $row[0];
            $table->getColumn($name)->setPrimaryKey(true);
        }
    }

    /**
     * according to the identifier definition, we have to clean simple quote (') around the identifier name
     * returned by pervasive
     *
     * @see http://msdn.microsoft.com/library/ms175874.aspx
     *
     * @param string $identifier
     *
     * @return string
     */
    protected function cleanDelimitedIdentifiers($identifier)
    {
        return preg_replace('/^\'(.*)\'$/U', '$1', $identifier);
    }
}
