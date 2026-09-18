<?php
/**
 * Build a WordPress.org-ready zip into releases/.
 *
 * Usage:
 *   php bin/build-zip.php
 *   php bin/build-zip.php --version=1.0.2
 *
 * From Docker (project root):
 *   docker compose exec -T php php /var/www/html/wp-content/plugins/delete-guard/bin/build-zip.php
 *
 * @package Delete_Guard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	if ( PHP_SAPI !== 'cli' ) {
		exit;
	}
}

// phpcs:disable WordPress.WP.AlternativeFunctions -- CLI build; WP_Filesystem is not bootstrapped.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI stdout/stderr.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- script-local CLI variables.

$delete_guard_plugin_root = dirname( __DIR__ );
$delete_guard_plugin_slug = 'delete-guard';

$delete_guard_version = null;
foreach ( $argv as $delete_guard_arg ) {
	if ( str_starts_with( $delete_guard_arg, '--version=' ) ) {
		$delete_guard_version = substr( $delete_guard_arg, 10 );
	}
}

if ( $delete_guard_version === null || $delete_guard_version === '' ) {
	$delete_guard_main = file_get_contents( $delete_guard_plugin_root . '/delete-guard.php' );
	if ( $delete_guard_main === false || ! preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $delete_guard_main, $delete_guard_m ) ) {
		fwrite( STDERR, "Cannot detect plugin version.\n" );
		exit( 1 );
	}
	$delete_guard_version = trim( $delete_guard_m[1] );
}

$delete_guard_version = preg_replace( '/[^0-9a-zA-Z.\-_]/', '', $delete_guard_version ) ?? '';
if ( $delete_guard_version === '' ) {
	fwrite( STDERR, "Invalid version.\n" );
	exit( 1 );
}

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "ZipArchive extension is required.\n" );
	exit( 1 );
}

$delete_guard_releases_dir = $delete_guard_plugin_root . '/releases';
if ( ! is_dir( $delete_guard_releases_dir ) && ! mkdir( $delete_guard_releases_dir, 0755, true ) && ! is_dir( $delete_guard_releases_dir ) ) {
	fwrite( STDERR, "Cannot create releases directory.\n" );
	exit( 1 );
}

$delete_guard_zip_name = "{$delete_guard_plugin_slug}-{$delete_guard_version}.zip";
$delete_guard_zip_path = $delete_guard_releases_dir . '/' . $delete_guard_zip_name;

$delete_guard_exclude_dir_names = [
	'.git'     => true,
	'releases' => true,
	'bin'      => true,
	'tests'    => true,
];

$delete_guard_exclude_file_names = [
	'.gitignore'     => true,
	'.gitattributes' => true,
	'.distignore'    => true,
	'.DS_Store'      => true,
];

if ( is_file( $delete_guard_zip_path ) ) {
	unlink( $delete_guard_zip_path );
}

$delete_guard_zip = new ZipArchive();
if ( $delete_guard_zip->open( $delete_guard_zip_path, ZipArchive::CREATE ) !== true ) {
	fwrite( STDERR, "Cannot create zip: {$delete_guard_zip_path}\n" );
	exit( 1 );
}

$delete_guard_iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $delete_guard_plugin_root, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

$delete_guard_added = 0;
/** @var SplFileInfo $delete_guard_file */
foreach ( $delete_guard_iterator as $delete_guard_file ) {
	$delete_guard_absolute = $delete_guard_file->getPathname();
	$delete_guard_relative = substr( $delete_guard_absolute, strlen( $delete_guard_plugin_root ) + 1 );
	$delete_guard_relative = str_replace( '\\', '/', $delete_guard_relative );

	$delete_guard_parts = explode( '/', $delete_guard_relative );
	if ( isset( $delete_guard_exclude_dir_names[ $delete_guard_parts[0] ] ) ) {
		continue;
	}

	$delete_guard_base = basename( $delete_guard_relative );
	if ( isset( $delete_guard_exclude_file_names[ $delete_guard_base ] ) ) {
		continue;
	}

	if ( $delete_guard_file->isDir() ) {
		$delete_guard_zip->addEmptyDir( $delete_guard_plugin_slug . '/' . $delete_guard_relative );
		continue;
	}

	if ( ! $delete_guard_file->isFile() ) {
		continue;
	}

	$delete_guard_zip->addFile( $delete_guard_absolute, $delete_guard_plugin_slug . '/' . $delete_guard_relative );
	$delete_guard_added++;
}

$delete_guard_zip->close();

if ( $delete_guard_added < 1 || ! is_file( $delete_guard_zip_path ) ) {
	fwrite( STDERR, "Zip build failed.\n" );
	exit( 1 );
}

$delete_guard_size = filesize( $delete_guard_zip_path );
echo "Created: {$delete_guard_zip_path}\n";
echo "Files: {$delete_guard_added}\n";
echo 'Size: ' . ( $delete_guard_size !== false ? (string) $delete_guard_size : '?' ) . " bytes\n";
exit( 0 );
