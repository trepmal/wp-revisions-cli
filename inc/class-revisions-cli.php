<?php
/**
 * Revisions CLI
 *
 * @package trepmal/wp-revisions-cli
 */

/**
 * Manage revisions
 */
class Revisions_CLI extends WP_CLI_Command {

	/**
	 * Delete all revisions
	 *
	 * ## OPTIONS
	 *
	 * [--hard]
	 * : Hard delete. Slower, uses wp_delete_post_revision() to handle additional meta, caches, or other actions. Alias to `wp revisions clean -1 --hard`
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions dump
	 */
	public function dump( $args = array(), $assoc_args = array() ) {

		$hard = WP_CLI\Utils\get_flag_value( $assoc_args, 'hard', false );

		global $wpdb;
		$revs = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'" );

		if ( $revs < 1 ) {
			WP_CLI::success( 'No revisions.' );
			return;
		}

		WP_CLI::confirm( sprintf( 'Remove all %d revisions?', $revs ), $assoc_args );

		if ( $hard ) {
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

		$post_type = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_type', false );
		$post_id   = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_id', false );

		global $wpdb;
		if ( $post_id ) {

			$revs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent = %d",
					$post_id
				)
			);

		} elseif ( $post_type ) {

			$post_types = array_map(
				function ( $i ) {
					return sprintf( "'%s'", esc_sql( $i ) );
				},
				wp_parse_slug_list( $post_type )
			);
			$where      = sprintf( 'AND post_type IN ( %s )', implode( ',', $post_types ) );
			// get all IDs for posts in given post type(s).
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN statement
			$ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=1 {$where}" );

			if ( $ids ) {
				// Prepare the IDs for inclusion in the query.
				$post__in = array_map( 'esc_sql', $ids );
				$post__in = implode( ',', $ids );

				// get revisions of those IDs.
				$revs = $wpdb->get_results(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN statement
					"SELECT * FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent IN ({$post__in}) ORDER BY post_parent, post_date, ID DESC"
				);
			} else {
				$revs = [];
			}
		} else {

			$revs = $wpdb->get_results(
				"SELECT * FROM $wpdb->posts WHERE post_type = 'revision' ORDER BY post_parent, post_date, ID DESC"
			);

		}

