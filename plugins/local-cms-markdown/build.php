<?php
declare(strict_types=1);

/**
 * Build script for the Local CMS Markdown plugin.
 *
 *   php plugins/local-cms-markdown/build.php
 *
 * 1. Syncs the canonical Markdown engine (public/assets/convert.js + markdown.css)
 *    into this plugin's assets/ directory, so there is a single source of truth.
 * 2. Produces a plug-and-play zip at _export-plugins/local-cms-markdown.zip ready
 *    to upload via WordPress -> Plugins -> Add New -> Upload Plugin.
 */

$pluginDir = __DIR__;
$repoRoot  = dirname($pluginDir, 2);
$slug      = basename($pluginDir);

$assetSources = array(
    'convert.js'   => $repoRoot . '/public/assets/convert.js',
    'markdown.css' => $repoRoot . '/public/assets/markdown.css',
);

echo "Syncing canonical assets...\n";

foreach ($assetSources as $name => $source) {
    if (!is_file($source)) {
        fwrite(STDERR, "  ! missing source: {$source}\n");
        exit(1);
    }

    $target = $pluginDir . '/assets/' . $name;

    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0775, true);
    }

    if (copy($source, $target)) {
        echo "  synced assets/{$name}\n";
    } else {
        fwrite(STDERR, "  ! failed to copy {$name}\n");
        exit(1);
    }
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension unavailable; assets synced but zip skipped.\n");
    exit(0);
}

$exportDir = $repoRoot . '/_export-plugins';

if (!is_dir($exportDir)) {
    mkdir($exportDir, 0775, true);
}

$zipPath = $exportDir . '/' . $slug . '.zip';

if (is_file($zipPath)) {
    unlink($zipPath);
}

// Files to ship in the distributable plugin (exclude dev-only build.php).
$included = array(
    'local-cms-markdown.php',
    'readme.txt',
    'README.md',
    'assets/convert.js',
    'assets/markdown.css',
);

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "  ! could not open {$zipPath} for writing\n");
    exit(1);
}

echo "Building {$zipPath}...\n";

foreach ($included as $relative) {
    $absolute = $pluginDir . '/' . $relative;

    if (!is_file($absolute)) {
        continue;
    }

    // Files sit at the archive root; extract straight into wp-content/plugins/<slug>/.
    $zip->addFile($absolute, $relative);
    echo "  + {$relative}\n";
}

$zip->close();

echo "Done. Extract {$zipPath} into wp-content/plugins/{$slug}/.\n";
