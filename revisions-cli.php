<?php

/**
 * Manage Revisions
 */
class Revisions_CLI extends WP_CLI_Command {

	/**
	 * Dump all revisions
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions dump
	 *
	 */
	public function dump( $args = array(), $assoc_args = array() ) {

		global $wpdb;
		$revs = $wpdb->get_col( "SELECT post_title FROM $wpdb->posts WHERE post_type = 'revision'" );

		WP_CLI::confirm( sprintf( 'Remove all %d revisions?', count( $revs ) ) );

		$wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'revision'" );

		WP_CLI::success( "Done." );

	}

	/**
	 * List all revisions
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : List revisions for given post type(s).
	 *
	 * [--post-id=<post-id>]
	 * : List revisions for given post. Trumps --post-type.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions list
	 *     wp revisions list --post-id=2
	 *     wp revisions list --post-type=post,page
	 *
	 * @subcommand list
	 */
	public function list_( $args = array(), $assoc_args = array() ) {

		global $wpdb;
		if ( ! empty( $assoc_args['post-id'] ) ) {

			$revs = $wpdb->get_results( $wpdb->prepare( "SELECT post_title, post_parent FROM $wpdb->posts WHERE post_parent = %d", $assoc_args['post-id'] ) );

		} else if ( ! empty( $assoc_args['post-type'] ) ) {

			$post_types = explode( ',', $assoc_args['post-type'] );

			$where = '';
			foreach ( $post_types as $post_type ) {
				$where .= $wpdb->prepare( ' OR post_type = %s', $post_type );
			}

			// get all IDs for posts in given post type(s)
			$ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=2 {$where}" );

			$post__in = implode( ',', $ids );

			// get revisions of those IDs
			$revs = $wpdb->get_results( "SELECT post_title, post_parent FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent IN ({$post__in}) ORDER BY post_parent DESC" );

		} else {

			$revs = $wpdb->get_results( "SELECT post_title, post_parent FROM $wpdb->posts WHERE post_type = 'revision' ORDER BY post_parent DESC" );

		}

		$total = count( $revs );

		if ( $total > 100 ) {
			WP_CLI::confirm( sprintf( 'List all %d revisions?', $total ) );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array('post_title', 'post_parent'), 'revisions' );
		$formatter->display_items( $revs );
		WP_CLI::success( "$total revisions." );

	}

	/**
	 * Get revision status
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions status
	 *
	 */
	public function status( $args = array(), $assoc_args = array() ) {

		if ( defined( 'WP_POST_REVISIONS' ) ) {
			if ( WP_POST_REVISIONS === true ) {
				WP_CLI::success( 'WP_POST_REVISIONS is true. Keeps all revisions.' );
			} else {
				WP_CLI::success( sprintf( 'Keeping the last %d revisions', WP_POST_REVISIONS ) );
			}
		} else {
			WP_CLI::success( 'WP_POST_REVISIONS is undefined.' );
		}

	}

	/**
	 * Clean out old revisions
	 *
	 * ## OPTIONS
	 *
	 * [<keep>]
	 * : Number of revisions to keep per post
	 *
	 * [--post-type=<post-type>]
	 * : List revisions for given post type(s). Default any
	 *
	 * [--post-id=<post-id>]
	 * : List revisions for given post.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions clean
	 *     wp revisions clean 5
	 *     wp revisions clean --post-id=2
	 *     wp revisions clean 5 --post-type=post,page
	 *
	 */
	public function clean( $args = array(), $assoc_args = array() ) {

		global $wpdb;

		if ( ! empty( $assoc_args['post-id'] ) ) {

			$posts = array( $assoc_args['post-id'] );

		} else {

			if ( empty( $assoc_args['post-type'] ) ) {
				$post_types = $this->supports_revisions();
			} else {
				$post_types = explode( ',', $assoc_args['post-type'] );
			}

			$where = '';
			foreach ( $post_types as $post_type ) {
				$where .= $wpdb->prepare( ' OR post_type = %s', $post_type );
			}

			// get all IDs for posts in given post type(s)
			$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=2 {$where}" );

		}

		$total = count( $posts );

		$notify = \WP_CLI\Utils\make_progress_bar( "Cleaning revisions for $total post(s)", $total );

		if ( isset( $args[0] ) ) {
			$keep = intval( $args[0] );
		} else if ( true === WP_POST_REVISIONS ) {
			WP_CLI::error( 'WP_POST_REVISIONS is set to true (keeps all revisions). Please pass a number.' );
		} else {
			$keep = WP_POST_REVISIONS;
		}

		foreach ( $posts as $post_id ) {

			wp_save_post_revision( $post_id );
			$revs = wp_get_post_revisions( $post_id );
			$r = wp_list_pluck( $revs, 'post_name' );
			$delete = array_slice( $r, $keep, null, true );
			foreach ( $delete as $id => $name ) {
				wp_delete_post_revision( $id );
			}

			$notify->tick();
		}

		$notify->finish();

	}

	/**
	 * Generate revisions
	 *
	 * ## OPTIONS
	 *
	 * [<count>]
	 * : Number of revisions to generate per post. Default 15
	 *
	 * [--post-type=<post-type>]
	 * : List revisions for given post type(s). Default any
	 *
	 * [--post-id=<post-id>]
	 * : List revisions for given post.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revisions generate 10
	 *     wp revisions generate --post-id=2
	 *     wp revisions generate 2 --post-type=post,page
	 *
	 */
	public function generate( $args = array(), $assoc_args = array() ) {

		global $wpdb;

		if ( ! empty( $assoc_args['post-id'] ) ) {

			$posts = array( $assoc_args['post-id'] );

		} else {

			if ( empty( $assoc_args['post-type'] ) ) {
				$post_types = $this->supports_revisions();
			} else {
				$post_types = explode( ',', $assoc_args['post-type'] );
			}

			$where = '';
			foreach ( $post_types as $post_type ) {
				$where .= $wpdb->prepare( ' OR post_type = %s', $post_type );
			}

			// get all IDs for posts in given post type(s)
			$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE 1=2 {$where}" );

		}

		$total = count( $posts );

		$notify = \WP_CLI\Utils\make_progress_bar( "Generating revisions for $total post(s)", $total );

		$count = isset( $args[0] ) ? intval( $args[0] ) : 15;

		foreach ( $posts as $post_id ) {
			$notify->tick();

			$p = get_post( $post_id );
			$content = $p->post_content;
			for ( $i = 0; $i < $count; $i++ ) {
				$content .= '|';
				wp_update_post( array(
					'ID'           => $post_id,
					'post_content' => $content
				) );
			}
		}

		$notify->finish();

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