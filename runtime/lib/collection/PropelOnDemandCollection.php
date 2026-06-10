<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

/**
 * Class for iterating over a statement and returning one Propel object at a time
 *
 * @author     Francois Zaninotto
 * @package    propel.runtime.collection
 */
class PropelOnDemandCollection extends PropelCollection
{
    /**
     * @var       PropelOnDemandIterator
     */
    protected $iterator;

    /**
     * @param PropelFormatter $formatter
     * @param PDOStatement    $stmt
     */
    public function initIterator(PropelFormatter $formatter, PDOStatement $stmt)
    {
        $this->iterator = new PropelOnDemandIterator($formatter, $stmt);
    }

    /**
     * Populates the collection from an array
     * Each object is populated from an array and the result is stored
     * Does not empty the collection before adding the data from the array
     *
     * @param array $arr
     *
     * @throws PropelException
     */
    public function fromArray($arr)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    // IteratorAggregate Interface

    /**
     * @return PropelOnDemandIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return $this->iterator;
    }

    // ArrayAccess Interface

    /**
     * @throws PropelException
     *
     * @param integer $offset
     *
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        throw new PropelException('The On Demand Collection does not allow access by offset');
    }

    /**
     * @throws PropelException
     *
     * @param integer $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        throw new PropelException('The On Demand Collection does not allow access by offset');
    }

    /**
     * @throws PropelException
     *
     * @param integer $offset
     * @param mixed   $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @throws PropelException
     *
     * @param integer $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    // Serializable Interface

    /**
     * @throws PropelException
     */
    #[\ReturnTypeWillChange]
    public function serialize()
    {
        throw new PropelException('The On Demand Collection cannot be serialized');
    }

    /**
     * @throws PropelException
     *
     * @param string $data
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function unserialize($data)
    {
        throw new PropelException('The On Demand Collection cannot be serialized');
    }

    // Countable Interface

    /**
     * Returns the number of rows in the resultset
     * Warning: this number is inaccurate for most databases. Do not rely on it for a portable application.
     *
     * @return integer Number of results
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->iterator->count();
    }

    // ArrayObject methods (untyped signatures for PHP 7.4 through 8.5 compatibility)

    /**
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function append($value)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function prepend($value)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @param int $flags
     *
     * @return true
     */
    #[\ReturnTypeWillChange]
    public function asort($flags = SORT_REGULAR)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @param array|object $array
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function exchangeArray($array)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function getArrayCopy()
    {
        throw new PropelException('The On Demand Collection does not allow access by offset');
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function getFlags()
    {
        throw new PropelException('The On Demand Collection does not allow access by offset');
    }

    /**
     * @param int $flags
     *
     * @return true
     */
    #[\ReturnTypeWillChange]
    public function ksort($flags = SORT_REGULAR)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @return true
     */
    #[\ReturnTypeWillChange]
    public function natcasesort()
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @return true
     */
    #[\ReturnTypeWillChange]
    public function natsort()
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @param int $flags
     */
    #[\ReturnTypeWillChange]
    public function setFlags($flags)
    {
        throw new PropelException('The On Demand Collection does not allow acces by offset');
    }

    /**
     * @param callable $callback
     *
     * @return true
     */
    #[\ReturnTypeWillChange]
    public function uasort($callback)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * @param callable $callback
     *
     * @return true
     */
    #[\ReturnTypeWillChange]
    public function uksort($callback)
    {
        throw new PropelException('The On Demand Collection is read only');
    }

    /**
     * {@inheritdoc}
     */
    public function exportTo($parser, $usePrefix = true, $includeLazyLoadColumns = true)
    {
        throw new PropelException('A PropelOnDemandCollection cannot be exported.');
    }
}
