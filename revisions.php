<?php
/*
 * Plugin Name: Revisions CLI
 * Plugin URI: trepmal.com
 * Description:
 * Version: 99
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * TextDomain:
 * DomainPath:
 * Network:
 */


if ( defined('WP_CLI') && WP_CLI ) {
	include plugin_dir_path( __FILE__ ) . '/revisions-cli.php';
}