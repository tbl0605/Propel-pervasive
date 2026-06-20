<?php

/**
 * Build a self-contained propel_generator PHAR (propel-gen CLI + generator + runtime + Phing 2.17.x).
 *
 * Usage:
 *   php -d phar.readonly=0 test/tools/build_propel_gen_phar.php --version=1.8.1
 *   php -d phar.readonly=0 test/tools/build_propel_gen_phar.php --version=1.8.1 \
 *     --output=dist/pear/propel_generator-1.8.1.phar --phing-phar=test/tools/phing-phars/phing-2.17.4.phar
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if (!class_exists('Phar', false)) {
    fwrite(STDERR, "The PHP phar extension is required to build propel-gen PHARs.\n");
    exit(1);
}

if (ini_get('phar.readonly')) {
    fwrite(STDERR, "phar.readonly is enabled. Re-run with: php -d phar.readonly=0 {$argv[0]}\n");
    exit(1);
}

$repoRoot = realpath(dirname(__FILE__) . '/../..');
$generatorRoot = $repoRoot . DIRECTORY_SEPARATOR . 'generator';
$runtimeRoot = $repoRoot . DIRECTORY_SEPARATOR . 'runtime';
$pharAlias = 'propel-gen.phar';

$options = getopt('', array('version:', 'output:', 'phing-phar:', 'help'));

if (isset($options['help']) || !isset($options['version']) || $options['version'] === '') {
    fwrite(STDERR, "Usage: php -d phar.readonly=0 {$argv[0]} --version=VERSION [--output=PATH] [--phing-phar=PATH]\n");
    exit(isset($options['help']) ? 0 : 1);
}

$version = $options['version'];
$outputFile = isset($options['output'])
    ? $options['output']
    : $repoRoot . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'phar' . DIRECTORY_SEPARATOR . 'propel_generator-' . $version . '.phar';

$phingPhar = isset($options['phing-phar']) ? $options['phing-phar'] : null;
if ($phingPhar === null) {
    $phingPhar = getenv('PHING_PHAR') ?: '';
}
if ($phingPhar === '' || !is_file($phingPhar)) {
    $defaultPhingPhar = $repoRoot . '/test/tools/phing-phars/phing-2.17.4.phar';
    if (is_file($defaultPhingPhar)) {
        $phingPhar = $defaultPhingPhar;
    } else {
        fwrite(STDERR, "Phing PHAR not found. Pass --phing-phar= or set PHING_PHAR (see pear-package.yml).\n");
        exit(1);
    }
}

$requiredGeneratorPaths = array(
    'lib',
    'resources',
    'stubs',
    'build-propel.xml',
    'default.properties',
    'build.xml',
);

foreach ($requiredGeneratorPaths as $relativePath) {
    if (!file_exists($generatorRoot . DIRECTORY_SEPARATOR . $relativePath)) {
        fwrite(STDERR, "Missing generator file: {$relativePath}\n");
        exit(1);
    }
}

if (!is_dir($runtimeRoot . DIRECTORY_SEPARATOR . 'lib')) {
    fwrite(STDERR, "Missing runtime/lib directory.\n");
    exit(1);
}

$outputDir = dirname($outputFile);
if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDir}\n");
    exit(1);
}

if (file_exists($outputFile)) {
    unlink($outputFile);
}

$stagingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'propel-gen-phar-' . getmypid();
if (is_dir($stagingDir)) {
    removeDirectory($stagingDir);
}
mkdir($stagingDir, 0777, true);
mkdir($stagingDir . DIRECTORY_SEPARATOR . 'bin', 0777, true);
$generatorStagingDir = $stagingDir . DIRECTORY_SEPARATOR . 'generator';
mkdir($generatorStagingDir, 0777, true);

try {
    copyDirectory($generatorRoot . DIRECTORY_SEPARATOR . 'lib', $generatorStagingDir . DIRECTORY_SEPARATOR . 'lib');
    copyDirectory($generatorRoot . DIRECTORY_SEPARATOR . 'resources', $generatorStagingDir . DIRECTORY_SEPARATOR . 'resources');
    copyDirectory($generatorRoot . DIRECTORY_SEPARATOR . 'stubs', $generatorStagingDir . DIRECTORY_SEPARATOR . 'stubs');
    copyDirectory($runtimeRoot . DIRECTORY_SEPARATOR . 'lib', $stagingDir . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'lib');

    foreach (array('build-propel.xml', 'default.properties', 'build.xml') as $fileName) {
        copy($generatorRoot . DIRECTORY_SEPARATOR . $fileName, $generatorStagingDir . DIRECTORY_SEPARATOR . $fileName);
    }

    if (!copy($phingPhar, $stagingDir . DIRECTORY_SEPARATOR . 'phing.phar')) {
        throw new RuntimeException('Unable to copy Phing PHAR into staging directory.');
    }

    $phingStagingDir = $stagingDir . DIRECTORY_SEPARATOR . 'phing';
    mkdir($phingStagingDir, 0777, true);
    $phingArchive = new Phar($phingPhar);
    $phingArchive->extractTo($phingStagingDir, null, true);
    unlink($stagingDir . DIRECTORY_SEPARATOR . 'phing.phar');

    if (!copy(
        dirname(__FILE__) . DIRECTORY_SEPARATOR . 'phar' . DIRECTORY_SEPARATOR . 'propel_gen_stub.php',
        $stagingDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'stub.php'
    )) {
        throw new RuntimeException('Unable to copy propel-gen stub into staging directory.');
    }

    $phar = new Phar($outputFile);
    $phar->startBuffering();
    $phar->setStub(createPharStub($pharAlias));
    $phar->buildFromDirectory($stagingDir);
    $phar->setAlias($pharAlias);
    $phar->compressFiles(Phar::GZ);
    $phar->stopBuffering();

    echo 'Created ' . $outputFile . ' (' . formatBytes(filesize($outputFile)) . ')' . PHP_EOL;
} catch (Exception $e) {
    fwrite(STDERR, 'PHAR build failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    removeDirectory($stagingDir);
}

/**
 * @param string $alias
 *
 * @return string
 */
function createPharStub($alias)
{
    return "#!/usr/bin/env php\n<?php\nPhar::mapPhar('{$alias}');\nrequire 'phar://{$alias}/bin/stub.php';\n__HALT_COMPILER();";
}

/**
 * @param string $source
 * @param string $destination
 *
 * @return void
 */
function copyDirectory($source, $destination)
{
    if (!is_dir($source)) {
        throw new RuntimeException("Source directory does not exist: {$source}");
    }

    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        throw new RuntimeException("Unable to create directory: {$destination}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                throw new RuntimeException("Unable to create directory: {$target}");
            }
            continue;
        }

        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException("Unable to copy {$item->getPathname()} to {$target}");
        }
    }
}

/**
 * @param string $directory
 *
 * @return void
 */
function removeDirectory($directory)
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}

/**
 * @param int $bytes
 *
 * @return string
 */
function formatBytes($bytes)
{
    $units = array('B', 'KB', 'MB', 'GB');
    $value = (float) $bytes;
    $unit = 0;

    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        ++$unit;
    }

    return sprintf('%.1f %s', $value, $units[$unit]);
}
