<?php

/**
 * Entry point bundled inside propel_generator-*.phar.
 *
 * Mirrors generator/bin/propel-gen: delegates to the bundled Phing and build.xml.
 * Extracts to a temp cache because Phing 2 cannot load build files from phar:// URLs.
 */

if (!class_exists('Phar', false)) {
    fwrite(STDERR, "The PHP phar extension is required to run propel-gen.\n");
    exit(1);
}

$pharPath = Phar::running(false);
if ($pharPath === '' || !is_file($pharPath)) {
    fwrite(STDERR, "Unable to resolve propel-gen PHAR path.\n");
    exit(1);
}

$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'propel-gen-' . md5_file($pharPath);
$readyMarker = $cacheDir . DIRECTORY_SEPARATOR . '.ready';

if (!is_file($readyMarker)) {
    if (is_dir($cacheDir)) {
        removeExtractedTree($cacheDir);
    }

    if (!mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
        fwrite(STDERR, "Unable to create cache directory: {$cacheDir}\n");
        exit(1);
    }

    $phar = new Phar($pharPath);
    $phar->extractTo($cacheDir, null, true);
    file_put_contents($readyMarker, date('c') . PHP_EOL);
}

$buildFile = $cacheDir . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR . 'build.xml';
$phingEntry = $cacheDir . DIRECTORY_SEPARATOR . 'phing' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phing.php';

if (!is_file($buildFile) || !is_file($phingEntry)) {
    fwrite(STDERR, "propel-gen cache is incomplete. Remove {$cacheDir} and retry.\n");
    exit(1);
}

$args = array_slice($_SERVER['argv'], 1);
$phingArgs = array('-f', $buildFile, '-Dusing.propel-gen=true');

if (count($args) <= 1) {
    $phingArgs[] = '-Dproject.dir=' . getcwd();
    if (count($args) === 1) {
        $phingArgs[] = $args[0];
    }
} else {
    $phingArgs[] = '-Dproject.dir=' . $args[0];
    $phingArgs = array_merge($phingArgs, array_slice($args, 1));
}

$argv = array_merge(array('propel-gen'), $phingArgs);
$_SERVER['argv'] = $argv;
$_SERVER['argc'] = count($argv);

if (PHP_VERSION_ID >= 80400) {
    ini_set('error_reporting', (string) (E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING));
}

require $phingEntry;

/**
 * @param string $directory
 *
 * @return void
 */
function removeExtractedTree($directory)
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
