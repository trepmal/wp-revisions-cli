<?php
/**
 * Revisions CLI
 *
 * @package trepmal/wp-revisions-cli
 */

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

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
	 * : Hard delete. Slower, uses wp_delete_post_revision(). Alias to wp revisions clean -1
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
		$revs = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'" ); // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching

		if ( $revs < 1 ) {
			WP_CLI::success( 'No revisions.' );
			return;
		}

		WP_CLI::confirm( sprintf( 'Remove all %d revisions?', $revs ), $assoc_args );

		if ( isset( $assoc_args['hard'] ) ) {
			WP_CLI::run_command( array( 'revisions', 'clean', -1 ), array( 'hard' => '' ) );
			return;
		}

		$wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'revision'" ); // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching

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
	 * : Comma-separated list of fields to be included in the output. Defaults
	 * to ID, post_title, post_parent
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
			'fields',
			[
				'ID',
				'post_title',
				'post_parent',
			]
		);

		if ( is_string( $fields ) ) {
			$fields = explode( ',', $fields );
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
		$excluded_fields = array_diff( $fields, $allowed_fields );

		if ( ! empty( $excluded_fields ) ) {
			WP_CLI::error( 'Invalid values provided in the fields argument.' );
		}

		$fields = array_map(
			function ( $field ) {
				return esc_sql( $field );
			},
			$fields
		);
		$fields = implode( ',', $fields );

		global $wpdb;
		if ( ! empty( $assoc_args['post_id'] ) ) {

			$revs = $wpdb->get_results( // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT {$fields} FROM $wpdb->posts WHERE post_parent = %d", //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $fields is escaped above with esc_sql()
					$assoc_args['post_id']
				)
			);

		} else if ( ! empty( $assoc_args['post_type'] ) ) {

			$post_types = explode( ',', $assoc_args['post_type'] );

			$where = '';
			foreach ( $post_types as $post_type ) {
				$where .= $wpdb->prepare( ' OR post_type = %s', $post_type );
			}

			// get all IDs for posts in given post type(s).
			$ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=2 {$where}" ); // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- $where is prepared above

			// Prepare the IDs for inclusion in the query.
			$post__in = array_map(
				function ( $id ) {
					return esc_sql( $id );
				},
				$ids
			);
			$post__in = implode( ',', $ids );

			// get revisions of those IDs.
			$revs = $wpdb->get_results( // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching
				"SELECT {$fields} FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent IN ({$post__in}) ORDER BY post_parent DESC" //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $fields and $post__in are escaped above with esc_sql()
			);

		} else {

			$revs = $wpdb->get_results( // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching
				"SELECT {$fields} FROM $wpdb->posts WHERE post_type = 'revision' ORDER BY post_parent DESC" //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $fields is escaped above with esc_sql()
			);

		}

		$total = count( $revs );

		if ( $total > 100 ) {
			WP_CLI::confirm( sprintf( 'List all %d revisions?', $total ), $assoc_args );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'ID', 'post_parent', 'post_title' ), 'revisions' );
		$formatter->display_items( $revs );
		WP_CLI::success( sprintf( '%d revisions.', $total ) );

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
	 * : Number of revisions to keep per post. Defaults to WP_POST_REVISIONS if it is an integer
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

		} else {

			if ( empty( $assoc_args['post_type'] ) ) {
				$post_types = $this->supports_revisions();
			} else {
				$post_types = explode( ',', $assoc_args['post_type'] );
			}

			$where = '';
			$post_type_where = array();
			foreach ( $post_types as $post_type ) {
				$post_type_where[] = $wpdb->prepare( 'post_type = %s', $post_type );
			}
			$where = ' AND (' . implode( ' OR ', $post_type_where ) . ')';

			if ( isset( $assoc_args['after-date'] ) && isset( $assoc_args['before-date'] ) ) {
				$where .= $wpdb->prepare( ' AND (post_date < %s AND post_date > %s)', $assoc_args['before-date'], $assoc_args['after-date'] );
			} else if ( isset( $assoc_args['after-date'] ) ) {
				$where .= $wpdb->prepare( ' AND post_date > %s', $assoc_args['after-date'] );
			} else if ( isset( $assoc_args['before-date'] ) ) {
				$where .= $wpdb->prepare( ' AND post_date < %s', $assoc_args['before-date'] );
			}

			// get all IDs for posts in given post type(s).
			$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=1 {$where}" ); // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- $where is prepared above

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

		$total_deleted = 0;

		foreach ( $posts as $post_id ) {

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
						$wpdb->query( "DELETE FROM $wpdb->posts WHERE ID IN ($delete_ids)" ); // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching
					}
				}
			}

			$wpdb->flush();

			$notify->tick();
		}

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
			$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=2 {$where}" ); // phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery,WordPress.VIP.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- $where is prepared above

		}

		$total = count( $posts );

		$notify = \WP_CLI\Utils\make_progress_bar( sprintf( 'Generating revisions for %d post(s)', $total ), $total );

		$count = isset( $args[0] ) ? intval( $args[0] ) : 15;

		foreach ( $posts as $post_id ) {
			$notify->tick();

			$p = get_post( $post_id );
			$content = $p->post_content;
			for ( $i = 0; $i < $count; $i++ ) {
				if ( '&nbsp;' === substr( $content, -6 ) ) {
					$content = substr( $content, 0, -6 );
				} else {
					$content .= '&nbsp;';
				}
				wp_update_post( array(
					'ID'           => $post_id,
					'post_content' => $content,
				) );
			}
		}

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

}
WP_CLI::add_command( 'revisions', 'Revisions_CLI' );
