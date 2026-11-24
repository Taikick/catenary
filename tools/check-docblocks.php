<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$finder = new NodeFinder();
$failures = [];
$checked = 0;

foreach (['src', 'tests', 'tools', 'benchmarks', 'examples'] as $directory) {
    if (!is_dir($root . '/' . $directory)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory));
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/vendor/')) {
            continue;
        }
        $nodes = $parser->parse(file_get_contents($file->getPathname()));
        $declarations = $finder->find($nodes ?? [], static fn (Node $node): bool =>
            $node instanceof ClassLike || $node instanceof ClassMethod || $node instanceof Function_);
        foreach ($declarations as $declaration) {
            ++$checked;
            $comment = $declaration->getDocComment()?->getText() ?? '';
            $description = preg_replace('/^\s*\*\s*@.*$/mu', '', $comment);
            if (!preg_match('/[A-Za-z]{3,}/u', $description) || !preg_match('/[ぁ-んァ-ヶ一-龠]/u', $description)) {
                $failures[] = substr($file->getPathname(), strlen($root) + 1) . ':' . $declaration->getStartLine();
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Missing English/Japanese class or method documentation:\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "Bilingual documentation verified for {$checked} declarations.\n";
