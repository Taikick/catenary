<?php

/**
 * Verifies that invalid consumer programs are rejected by both analyzers.
 * 不正な利用側コードを両解析器が拒否することを検証します。
 */

declare(strict_types=1);

/**
 * Runs an analyzer without shell interpolation and returns its diagnostics.
 * シェル展開を使わず解析器を実行し、診断結果を返します。
 *
 * @param list<string> $arguments
 * @return array{int, string, string}
 */

$runAnalyzer = static function (array $arguments): array {
    $process = proc_open(
        [PHP_BINARY, ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the type analyzer.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
};

$fixture = dirname(__DIR__) . '/tests/Types/invalid.php';
$expected = [];
foreach (file($fixture) as $index => $line) {
    if (str_contains($line, '// @expect-type-error')) {
        $expected[] = $index + 1;
    }
}

$commands = [
    'PHPStan' => [
        'vendor/bin/phpstan', 'analyse', 'src', 'tests/Types/invalid.php',
        '--no-progress', '--error-format=json', '--memory-limit=512M',
    ],
    'Psalm' => [
        'vendor/bin/psalm', '--config=psalm-negative.xml', '--no-cache',
        '--output-format=json', '--no-progress', '--threads=1', '--show-info=false',
    ],
];
foreach ($commands as $name => $arguments) {
    [$code, $stdout, $stderr] = $runAnalyzer($arguments);
    try {
        $diagnostics = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        fwrite(STDERR, "$name returned invalid diagnostics (exit $code):\n$stdout\n$stderr\n");
        exit(1);
    }
    $actual = [];
    $unexpected = [];
    if ($name === 'PHPStan') {
        $unexpected = $diagnostics['errors'];
        foreach ($diagnostics['files'] as $filename => $details) {
            foreach ($details['messages'] as $message) {
                if ($filename === $fixture && in_array($message['line'], $expected, true)) {
                    if ($message['identifier'] === 'argument.type') {
                        $actual[] = $message['line'];
                    } elseif ($message['identifier'] !== 'argument.templateType') {
                        $unexpected[] = $message;
                    }
                } else {
                    $unexpected[] = $message;
                }
            }
        }
    } else {
        foreach ($diagnostics as $message) {
            if ($message['severity'] !== 'error') {
                continue;
            }
            if (
                $message['file_path'] === $fixture && in_array($message['line_from'], $expected, true)
                && in_array($message['type'], ['InvalidArgument', 'InvalidScalarArgument'], true)
            ) {
                $actual[] = $message['line_from'];
            } else {
                $unexpected[] = $message;
            }
        }
    }
    $actual = array_values(array_unique($actual));
    sort($actual);
    if (!in_array($code, [1, 2], true) || $actual !== $expected || $unexpected !== []) {
        fwrite(STDERR, "$name did not reject exactly the expected type violations.\n$stdout\n$stderr\n");
        exit(1);
    }
    echo "$name rejected all " . count($expected) . " invalid programs.\n";
}
