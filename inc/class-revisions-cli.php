<?php
/**
 * Revisions CLI
 *
 * @package trepmal/wp-revisions-cli
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- this is not a core file

/**
 * Manage revisions
 */
class Revisions_CLI extends WP_CLI_Command { // phpcs:ignore WordPressVIPMinimum.Classes.RestrictedExtendClasses.wp_cli

	/**
	 * Delete all revisions
	 *
	 * ## OPTIONS
	 *
	 * [--hard]
	 * : Hard delete. Slower, uses wp_delete_post_revision(). Alias to `wp revisions clean -1`
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions dump
	 */
	public function dump( $args = array(), $assoc_args = array() ) {

		global $wpdb;
		$revs = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'" );

		if ( $revs < 1 ) {
			WP_CLI::success( 'No revisions.' );
			return;
		}

		WP_CLI::confirm( sprintf( 'Remove all %d revisions?', $revs ), $assoc_args );

		if ( isset( $assoc_args['hard'] ) ) {
			WP_CLI::run_command( array( 'revisions', 'clean', -1 ), array( 'hard' => '' ) );
			return;
		}

		$wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'revision'" );

		// @todo: Are there caches to clear?

		WP_CLI::success( 'Finished removing all revisions.' );

	}

	/**
	 * List all revisions
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<post-type>]
	 * : List revisions for given post type(s).
	 *
	 * [--post_id=<post-id>]
	 * : List revisions for given post. Trumps --post_type.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to be included in the output.
	 * ---
	 * default: ID,post_title,post_parent
	 * ---
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * [--format=<format>]
	 * : Format to use for the output. One of table, csv or json.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions list
	 *     wp revisions list --post_id=2
	 *     wp revisions list --post_type=post,page
	 *
	 * @subcommand list
	 */
	public function list_( $args = array(), $assoc_args = array() ) {

		// Default fields to return.
		$fields = WP_CLI\Utils\get_flag_value(
			$assoc_args,
			'fields'
		);

		if ( is_string( $fields ) ) {
			$fields = wp_parse_list( $fields );
		}

		// Whitelist the fields we allow to avoid spurious queries.
		$allowed_fields = [
			'ID',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_content',
			'post_title',
			'post_excerpt',
			'post_status',
			'comment_status',
			'ping_status',
			'post_name',
			'post_modified',
			'post_modified_gmt',
			'post_password',
			'to_ping',
			'pinged',
			'post_content_filtered',
			'post_parent',
			'guid',
			'menu_order',
			'post_type',
			'post_mime_type',
			'comment_count',
		];

		// Don't allow fields that aren't in the above whitelist.
		// Note: we do not use array_filter to remove empty elements (in
		// case of an empty `--fields` flag). This way the error message
		// will still be triggered instead of running invalid SQL
		$excluded_fields = array_diff( $fields, $allowed_fields );

		if ( ! empty( $excluded_fields ) ) {
			WP_CLI::error( 'Invalid values provided in the fields argument.' );
		}

		$fields = array_map( 'esc_sql',	$fields	);
		$fields = implode( ',', $fields );

		global $wpdb;
		if ( ! empty( $assoc_args['post_id'] ) ) {

			$revs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT SQL_CALC_FOUND_ROWS {$fields} FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent = %d",
					$assoc_args['post_id']
				)
			);

		} else if ( ! empty( $assoc_args['post_type'] ) ) {

			$post_types = array_map( function( $i ) {
				return sprintf( "'%s'", esc_sql( $i ) );
			}, wp_parse_slug_list( $assoc_args['post_type'] ) );
			$where = sprintf( 'AND post_type IN ( %s )', implode( ',', $post_types ) );

			// get all IDs for posts in given post type(s).
			$ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=2 {$where}" );

			// Prepare the IDs for inclusion in the query.
			$post__in = array_map( 'esc_sql', $ids );
			$post__in = implode( ',', $ids );

			// get revisions of those IDs.
			$revs = $wpdb->get_results(
				"SELECT SQL_CALC_FOUND_ROWS {$fields} FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent IN ({$post__in}) ORDER BY post_parent DESC"
			);

		} else {

			$revs = $wpdb->get_results(
				"SELECT SQL_CALC_FOUND_ROWS {$fields} FROM $wpdb->posts WHERE post_type = 'revision' ORDER BY post_parent DESC"
			);

		}

		$total = $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		if ( $total > 100 ) {
			WP_CLI::confirm( sprintf( 'List all %d revisions?', $total ), $assoc_args );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'ID', 'post_parent', 'post_title' ), 'revisions' );
		$formatter->display_items( $revs );

	}

	/**
	 * Get revision status
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions status
	 */
	public function status( $args = array(), $assoc_args = array() ) {

		if ( defined( 'WP_POST_REVISIONS' ) ) {
			if ( true === WP_POST_REVISIONS ) {
				WP_CLI::success( 'WP_POST_REVISIONS is true. Keeps all revisions.' );
			} else {
				WP_CLI::success( sprintf( 'Keeping the last %d revisions', WP_POST_REVISIONS ) );
			}
		} else {
			WP_CLI::success( 'WP_POST_REVISIONS is undefined.' );
		}

	}

	/**
	 * Delete old revisions
	 *
	 * ## OPTIONS
	 *
	 * [<keep>]
	 * : Number of revisions to keep per post. Defaults to WP_POST_REVISIONS if it is an integer.
	 *
	 * [--filter-keep]
	 * : Allow `wp_revisions_to_keep` filter to override keep number.
	 *
	 * [--post_type=<post-type>]
	 * : Clean revisions for given post type(s). Default: any
	 *
	 * [--after-date=<yyyy-mm-dd>]
	 * : Clean revisions published on or after this date. Default: none.
	 *
	 * [--before-date=<yyyy-mm-dd>]
	 * : Clean revisions published on or before this date. Default: none.
	 *
	 * [--post_id=<post-id>]
	 * : Clean revisions for given post.
	 *
	 * [--hard]
	 * : Hard delete. Slower, uses wp_delete_post_revision().
	 *
	 * [--dry-run]
	 * : Dry run, just a test, no actual cleaning done.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions clean
	 *     wp revisions clean 5
	 *     wp revisions clean --post_id=2
	 *     wp revisions clean 5 --post_type=post,page
	 *     wp revisions clean --after-date=2015-11-01 --before-date=2015-12-30
	 *     wp revisions clean --after-date=2015-11-01 --before-date=2015-12-30 --dry-run
	 */
	public function clean( $args = array(), $assoc_args = array() ) {

		global $wpdb;

		if ( ! empty( $assoc_args['post_id'] ) ) {

			$posts = array( $assoc_args['post_id'] );
			$posts = array_map( 'absint', $posts );

		} else {

			if ( empty( $assoc_args['post_type'] ) ) {
				$post_types = $this->supports_revisions();
			} else {
				$post_types = wp_parse_slug_list( $assoc_args['post_type'] );
			}

			$post_types = array_map( function( $i ) {
				return sprintf( "'%s'", esc_sql( $i ) );
			}, $post_types );
			$where = sprintf( 'AND post_type IN ( %s )', implode( ',', $post_types ) );

			// verify dates
			if ( isset( $assoc_args['after-date'] ) || isset( $assoc_args['before-date'] ) ) {

				$strto_aft = isset( $assoc_args['after-date'] ) ? strtotime( $assoc_args['after-date'] ) : false;
				$strto_bef = isset( $assoc_args['before-date'] ) ? strtotime( $assoc_args['before-date'] ) : false;

				$aft_date = $strto_aft ? date( 'Y-m-d', $strto_aft ) : false;
				$bef_date = $strto_bef ? date( 'Y-m-d', $strto_bef ) : false;

				if ( $aft_date && $bef_date ) {
					$where .= $wpdb->prepare( ' AND (post_date < %s AND post_date > %s)', $bef_date, $aft_date );
				} else if ( $aft_date ) {
					$where .= $wpdb->prepare( ' AND post_date > %s', $aft_date );
				} else if ( $bef_date ) {
					$where .= $wpdb->prepare( ' AND post_date < %s', $bef_date );
				}

				if (
					( isset( $assoc_args['after-date'] ) && ! $aft_date ) ||
					( isset( $assoc_args['before-date'] ) && ! $bef_date )
				) {
					WP_CLI::log( 'Invalid date provided' );
					WP_CLI::log( 'Date: Given -> Computed' );
					if ( isset( $assoc_args['after-date'] ) ) {
						WP_CLI::log( sprintf( 'After: %s -> %s', $assoc_args['after-date'], $aft_date ?: 'none' ) );
					}
					if ( isset( $assoc_args['before-date'] ) ) {
						WP_CLI::log( sprintf( 'Before: %s -> %s', $assoc_args['before-date'], $bef_date ?: 'none' ) );
					}
					WP_CLI::confirm( 'Proceed?' );
				}
			}

			// get all IDs for posts in given post type(s).
			$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=1 {$where}" );

		}

		$total = count( $posts );

		$notify = \WP_CLI\Utils\make_progress_bar( sprintf( 'Cleaning revisions for %d post(s)', $total ), $total );

		if ( isset( $args[0] ) ) {
			$keep = intval( $args[0] );
		} else if ( true === WP_POST_REVISIONS ) {
			WP_CLI::error( 'WP_POST_REVISIONS is set to true (keeps all revisions). Please pass a number.' );
		} else {
			$keep = WP_POST_REVISIONS;
		}
		$keep = absint( $keep );

		$total_deleted = 0;

		$this->start_bulk_operation();

		foreach ( $posts as $post_id ) {

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_children
			$revisions = get_children( array(
				'order'                  => 'DESC',
				'orderby'                => 'date ID',
				'post_parent'            => $post_id,
				'post_type'              => 'revision',
				'post_status'            => 'inherit',
				// trust me on these.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			) );

			if ( ! $revisions ) {
				$notify->tick();
				continue;
			}

			if ( isset( $assoc_args['filter-keep'] ) ) {
				$keep = wp_revisions_to_keep( get_post( $post_id ) );
			}

			$count = count( $revisions );

			if ( $count > $keep ) {
				if ( $keep > 0 ) {
					$revisions = array_slice( $revisions, $keep, null, true );
				}

				$total_deleted += count( $revisions );

				if ( isset( $assoc_args['hard'] ) ) {
					foreach ( $revisions as $id ) {
						if ( empty( $assoc_args['dry-run'] ) ) {
							wp_delete_post_revision( $id );
						}
					}
				} else {
					$delete_ids = implode( ',', $revisions );
					if ( empty( $assoc_args['dry-run'] ) ) {
						$wpdb->query( "DELETE FROM $wpdb->posts WHERE ID IN ($delete_ids)" );
					}
				}
			}

			$wpdb->flush();

			$notify->tick();
		}

		$this->end_bulk_operation();

		$notify->finish();

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			WP_CLI::success( sprintf( 'Dry Run: Will remove %d old revisions.', $total_deleted ) );
		} else {
			WP_CLI::success( sprintf( 'Finished removing %d old revisions.', $total_deleted ) );
		}

	}

	/**
	 * Generate revisions
	 *
	 * ## OPTIONS
	 *
	 * [<count>]
	 * : Number of revisions to generate per post. Default 15
	 *
	 * [--post_type=<post-type>]
	 * : Generate revisions for given post type(s). Default any
	 *
	 * [--post_id=<post-id>]
	 * : Generate revisions for given post.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions generate 10
	 *     wp revisions generate --post_id=2
	 *     wp revisions generate 2 --post_type=post,page
	 */
	public function generate( $args = array(), $assoc_args = array() ) {

		global $wpdb;

		if ( ! empty( $assoc_args['post_id'] ) ) {

			$posts = array( $assoc_args['post_id'] );
			$posts = array_filter( $posts, 'get_post' );

		} else {

			if ( empty( $assoc_args['post_type'] ) ) {
				$post_types = $this->supports_revisions();
			} else {
				$post_types = explode( ',', $assoc_args['post_type'] );
			}

			$where = '';
			foreach ( $post_types as $post_type ) {
				$where .= $wpdb->prepare( ' OR post_type = %s', $post_type );
			}

			// get all IDs for posts in given post type(s).
			$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=2 {$where}" );

		}

		$total = count( $posts );

		$notify = \WP_CLI\Utils\make_progress_bar( sprintf( 'Generating revisions for %d post(s)', $total ), $total );

		$count = isset( $args[0] ) ? intval( $args[0] ) : 15;

		$this->start_bulk_operation();

		remove_all_filters( 'wp_revisions_to_keep' );
		add_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );
		$inc = 0;
		foreach ( $posts as $post_id ) {
			$notify->tick();

			for ( $i = 0; $i < $count; $i++ ) {

				wp_save_post_revision( $post_id );
			}
			$inc++;
			if ( $inc % 10 === 0 ) {
				$this->stop_the_insanity();
			}
		}

		$this->end_bulk_operation();

		$notify->finish();
		WP_CLI::success( 'Finished generating revisions.' );

	}

	/**
	 * Supports Revisions
	 *
	 * Get list of post types that support revisions
	 *
	 * @return array
	 */
	private function supports_revisions() {

		$supports_revisions = array();

		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'revisions' ) ) {
				$supports_revisions[] = $post_type;
			}
		}

		return $supports_revisions;

	}

	/*
	 *  Clear all of the caches for memory management
	 */
	protected function stop_the_insanity() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

		if ( !is_object( $wp_object_cache ) )
			return;

		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();

		if ( is_callable( $wp_object_cache, '__remoteset' ) )
			$wp_object_cache->__remoteset(); // important
	}

	/**
	 * Disable term counting so that terms are not all recounted after every term operation
	 */
	protected function start_bulk_operation() {
		// Disable term count updates for speed
		wp_defer_term_counting( true );
	}

	/**
	 * Re-enable Term counting and trigger a term counting operation to update all term counts
	 */
	protected function end_bulk_operation() {
		// This will also trigger a term count.
		wp_defer_term_counting( false );
	}
}
