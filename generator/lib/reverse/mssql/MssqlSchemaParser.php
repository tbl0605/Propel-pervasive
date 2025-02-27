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
 * Microsoft SQL Server database schema parser.
 *
 * @author     Hans Lellelid <hans@xmpl.org>
 * @version    $Revision$
 * @package    propel.generator.reverse.mssql
 */
class MssqlSchemaParser extends BaseSchemaParser
{

    /**
     * Map MSSQL native types to Propel types.
     *
     * @var        array
     */
    private static $mssqlTypeMap = array(
        "binary" => PropelTypes::BINARY,
        "bit" => PropelTypes::BOOLEAN,
        "char" => PropelTypes::CHAR,
        "date" => PropelTypes::DATE,
        "datetime" => PropelTypes::TIMESTAMP,
        "datetime2" => PropelTypes::TIMESTAMP,
        "decimal() identity"  => PropelTypes::DECIMAL,
        "decimal"  => PropelTypes::DECIMAL,
        "image" => PropelTypes::LONGVARBINARY,
        "int" => PropelTypes::INTEGER,
        "int identity" => PropelTypes::INTEGER,
        "integer" => PropelTypes::INTEGER,
        "money" => PropelTypes::DECIMAL,
        "nchar" => PropelTypes::CHAR,
        "ntext" => PropelTypes::LONGVARCHAR,
        "numeric() identity" => PropelTypes::NUMERIC,
        "numeric" => PropelTypes::NUMERIC,
        "nvarchar" => PropelTypes::VARCHAR,
        "real" => PropelTypes::REAL,
        "float" => PropelTypes::FLOAT,
        "smalldatetime" => PropelTypes::TIMESTAMP,
        "smallint" => PropelTypes::SMALLINT,
        "smallint identity" => PropelTypes::SMALLINT,
        "smallmoney" => PropelTypes::DECIMAL,
        "sysname" => PropelTypes::VARCHAR,
        "text" => PropelTypes::LONGVARCHAR,
        "timestamp" => PropelTypes::BINARY,
        "tinyint identity" => PropelTypes::TINYINT,
        "tinyint" => PropelTypes::TINYINT,
        "uniqueidentifier" => PropelTypes::CHAR,
        "varbinary" => PropelTypes::VARBINARY,
        "varbinary(max)" => PropelTypes::CLOB,
        "varchar" => PropelTypes::VARCHAR,
        "varchar(max)" => PropelTypes::CLOB,
    // SQL Server 2000 only
        "bigint identity" => PropelTypes::BIGINT,
        "bigint" => PropelTypes::BIGINT,
        "sql_variant" => PropelTypes::VARCHAR,
    );

    // TODO: Remove this hack...
    protected function cleanMethod($schemaName, Task $task)
    {
        if ($task instanceof PropelSchemaReverseTask && $task->isSamePhpName()) {
            return $schemaName;
        }

        $name = NameFactory::generateName(NameFactory::PHP_GENERATOR, array($schemaName, NameGenerator::CONV_METHOD_CLEAN));

        // A variable name cannot start with a number:
        if (preg_match('/^[0-9]/', $name)) {
            $name = NameGenerator::STD_SEPARATOR_CHAR . $name;
        }

        return $name;
    }

    /**
     * @see        BaseSchemaParser::getTypeMapping()
     */
    protected function getTypeMapping()
    {
        return self::$mssqlTypeMap;
    }

    /**
     *
     */
    public function parse(Database $database, Task $task = null)
    {
        $stmt = $this->dbh->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME <> 'dtproperties' ORDER BY TABLE_NAME");

        // First load the tables (important that this happen before filling out details of tables)
        $tables = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $name = $this->cleanDelimitedIdentifiers($row[0]);
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
            $this->addForeignKeys($table);
            $this->addIndexes($table);
            $this->addPrimaryKey($table);
        }

