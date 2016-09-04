<?php
/*
 * Plugin Name: Revisions CLI
 * Plugin URI: https://github.com/trepmal/wp-revisions-cli/
 * Description: Plugin wrapper for WP CLI command, WP Revisions CLI
 * Version: 0.1.0
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: MIT
 * TextDomain:
 * DomainPath:
 * Network:
 */


if ( defined('WP_CLI') && WP_CLI ) {
	include plugin_dir_path( __FILE__ ) . '/revisions-cli.php';
}