		$total = $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		$format_is_count = isset( $assoc_args['format'] ) && 'count' === $assoc_args['format'];
		if ( ! $format_is_count && $total > 100 ) {
			WP_CLI::confirm( sprintf( 'List all %d revisions?', $total ), $assoc_args );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'ID', 'post_parent', 'post_title' ), 'revisions' );
		$formatter->display_items( $revs );
	}

	/**
	 * Get WP_POST_REVISIONS value
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
	 * : Clean revisions on posts published on or after this date (GMT). Default: none.
	 *
	 * [--before-date=<yyyy-mm-dd>]
	 * : Clean revisions on posts published before this date (GMT). Default: none.
	 *
	 * [--post_id=<post-id>]
	 * : Clean revisions for given post.
	 *
	 * [--hard]
	 * : Hard delete. Slower, uses wp_delete_post_revision() to handle additional meta, caches, or other actions.
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

		$keep = WP_CLI\Utils\get_flag_value( $args, 0, false );
		if ( $keep ) {
			$keep = absint( $keep );
		} elseif ( true === WP_POST_REVISIONS ) {
			WP_CLI::error( 'WP_POST_REVISIONS is set to true (keeps all revisions). Please pass a number.' );
		} else {
			$keep = absint( WP_POST_REVISIONS );
		}

		$filter_keep = WP_CLI\Utils\get_flag_value( $assoc_args, 'filter-keep', false );
		$post_type   = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_type', false );
		$post_id     = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_id', false );
		$after_date  = WP_CLI\Utils\get_flag_value( $assoc_args, 'after-date', false );
		$before_date = WP_CLI\Utils\get_flag_value( $assoc_args, 'before-date', false );
		$hard        = WP_CLI\Utils\get_flag_value( $assoc_args, 'hard', false );
		$dry_run     = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		global $wpdb;

		if ( $post_id ) {

			$posts = array( $post_id );
			$posts = array_map( 'absint', $posts );

		} else {

			if ( ! $post_type ) {
				$post_types = $this->supports_revisions();
			} else {
				$post_types = wp_parse_slug_list( $post_type );
			}

			$post_types = array_map(
				function ( $i ) {
					return sprintf( "'%s'", esc_sql( $i ) );
				},
				$post_types
			);
			$where      = sprintf( 'AND post_type IN ( %s )', implode( ',', $post_types ) );

			// verify dates
			if ( $after_date || $before_date ) {

				$strto_aft = $after_date ? strtotime( $after_date ) : false;
				$strto_bef = $before_date ? strtotime( $before_date ) : false;

				// use wp_date to get local time, since we query against post_date column
				$after_ymd  = $strto_aft ? wp_date( 'Y-m-d', $strto_aft ) : false;
				$before_ymd = $strto_bef ? wp_date( 'Y-m-d', $strto_bef ) : false;

				if ( $after_ymd && $before_ymd ) {
					$where .= $wpdb->prepare( ' AND (post_date < %s AND post_date > %s)', $before_ymd, $after_ymd );
				} elseif ( $after_ymd ) {
					$where .= $wpdb->prepare( ' AND post_date > %s', $after_ymd );
				} elseif ( $before_ymd ) {
					$where .= $wpdb->prepare( ' AND post_date < %s', $before_ymd );
				}

				if (
					( $after_date && ! $after_ymd ) ||
					( $before_date && ! $before_ymd )
				) {
					WP_CLI::log( 'Invalid date provided' );
					WP_CLI::log( 'Date: Given -> Computed' );
					if ( $after_date ) {
						WP_CLI::log( sprintf( 'After: %s -> %s', $after_date, $after_ymd ?: 'none' ) );
					}
					if ( $before_date ) {
						WP_CLI::log( sprintf( 'Before: %s -> %s', $before_date, $before_ymd ?: 'none' ) );
					}
					WP_CLI::confirm( 'Proceed?' );
				}
			}

			// get all IDs for posts in given post type(s).
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN statement
			$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=1 {$where}" );

		}

		$total = count( $posts );

		$notify = \WP_CLI\Utils\make_progress_bar( sprintf( 'Cleaning revisions for %d post(s)', $total ), $total );

		$total_deleted = 0;

		$this->start_bulk_operation();

		foreach ( $posts as $post_id ) {

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_children
			$revisions = get_children(
				array(
					'order'                  => 'DESC',
					'orderby'                => 'date ID',
					'post_parent'            => $post_id,
					'post_type'              => 'revision',
					'post_status'            => 'inherit',
					// trust me on these.
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'fields'                 => 'ids',
				)
			);

			if ( ! $revisions ) {
				$notify->tick();
				continue;
			}

			if ( $filter_keep ) {
				$keep = wp_revisions_to_keep( get_post( $post_id ) );
			}

			$count = count( $revisions );

			if ( $count > $keep ) {
				if ( $keep > 0 ) {
					$revisions = array_slice( $revisions, $keep, null, true );
				}

				$total_deleted += count( $revisions );

				if ( $hard ) {
					foreach ( $revisions as $id ) {
						if ( ! $dry_run ) {
							wp_delete_post_revision( $id );
						}
					}
				} else {
					$delete_ids = implode( ',', $revisions );
					if ( ! $dry_run ) {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN statement
						$wpdb->query( "DELETE FROM $wpdb->posts WHERE ID IN ($delete_ids)" );
					}
				}
			}

			$wpdb->flush();

			$notify->tick();
		}

		$this->end_bulk_operation();

		$notify->finish();

		if ( $dry_run ) {
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
	 * [--oldest_date=<oldest-date>]
	 * : Oldest date for revisions. Default: 5 years ago
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions generate 10
	 *     wp revisions generate --post_id=2
	 *     wp revisions generate 2 --post_type=post,page
	 */
	public function generate( $args = array(), $assoc_args = array() ) {

		$count = WP_CLI\Utils\get_flag_value( $args, 0, 15 );
		$count = absint( $count );

		$post_id     = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_id', false );
		$post_type   = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_type', false );
		$oldest_date = WP_CLI\Utils\get_flag_value( $assoc_args, 'oldest_date', '5 years ago' );

		$oldest_date_time = strtotime( $oldest_date );
		if ( false === $oldest_date_time ) {
			WP_CLI::error( 'oldest_date value invalid' );
		}

		// distribute revisions between oldest date and current time
		$interval = round( ( time() - $oldest_date_time ) / $count );

		global $wpdb;

		if ( $post_id ) {

			$posts = array( $post_id );
			$posts = array_filter( $posts, 'get_post' );

		} else {

			if ( ! $post_type ) {
				$post_types = $this->supports_revisions();
			} else {
				$post_types = wp_parse_slug_list( $post_type );
			}

			$where = '';
			foreach ( $post_types as $post_type ) {
				$where .= $wpdb->prepare( ' OR post_type = %s', $post_type );
			}

			// get all IDs for posts in given post type(s).
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN statement
			$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=2 {$where}" );

		}

		$total = count( $posts );

		$notify = \WP_CLI\Utils\make_progress_bar( sprintf( 'Generating revisions for %d post(s)', $total ), $total );

		$this->start_bulk_operation();

		remove_all_filters( 'wp_revisions_to_keep' );
		remove_all_filters( 'wp_insert_post_data' );
		add_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );

		$inc = 0;
		foreach ( $posts as $post_id ) {
			$notify->tick();

			for ( $i = 0; $i < $count; $i++ ) {

				$time = $oldest_date_time + ( $interval * $i );

				add_filter(
					'wp_insert_post_data',
					function ( $data ) use ( $time ) {
						$data['post_date_gmt']     = gmdate( 'Y-m-d H:i:s', $time );
						$data['post_date']         = wp_date( 'Y-m-d H:i:s', $time );
						$data['post_modified_gmt'] = gmdate( 'Y-m-d H:i:s', $time );
						$data['post_modified']     = wp_date( 'Y-m-d H:i:s', $time );
						return $data;
					},
					10
				);

				wp_save_post_revision( $post_id );
			}
			++$inc;
			if ( 0 === $inc % 10 ) {
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

		$wpdb->queries = array();

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		$wp_object_cache->group_ops      = array();
		$wp_object_cache->stats          = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache          = array();

		if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset(); // important
		}
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
