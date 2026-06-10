<?php

/**
 * PHPUnit base test case with backward-compatible APIs for tests written for PHPUnit 4/5.
 *
 * @license MIT License
 */

use PHPUnit\Framework\TestCase;

/**
 * @method mixed getMock(string $originalClassName, $methods = [], array $arguments = [], string $mockClassName = '', bool $callOriginalConstructor = true, bool $callOriginalClone = true, bool $callAutoload = true, bool $cloneArguments = false, bool $callOriginalMethods = false, $proxyTarget = null)
 */
abstract class PropelTestCase extends TestCase
{
    /**
     * {@inheritdoc}
     *
     * Translates legacy @expectedException docblock annotations to expectException() calls.
     */
    protected function runTest()
    {
        $this->applyLegacyExpectedExceptionAnnotation();

        return parent::runTest();
    }

    /**
     * @return void
     */
    private function applyLegacyExpectedExceptionAnnotation()
    {
        $name = method_exists($this, 'name') ? $this->name() : $this->getName();
        if ($name === false || $name === '' || !method_exists($this, $name)) {
            return;
        }

        $doc = (new ReflectionMethod($this, $name))->getDocComment();
        if ($doc === false) {
            return;
        }

        if (preg_match('/@expectedException\s+(\S+)/', $doc, $matches)) {
            $this->expectException($matches[1]);
        }
        if (preg_match('/@expectedExceptionMessage\s+(.+)$/m', $doc, $matches)) {
            $this->expectExceptionMessage(trim($matches[1]));
        }
        if (preg_match('/@expectedExceptionCode\s+(\d+)/', $doc, $matches)) {
            $this->expectExceptionCode((int) $matches[1]);
        }
    }

    /**
     * @param string $expected
     * @param mixed  $actual
     * @param string $message
     *
     * @return void
     */
    public static function assertInternalType($expected, $actual, $message = '')
    {
        $map = array(
            'array' => 'assertIsArray',
            'boolean' => 'assertIsBool',
            'bool' => 'assertIsBool',
            'float' => 'assertIsFloat',
            'double' => 'assertIsFloat',
            'integer' => 'assertIsInt',
            'int' => 'assertIsInt',
            'null' => 'assertNull',
            'numeric' => 'assertIsNumeric',
            'object' => 'assertIsObject',
            'real' => 'assertIsFloat',
            'resource' => 'assertIsResource',
            'resource (closed)' => 'assertIsResource',
            'string' => 'assertIsString',
            'scalar' => 'assertIsScalar',
            'callable' => 'assertIsCallable',
            'iterable' => 'assertIsIterable',
        );

        $expected = strtolower($expected);
        if ($expected === 'resource' && !method_exists(TestCase::class, 'assertIsResource')) {
            TestCase::assertTrue(is_resource($actual), $message);

            return;
        }

        if (isset($map[$expected]) && method_exists(TestCase::class, $map[$expected])) {
            TestCase::{$map[$expected]}($actual, $message);

            return;
        }

        if (method_exists(TestCase::class, 'assertInternalType')) {
            parent::assertInternalType($expected, $actual, $message);

            return;
        }

        self::fail(sprintf('assertInternalType() is not supported for type "%s".', $expected));
    }

    /**
     * PHPUnit 4/5 compatible mock factory.
     *
     * @param string       $originalClassName
     * @param array|string $methods
     * @param array        $arguments
     * @param string       $mockClassName
     * @param bool         $callOriginalConstructor
     * @param bool         $callOriginalClone
     * @param bool         $callAutoload
     * @param bool         $cloneArguments
     * @param bool         $callOriginalMethods
     * @param mixed        $proxyTarget
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function getMock($originalClassName, $methods = array(), array $arguments = array(), $mockClassName = '', $callOriginalConstructor = true, $callOriginalClone = true, $callAutoload = true, $cloneArguments = false, $callOriginalMethods = false, $proxyTarget = null)
    {
        $builder = $this->getMockBuilder($originalClassName);

        if (is_array($methods) && !empty($methods)) {
            if (method_exists($builder, 'onlyMethods')) {
                $builder->onlyMethods($methods);
            } else {
                $builder->setMethods($methods);
            }
        }

        if (!empty($arguments)) {
            $builder->setConstructorArgs($arguments);
        }

        if ($mockClassName !== '') {
            $builder->setMockClassName($mockClassName);
        }

        if (!$callOriginalConstructor) {
            $builder->disableOriginalConstructor();
        }

        if (!$callOriginalClone) {
            $builder->disableOriginalClone();
        }

        if (!$callAutoload) {
            $builder->disableAutoload();
        }

        if ($cloneArguments && method_exists($builder, 'enableArgumentCloning')) {
            $builder->enableArgumentCloning();
        }

        if ($callOriginalMethods && method_exists($builder, 'enableProxyingToOriginalMethods')) {
            $builder->enableProxyingToOriginalMethods();
        }

        if ($proxyTarget !== null && method_exists($builder, 'setProxyTarget')) {
            $builder->setProxyTarget($proxyTarget);
        }

        return $builder->getMock();
    }
}
