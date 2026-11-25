<?php

declare(strict_types=1);

$root = dirname(__DIR__);

/**
 * Runs a checked command without shell interpolation and captures its output.
 * シェル展開を使わずコマンドを検証実行し、出力を取得します。
 *
 * @param list<string> $arguments
 * @param string $directory
 * @return string
 */
$run = static function (array $arguments, string $directory): string {
    $stdout = tmpfile();
    $stderr = tmpfile();
    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Could not create command output files.');
    }
    try {
        $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => $stdout, 2 => $stderr], $pipes, $directory);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start distribution check.');
        }
        fclose($pipes[0]);
        $status = proc_close($process);
        rewind($stdout);
        rewind($stderr);
        $output = stream_get_contents($stdout);
        $errors = stream_get_contents($stderr);
        if ($status !== 0) {
            throw new RuntimeException(implode(' ', $arguments) . " failed:\n" . $output . $errors);
        }
        return $output;
    } finally {
        fclose($stdout);
        fclose($stderr);
    }
};

/**
 * Removes only the temporary tree created for this isolated consumer.
 * この検証で作成した一時利用先のツリーだけを除去します。
 *
 * @param string $directory
 * @return void
 */
$remove = static function (string $directory) use (&$remove): void {
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isDir() && !$entry->isLink()) {
            $remove($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
};

$temporary = sys_get_temp_dir() . '/catenary-distribution-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
try {
    $archive = $temporary . '/package.tar';
    $run(['git', 'archive', '--format=tar', '--output=' . $archive, 'HEAD'], $root);
    $files = explode("\n", trim($run(['tar', '-tf', $archive], $root)));
    $required = ['composer.json', 'LICENSE', 'README.md', 'README.en.md', 'README.ja.md',
        'src/Vec.php', 'src/Option.php', 'src/Dict.php', 'src/Stream.php',
        'docs/contracts.md', 'docs/migration-to-catenary.md', 'benchmarks/README.md', 'examples/basic.php'];
    foreach ($required as $file) {
        if (!in_array($file, $files, true)) {
            throw new RuntimeException('Distribution is missing: ' . $file);
        }
    }
    foreach ($files as $file) {
        $excluded = '~^(?:tests/|tools/|vendor/|\.github/|AGENTS\.md$|composer\.lock$|benchmarks/run\.php$)~';
        if (preg_match($excluded, $file)) {
            throw new RuntimeException('Development-only file entered the distribution: ' . $file);
        }
    }
    $package = $temporary . '/package';
    $consumer = $temporary . '/consumer';
    mkdir($package);
    mkdir($consumer);
    $run(['tar', '-xf', $archive, '-C', $package], $root);
    $metadata = json_decode(file_get_contents($package . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($metadata['name'] !== 'catenary/collections' || $metadata['autoload']['psr-4'] !== ['Catenary\\' => 'src/']) {
        throw new RuntimeException('The distribution has an unexpected package name or autoload namespace.');
    }
    file_put_contents($consumer . '/composer.json', json_encode([
        'name' => 'catenary/distribution-consumer',
        'repositories' => [['type' => 'path', 'url' => $package,
            'options' => ['symlink' => false, 'versions' => ['catenary/collections' => 'dev-main']]],
            ['packagist.org' => false]],
        'require' => ['catenary/collections' => 'dev-main'],
        'config' => ['allow-plugins' => false],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $run(['composer', 'install', '--no-dev', '--no-plugins', '--no-scripts', '--no-interaction'], $consumer);
    $installed = $consumer . '/vendor/catenary/collections';
    if (is_link($installed) || is_dir($installed . '/tests')) {
        throw new RuntimeException('The consumer must install a copied production distribution.');
    }
    $run([PHP_BINARY, $installed . '/examples/basic.php', $consumer . '/vendor/autoload.php'], $consumer);
    echo "Distribution archive, retained guides and isolated production consumer passed.\n";
} finally {
    $remove($temporary);
}
