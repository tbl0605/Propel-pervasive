<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * This is used to connect to a Pervasive database.
 *
 * @author     Hans Lellelid <hans@xmpl.org> (Propel)
 * @version    $Revision$
 * @package    propel.runtime.adapter
 */
class DBPervasive extends DBAdapter
{
    /**
     * MS SQL Server does not support SET NAMES
     *
     * @see       DBAdapter::setCharset()
     *
     * @param PDO    $con
     * @param string $charset
     */
    public function setCharset(PDO $con, $charset)
    {
    }

    /**
     * This method is used to ignore case.
     *
     * @param string $in The string to transform to upper case.
     *
     * @return string The upper case string.
     */
    public function toUpperCase($in)
    {
        return $this->ignoreCase($in);
    }

    /**
     * This method is used to ignore case.
     *
     * @param string $in The string whose case to ignore.
     *
     * @return string The string in a case that can be ignored.
     */
    public function ignoreCase($in)
    {
        return 'UPPER(' . $in . ')';
    }

    /**
     * Returns SQL which concatenates the second string to the first.
     *
     * @param string $s1 String to concatenate.
     * @param string $s2 String to append.
     *
     * @return string
     */
    public function concatString($s1, $s2)
    {
        return '(' . $s1 . ' + ' . $s2 . ')';
    }

    /**
     * Returns SQL which extracts a substring.
     *
     * @param string  $s   String to extract from.
     * @param integer $pos Offset to start from.
     * @param integer $len Number of characters to extract.
     *
     * @return string
     */
    public function subString($s, $pos, $len)
    {
        return 'SUBSTRING(' . $s . ', ' . $pos . ', ' . $len . ')';
    }

    /**
     * Returns SQL which calculates the length (in chars) of a string.
     *
     * @param string $s String to calculate length of.
     *
     * @return string
     */
    public function strLength($s)
    {
        return 'LENGTH(' . $s . ')';
    }

    /**
     * @see       DBAdapter::quoteIdentifierTable()
     *
     * @param string $table
     *
     * @return string
     */
    public function quoteIdentifierTable($table)
    {
        // e.g. 'database.table alias' should be escaped as '[database].[table] [alias]'
        return '"' . strtr($table, array('.' => '"."', ' ' => '" "')) . '"';
    }

    /**
     * @see       DBAdapter::random()
     *
     * @param string $seed
     *
     * @return string
     */
    public function random($seed = null)
    {
        return 'RAND(' . ((int) $seed) . ')';
    }

    /**
     * Should Column-Names get identifiers for inserts or updates.
     * By default false is returned -> backwards compability.
     *
     * it`s a workaround...!!!
     *
     * @todo should be abstract
     * @deprecated
     *
     * @return boolean
     */
    public function useQuoteIdentifier()
    {
        return true;
    }

    /**
     * Simulated Limit/Offset
     *
     * This rewrites the $sql query to apply the offset and limit.
     * some of the ORDER BY logic borrowed from Doctrine MsSqlPlatform.
     * <b>For now, offset simulation is not working and $offset
     * parameter should be set to 0.</b>
     *
     * @see       DBAdapter::applyLimit()
     * @author    Benjamin Runnels <kraven@kraven.org>
     * @todo      Make offset simulation work
     *
     * @param string  $sql
     * @param integer $offset
     * @param integer $limit
     *
     * @return void
     *
     * @throws PropelException
     * @throws Exception
     */
    public function applyLimit(&$sql, $offset, $limit)
    {
        // make sure offset and limit are numeric
        if (!is_numeric($offset) || !is_numeric($limit)) {
            throw new PropelException('DBPervasive::applyLimit() expects a number for argument 2 and 3');
        }

        //split the select and from clauses out of the original query
        $selectSegment = array();

        $selectText = 'SELECT ';

        preg_match('/\Aselect(.*)from(.*)/si', $sql, $selectSegment);
        if (count($selectSegment) == 3) {
            $selectStatement = trim($selectSegment[1]);
            $fromStatement = trim($selectSegment[2]);
        } else {
            throw new Exception('DBPervasive::applyLimit() could not locate the select statement at the start of the query.');
        }

        if (preg_match('/\Aselect(\s+)distinct/i', $sql)) {
            $selectText .= 'DISTINCT ';
            $selectStatement = str_ireplace('distinct ', '', $selectStatement);
        }

        // if we're starting at offset 0 then theres no need to simulate LIMIT,
        // just grab the top $limit number of rows.
        // TODO : works only starting from Pervasive V8.
        if ($offset == 0) {
            $sql = $selectText . 'TOP ' . $limit . ' ' . $selectStatement . ' FROM ' . $fromStatement;
        } else {
            throw new Exception('DBPervasive::applyLimit() does not work with $offset <> 0.');
        }
    }

    /**
     * @see       parent::cleanupSQL()
     *
     * @param string      $sql
     * @param array       $params
     * @param Criteria    $values
     * @param DatabaseMap $dbMap
     */
    public function cleanupSQL(&$sql, array &$params, Criteria $values, DatabaseMap $dbMap)
    {
        $i = 1;
        $paramCols = array();
        foreach ($params as $param) {
            if (null !== $param['table']) {
                $column = $dbMap->getTable($param['table'])->getColumn($param['column']);
                /* Pervasive blob values must be converted to hex and then the hex added
                 * to the query string directly.  If it goes through PDOStatement::bindValue quotes will cause
                 * an error with the insert or update.
                 */
                if (is_resource($param['value']) && $column->isLob()) {
                    // we always need to make sure that the stream is rewound, otherwise nothing will
                    // get written to database.
                    rewind($param['value']);
                    $hexArr = unpack('H*hex', stream_get_contents($param['value']));
                    $sql = str_replace(":p$i", '0x' . $hexArr['hex'], $sql);
                    unset($hexArr);
                    fclose($param['value']);
                } else {
                    $paramCols[] = $param;
                }
            }
            $i++;
        }

        //if we made changes re-number the params
        if ($params != $paramCols) {
            $params = $paramCols;
            unset($paramCols);
            preg_match_all('/:p\d/', $sql, $matches);
            foreach ($matches[0] as $key => $match) {
                $sql = str_replace($match, ':p' . ($key + 1), $sql);
            }
        }
    }
}
