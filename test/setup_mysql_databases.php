<?php

/**
 * Create MySQL databases required by Propel test fixtures.
 *
 * Connection settings are read from test/fixtures/bookstore/runtime-conf.xml.
 */

$fixturesDir = __DIR__ . '/fixtures';
$configPath = $fixturesDir . '/bookstore/runtime-conf.xml';

/**
 * @return array{host: string, port: ?string, user: string, password: string, dbname: ?string}
 */
function propel_test_mysql_connection_from_runtime_conf($configPath)
{
    if (!is_readable($configPath)) {
        throw new RuntimeException('Config not found or not readable: ' . $configPath);
    }

    $xml = simplexml_load_file($configPath);
    if ($xml === false) {
        throw new RuntimeException('Invalid XML in config: ' . $configPath);
    }

    if (!isset($xml->propel->datasources->datasource)) {
        throw new RuntimeException('No datasources in config: ' . $configPath);
    }

    $defaultId = (string) $xml->propel->datasources['default'];
    $selectedDatasource = null;

    foreach ($xml->propel->datasources->datasource as $datasource) {
        if ($defaultId === '' || (string) $datasource['id'] === $defaultId) {
            $selectedDatasource = $datasource;
            if ($defaultId !== '') {
                break;
            }
        }
    }

    if ($selectedDatasource === null) {
        $selectedDatasource = $xml->propel->datasources->datasource[0];
    }

    $connection = $selectedDatasource->connection;
    $adapter = strtolower((string) $selectedDatasource->adapter);
    if ($adapter !== '' && $adapter !== 'mysql') {
        throw new RuntimeException('Expected mysql adapter in ' . $configPath . ', got: ' . $adapter);
    }

    $dsn = (string) $connection->dsn;
    $parsed = propel_test_parse_mysql_dsn($dsn);

    return array(
        'host' => $parsed['host'],
        'port' => $parsed['port'],
        'user' => (string) $connection->user,
        'password' => (string) $connection->password,
        'dbname' => $parsed['dbname'],
    );
}

/**
 * @return array{host: string, port: ?string, dbname: ?string}
 */
function propel_test_parse_mysql_dsn($dsn)
{
    if (strpos($dsn, 'mysql:') !== 0) {
        throw new RuntimeException('Not a MySQL DSN: ' . $dsn);
    }

    $params = array();
    foreach (explode(';', substr($dsn, 6)) as $pair) {
        if ($pair === '' || strpos($pair, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $pair, 2);
        $params[$key] = $value;
    }

    return array(
        'host' => isset($params['host']) ? $params['host'] : '127.0.0.1',
        'port' => isset($params['port']) ? $params['port'] : null,
        'dbname' => isset($params['dbname']) ? $params['dbname'] : null,
    );
}

/**
 * @param string $fixturesDir
 *
 * @return string[]
 */
function propel_test_collect_mysql_database_names($fixturesDir)
{
    $databases = array(
        'bookstore_schemas',
        'contest',
        'second_hand_books',
        'reverse_bookstore',
    );

    $patterns = array(
        $fixturesDir . '/*/build.properties',
        $fixturesDir . '/*/runtime-conf.xml',
        $fixturesDir . '/reverse/*/build.properties',
        $fixturesDir . '/reverse/*/runtime-conf.xml',
    );

    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: array() as $path) {
            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            if (preg_match_all('/mysql:(?:[^;\s"\']*;)*dbname=([A-Za-z0-9_]+)/', $contents, $matches)) {
                foreach ($matches[1] as $dbname) {
                    $databases[] = $dbname;
                }
            }
        }
    }

    return array_values(array_unique($databases));
}

try {
    $connection = propel_test_mysql_connection_from_runtime_conf($configPath);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$host = $connection['host'];
$user = $connection['user'];
$password = $connection['password'];

$databases = propel_test_collect_mysql_database_names($fixturesDir);

if ($connection['dbname'] !== null && !in_array($connection['dbname'], $databases, true)) {
    array_unshift($databases, $connection['dbname']);
}

$dsn = 'mysql:host=' . $host;
if ($connection['port'] !== null) {
    $dsn .= ';port=' . $connection['port'];
}

try {
    $pdo = new PDO($dsn, $user, $password);
} catch (PDOException $e) {
    fwrite(STDERR, 'ERROR: Unable to connect to MySQL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

foreach ($databases as $database) {
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $database) . '`');
    echo 'Database ready: ' . $database . PHP_EOL;
}

exit(0);
