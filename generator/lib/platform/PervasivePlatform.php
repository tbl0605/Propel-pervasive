<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

require_once dirname(__FILE__) . '/DefaultPlatform.php';
require_once dirname(__FILE__) . '/../model/Domain.php';

/**
 * Pervasive PropelPlatformInterface implementation.
 *
 * @author     Thierry Blind
 * @version    $Revision$
 * @package    propel.generator.platform
 */
class PervasivePlatform extends DefaultPlatform
{

    protected static $dropCount = 0;

    /**
     * Initializes db specific domain mapping.
     */
    protected function initialize()
    {
        parent::initialize();
        $this->setSchemaDomainMapping(new Domain(PropelTypes::INTEGER, "INT"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BOOLEAN, "INT"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::DOUBLE, "FLOAT"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::LONGVARCHAR, "VARCHAR(MAX)"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::CLOB, "VARCHAR(MAX)"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::DATE, "DATETIME"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BU_DATE, "DATETIME"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::TIME, "DATETIME"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::TIMESTAMP, "DATETIME"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BU_TIMESTAMP, "DATETIME"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BINARY, "BINARY(7132)"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::VARBINARY, "VARBINARY(MAX)"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::LONGVARBINARY, "VARBINARY(MAX)"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BLOB, "VARBINARY(MAX)"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::OBJECT, "VARCHAR(MAX)"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::PHP_ARRAY, "VARCHAR(MAX)"));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::ENUM, "TINYINT"));
    }

    /**
     * Builds the DDL SQL to add the tables of a database
     * together with index and foreign keys
     *
     * @return string
     */
    public function getAddTablesDDL(Database $database)
    {
        $ret = $this->getBeginDDL();
        foreach ($database->getTablesForSql() as $table) {
            $ret2 = '';
            foreach ($table->getForeignKeys() as $fk) {
                $ret2 .= $this->getDropForeignKeyDDL($fk);
            }
            if ($ret2) {
                $ret .= $this->getCommentBlockDDL($table->getName());
                $ret .= $ret2;
            }
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->getCommentBlockDDL($table->getName());
            $ret .= $this->getDropTableDDL($table);
            $ret .= $this->getAddTableDDL($table);
            $ret .= $this->getAddIndicesDDL($table);
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret2 = $this->getAddForeignKeysDDL($table);
            if ($ret2) {
                $ret .= $this->getCommentBlockDDL($table->getName());
                $ret .= $ret2;
            }
        }
        $ret .= $this->getEndDDL();

        return $ret;
    }

    /**
     * Builds the DDL SQL for a Column object.
     *
     * @return string
     */
    public function getColumnDDL(Column $col)
    {
        $domain = $col->getDomain();
        $ddl = array(
            $this->quoteIdentifier($col->getName())
        );
        $sqlType = $domain->getSqlType();

        // Special handling of IDENTITY columns ...
        if ($autoIncrement = $col->getAutoIncrementString()) {
            if ($col->getType() == PropelTypes::SMALLINT) {
                $autoIncrement = 'SMALLIDENTITY';
            }
            if (! $col->isNotNull()) {
                throw new EngineException(sprintf('You have specified autoIncrement for column "%s" from table "%s", but this column is nullable.', $col->getName(), $col->getTable()->getName()));
            }
            $ddl[] = $autoIncrement;

            if ($default = $this->getColumnDefaultValueDDL($col)) {
                $ddl[] = $default;
            }
        } else {
            if ($this->hasSize($sqlType) && $col->isDefaultSqlType($this)) {
                $ddl[] = $sqlType . $domain->printSize();
            } else {
                $ddl[] = $sqlType;
            }

            if ($default = $this->getColumnDefaultValueDDL($col)) {
                $ddl[] = $default;
            }

            if ($notNull = $this->getNullString($col->isNotNull())) {
                $ddl[] = $notNull;
            }
        }

        return implode(' ', $ddl);
    }

    public function getMaxColumnNameLength()
    {
        return 20;
    }

    public function getNullString($notNull)
    {
        return ($notNull ? "NOT NULL" : "NULL");
    }

    public function supportsNativeDeleteTrigger()
    {
        return true;
    }

    public function supportsInsertNullPk()
    {
        return false;
    }

    /**
     *
     * @see Platform::supportsSchemas()
     */
    public function supportsSchemas()
    {
        return true;
    }
}
