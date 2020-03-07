<?php
/**
 * Revisions CLI
 *
 * @package trepmal/wp-revisions-cli
 */


if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! class_exists( 'Revisions_CLI' ) ) {
	require __DIR__ . '/inc/class-revisions-cli.php';
}

WP_CLI::add_command( 'revisions', 'Revisions_CLI' );
