#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$fix = in_array('--fix', $argv, true);
$errors = [];
$files = [];
$scopes = ['bin', 'src', 'tests'];

foreach ($scopes as $scope) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $scope, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files, SORT_STRING);
foreach ($files as $file) {
    $source = (string) file_get_contents($file);
    $relative = substr($file, strlen($root) + 1);
    $normalized = str_replace("\r\n", "\n", $source);
    $normalized = preg_replace('/[ \t]+$/m', '', $normalized) ?? $normalized;
    $normalized = rtrim($normalized, "\n") . "\n";

    if ($fix && $source !== $normalized) {
        file_put_contents($file, $normalized);
        $source = $normalized;
    } elseif ($source !== $normalized) {
        $errors[] = $relative . ': run composer quality-fix (line endings/trailing whitespace).';
    }

    if (!str_starts_with($source, '<?php') && !str_starts_with($source, "#!/usr/bin/env php\n<?php")) {
        $errors[] = $relative . ': PHP files must begin with <?php or the PHP CLI shebang.';
    }

    $tokens = token_get_all($source);
    $functionLine = null;
    $braceDepth = 0;
    $functionDepth = null;
    $currentLine = 1;
    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] === T_FUNCTION) {
            $functionLine = $token[2];
        } elseif ($token === '{') {
            $braceDepth++;
            if ($functionLine !== null && $functionDepth === null) {
                $functionDepth = $braceDepth;
            }
        } elseif ($token === '}') {
            if ($functionDepth === $braceDepth && $functionLine !== null) {
                if (($currentLine - $functionLine) > 180) {
                    $errors[] = $relative . ':' . $functionLine . ': function exceeds the 180-line release-boundary limit.';
                }
                $functionLine = null;
                $functionDepth = null;
            }
            $braceDepth--;
        }
        if (is_array($token)) {
            $currentLine += substr_count($token[1], "\n");
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo 'Quality gate passed for ' . count($files) . " release-boundary PHP files.\n";
