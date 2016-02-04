<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * This is a utility class for all generated Peer classes in the system.
 *
 * This class gives last chance to modify "by hand" a generated SQL order
 * before issuing it. One typical use case is to add index hints to speed up slow SELECT orders.<br />
 * The BasePeerSQLRewriter object has to be defined through
 * {@link BasePeer::setSQLRewriter($sqlRewriter)} <b>BEFORE</b> issuing the SQL order.
 * You can remove the BasePeerSQLRewriter object by calling
 * <code>BasePeer::removeSQLRewriter()</code>. Be aware that the BasePeerSQLRewriter object
 * will handle <b>ALL</b> SQL orders as long as it's not removed from the BasePeer class.
 *
 * @author     Thierry BLIND
 * @version    $Revision$
 * @package    propel.runtime.util
 */
interface BasePeerSQLRewriter {
    public function rewrite(&$sql);
}
