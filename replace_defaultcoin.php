<?php
$root = __DIR__;
$extensions = ['blade.php', 'php', 'json'];
$skip = [$root . DIRECTORY_SEPARATOR . 'vendor', $root . DIRECTORY_SEPARATOR . 'storage'];

$count = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

foreach ($it as $file) {
    $path = $file->getPathname();

    // Skip vendor and storage
    foreach ($skip as $s) {
        if (strpos($path, $s) === 0) continue 2;
    }

    // Check extension
    $ext = $file->getExtension();
    $basename = $file->getBasename();

    // Match .blade.php and .php and .json
    $matched = false;
    if ($ext === 'json') $matched = true;
    if ($ext === 'php') $matched = true;   // catches both .php and .blade.php

    if (!$matched) continue;

    // Skip self
    if ($basename === 'replace_defaultcoin.php' || $basename === 'replace_defaultcoin.ps1') continue;

    $original = file_get_contents($path);
    // Case-insensitive replace: "Default Coin" / "default coin" / "Default coin" etc → OBXCoin
    $replaced = preg_replace('/Default Coin/i', 'OBXCoin', $original);

    if ($replaced !== $original) {
        file_put_contents($path, $replaced);
        echo "Updated: $path\n";
        $count++;
    }
}

echo "\n--- Done. $count files updated.\n";