        return count($tables);
    }

    /**
     * Adds Columns to the specified table.
     *
     * @param Table $table The Table model class to add columns to.
     */
    protected function addColumns(Table $table, Task $task = null)
    {
        $stmt = $this->dbh->query("sp_columns '" . $table->getName() . "'");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $name = $this->cleanDelimitedIdentifiers($row['COLUMN_NAME']);
            $type = $row['TYPE_NAME'];
            $size = $row['LENGTH'];
            $is_nullable = $row['NULLABLE'];
            $default = $row['COLUMN_DEF'];
            //$precision = $row['PRECISION'];
            $scale = $row['SCALE'];
            $autoincrement = false;
            if (substr(strtolower($type), -strlen("int identity")) === "int identity") {
                $autoincrement = true;
            }

            $propelType = $this->getMappedPropelType($type);
            if (!$propelType) {
                $propelType = Column::DEFAULT_TYPE;
                $this->warn("Column [" . $table->getName() . "." . $name . "] has a column type (" . $type . ") that Propel does not support.");
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
            if ($default !== null) {
                $column->getDomain()->setDefaultValue(new ColumnDefaultValue($default, ColumnDefaultValue::TYPE_VALUE));
            }
            $column->setAutoIncrement($autoincrement);
            $column->setNotNull(!$is_nullable);

            $table->addColumn($column);
        }
    }

    /**
     * Load foreign keys for this table.
     */
    protected function addForeignKeys(Table $table)
    {
        $database = $table->getDatabase();

        // http://blog.sqlauthority.com/2006/11/01/sql-server-query-to-display-foreign-key-relationships-and-name-of-the-constraint-for-each-table-in-database/
        $stmt = $this->dbh->query(
"SELECT DISTINCT
        tc.TABLE_NAME,
        kcu.COLUMN_NAME,
        ccu.TABLE_NAME     AS FK_TABLE_NAME,
        ccu.COLUMN_NAME    AS FK_COLUMN_NAME,
        tc.CONSTRAINT_NAME AS FK_NAME
   FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
   LEFT JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
     ON tc.constraint_catalog = rc.constraint_catalog
    AND tc.constraint_schema  = rc.constraint_schema
    AND tc.constraint_name    = rc.constraint_name
   LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
     ON tc.constraint_catalog = kcu.constraint_catalog
    AND tc.constraint_schema  = kcu.constraint_schema
    AND tc.constraint_name    = kcu.constraint_name
  INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE ccu
     ON rc.unique_constraint_catalog = ccu.constraint_catalog
    AND rc.unique_constraint_schema  = ccu.constraint_schema
    AND rc.unique_constraint_name    = ccu.constraint_name
    AND kcu.ordinal_position         = ccu.ordinal_position
  WHERE tc.constraint_type           = 'FOREIGN KEY'
    AND tc.table_name                = '" . $table->getName() . "'"
);

        $foreignKeys = array(); // local store to avoid duplicates
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $lcol = $this->cleanDelimitedIdentifiers($row['COLUMN_NAME']);
            $ftbl = $this->cleanDelimitedIdentifiers($row['FK_TABLE_NAME']);
            $fcol = $this->cleanDelimitedIdentifiers($row['FK_COLUMN_NAME']);
            $name = $this->cleanDelimitedIdentifiers($row['FK_NAME']);

            $foreignTable = $database->getTable($ftbl);
            $foreignColumn = $foreignTable->getColumn($fcol);
            $localColumn = $table->getColumn($lcol);

            if (!isset($foreignKeys[$name])) {
                $fk = new ForeignKey($name);
                $fk->setForeignTableCommonName($foreignTable->getCommonName());
                $fk->setForeignSchemaName($foreignTable->getSchema());
                //$fk->setOnDelete($fkactions['ON DELETE']);
                //$fk->setOnUpdate($fkactions['ON UPDATE']);
                $table->addForeignKey($fk);
                $foreignKeys[$name] = $fk;
            }
            $foreignKeys[$name]->addReference($localColumn, $foreignColumn);
        }
    }

    /**
     * Load indexes for this table
     */
    protected function addIndexes(Table $table)
    {
        $stmt = $this->dbh->query("sp_indexes_rowset '" . $table->getName() . "'");

        $indexes = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $colName = $this->cleanDelimitedIdentifiers($row["COLUMN_NAME"]);
            $name = $this->cleanDelimitedIdentifiers($row['INDEX_NAME']);
            $unique = (string) $row['UNIQUE'] === '1';

            if (! isset($indexes[$name])) {
                if ($unique) {
                    $indexes[$name] = new Unique($name);
                } else {
                    $indexes[$name] = new Index($name);
                }
                $table->addIndex($indexes[$name]);
            }

            $indexes[$name]->addColumn($table->getColumn($colName));
        }
    }

    /**
     * Loads the primary key for this table.
     */
    protected function addPrimaryKey(Table $table)
    {
        $stmt = $this->dbh->query("SELECT COLUMN_NAME
                        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                                INNER JOIN INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE ON
                        INFORMATION_SCHEMA.TABLE_CONSTRAINTS.CONSTRAINT_NAME = INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE.CONSTRAINT_NAME
                        WHERE     (INFORMATION_SCHEMA.TABLE_CONSTRAINTS.CONSTRAINT_TYPE = 'PRIMARY KEY') AND
                        (INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_NAME = '" . $table->getName() . "')");

        // Loop through the returned results, grouping the same key_name together
        // adding each column for that key.
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $name = $this->cleanDelimitedIdentifiers($row[0]);
            $table->getColumn($name)->setPrimaryKey(true);
        }
    }

    /**
     * according to the identifier definition, we have to clean simple quote (') around the identifier name
     * returns by mssql
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
