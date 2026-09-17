<?php
/**
 * Build a WordPress.org-ready zip into releases/.
 *
 * Usage:
 *   php bin/build-zip.php
 *   php bin/build-zip.php --version=1.0.0
 *
 * From Docker (project root):
 *   docker compose exec -T php php /var/www/html/wp-content/plugins/delete-guard/bin/build-zip.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

$plugin_root = dirname(__DIR__);
$plugin_slug = 'delete-guard';

$version = null;
foreach ($argv as $arg) {
	if (str_starts_with($arg, '--version=')) {
		$version = substr($arg, 10);
	}
}

if ($version === null || $version === '') {
	$main = file_get_contents($plugin_root . '/delete-guard.php');
	if ($main === false || !preg_match('/^\s*\*\s*Version:\s*(.+)$/mi', $main, $m)) {
		fwrite(STDERR, "Cannot detect plugin version.\n");
		exit(1);
	}
	$version = trim($m[1]);
}

$version = preg_replace('/[^0-9a-zA-Z.\-_]/', '', $version) ?? '';
if ($version === '') {
	fwrite(STDERR, "Invalid version.\n");
	exit(1);
}

if (!class_exists('ZipArchive')) {
	fwrite(STDERR, "ZipArchive extension is required.\n");
	exit(1);
}

$releases_dir = $plugin_root . '/releases';
if (!is_dir($releases_dir) && !mkdir($releases_dir, 0755, true) && !is_dir($releases_dir)) {
	fwrite(STDERR, "Cannot create releases directory.\n");
	exit(1);
}

$zip_name = "{$plugin_slug}-{$version}.zip";
$zip_path = $releases_dir . '/' . $zip_name;

$exclude_dir_names = [
	'.git' => true,
	'releases' => true,
	'bin' => true,
	'tests' => true,
];

$exclude_file_names = [
	'.gitignore' => true,
	'.gitattributes' => true,
	'.DS_Store' => true,
];

$exclude_path_prefixes = [];

if (is_file($zip_path)) {
	unlink($zip_path);
}

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
	fwrite(STDERR, "Cannot create zip: {$zip_path}\n");
	exit(1);
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($plugin_root, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::SELF_FIRST
);

$added = 0;
/** @var SplFileInfo $file */
foreach ($iterator as $file) {
	$absolute = $file->getPathname();
	$relative = substr($absolute, strlen($plugin_root) + 1);
	$relative = str_replace('\\', '/', $relative);

	$parts = explode('/', $relative);
	if (isset($exclude_dir_names[$parts[0]])) {
		continue;
	}

	$base = basename($relative);
	if (isset($exclude_file_names[$base])) {
		continue;
	}

	foreach ($exclude_path_prefixes as $prefix) {
		if (str_starts_with($relative, $prefix)) {
			continue 2;
		}
	}

	if ($file->isDir()) {
		$zip->addEmptyDir($plugin_slug . '/' . $relative);
		continue;
	}

	if (!$file->isFile()) {
		continue;
	}

	$zip->addFile($absolute, $plugin_slug . '/' . $relative);
	$added++;
}

$zip->close();

if ($added < 1 || !is_file($zip_path)) {
	fwrite(STDERR, "Zip build failed.\n");
	exit(1);
}

$size = filesize($zip_path);
echo "Created: {$zip_path}\n";
echo "Files: {$added}\n";
echo 'Size: ' . ($size !== false ? (string) $size : '?') . " bytes\n";
exit(0);
