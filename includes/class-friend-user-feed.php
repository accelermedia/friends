<?php
/**
 * Friends Feed URL
 *
 * This contains the functions for managing feed URLs.
 *
 * @package Friends
 */

/**
 * This is the class for the feed URLs part of the Friends Plugin.
 *
 * @since 1.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friend_User_Feed {
	const TAXONOMY = 'friend-user-feed';

	/**
	 * Contains a reference to the WP_Term for the feed.
	 *
	 * @var WP_Term
	 */
	private $term;

	/**
	 * Contains a reference to the associated Friend_User.
	 *
	 * @var Friend_User
	 */
	private $friend_user;

	/**
	 * Constructor
	 *
	 * @param WP_Term          $term        The WordPress term of the feed taxonomy.
	 * @param Friend_User|null $friend_user Optionally the associated Friend_User, if available.
	 */
	public function __construct( WP_Term $term, Friend_User $friend_user = null ) {
		$this->term = $term;
		$this->friend_user = $friend_user;
	}

	/**
	 * The string representation of the term = The URL.
	 *
	 * @return string Term name = URL.
	 */
	public function __toString() {
		return $this->term->name;
	}

	/**
	 * Gets the URL (= the term name).
	 *
	 * @return string The URL (= the term name).
	 */
	public function get_url() {
		return $this->term->name;
	}

	/**
	 * Get the private URL of the friend (= append authentication).
	 *
	 * @param      int $validity  The validity in seconds.
	 *
	 * @return     string  The (extended) URL.
	 */
	public function get_private_url( $validity = 3600 ) {
		$feed_url = $this->get_url();
		$friend_user = $this->get_friend_user();

		if ( $friend_user->is_friend_url( $feed_url ) && ( current_user_can( Friends::REQUIRED_ROLE ) || wp_doing_cron() ) ) {
			$friends = Friends::get_instance();
			$feed_url = $friends->access_control->append_auth( $feed_url, $friend_user, $validity );
		}

		return apply_filters( 'friends_friend_private_feed_url', $feed_url, $friend_user );
	}

	/**
	 * Get the local feed URL. Dysfunctional at the moment.
	 *
	 * @return string The local feed URL.
	 */
	public function get_local_url() {
		$friend_user = $this->get_friend_user();
		return home_url() . '/friends/' . $friend_user->user_login . '/feed/';
	}

	/**
	 * Get the local HTML URL. This would be the URL where to see the friends post on the friends page.
	 * Dysfunctional at the moment.
	 *
	 * @return string The local HTML URL.
	 */
	public function get_local_html_url() {
		$friend_user = $this->get_friend_user();
		return $friend_user->get_local_friends_page_url();
	}

	/**
	 * Gets the friend user associated wit the term.
	 *
	 * @return Friend_User|null The associated user.
	 */
	public function get_friend_user() {
		if ( empty( $this->friend_user ) ) {
			$user_ids = get_objects_in_term( $this->term->term_id, self::TAXONOMY );
			if ( empty( $user_ids ) ) {
				return null;
			}
			$user_id = reset( $user_ids );
			$this->friend_user = new Friend_User( $user_id );
		}

		return $this->friend_user;
	}

	/**
	 * Whether the feed is active (=subscribed).
	 *
	 * @return bool Feed is active if true.
	 */
	public function get_active() {
		return self::validate_active( get_metadata( 'term', $this->term->term_id, 'active', true ) );
	}

	/**
	 * The post format to which the feed items should be imported.
	 *
	 * @return string The post format.
	 */
	public function get_post_format() {
		return self::validate_post_format( get_metadata( 'term', $this->term->term_id, 'post-format', true ) );
	}

	/**
	 * The parser to be used to fetch and parse the feed.
	 *
	 * @return string The parser slug.
	 */
	public function get_parser() {
		return self::validate_parser( get_metadata( 'term', $this->term->term_id, 'parser', true ) );
	}

	/**
	 * The (expected) mime-type of the feed.
	 *
	 * @return string The mime-type.
	 */
	public function get_mime_type() {
		return self::validate_mime_type( get_metadata( 'term', $this->term->term_id, 'mime-type', true ) );
	}

	/**
	 * The title of the feed.
	 *
	 * @return string The feed title.
	 */
	public function get_title() {
		return self::validate_title( get_metadata( 'term', $this->term->term_id, 'title', true ) );
	}

	/**
	 * A log entry of the last retrieval of the feed.
	 *
	 * @return string The log line.
	 */
	public function get_last_log() {
		return self::validate_last_log( get_metadata( 'term', $this->term->term_id, 'last-log', true ) );
	}

	/**
	 * Whether the feed is active.
	 *
	 * @return boolean Is the feed to be fetched?
	 */
	public function is_active() {
		return self::validate_active( get_metadata( 'term', $this->term->term_id, 'active', true ) );
	}

	/**
	 * Validates the post format against defined post formats.
	 *
	 * @param  string $post_format The post format to be validated.
	 * @return string              A validated post format.
	 */
	public static function validate_post_format( $post_format ) {
		$post_formats = get_post_format_strings();
		$post_formats['autodetect'] = true;
		if ( isset( $post_formats[ $post_format ] ) ) {
			return $post_format;
		}
		$keys = array_keys( $post_formats );
		return reset( $keys );
	}

	/**
	 * Validates the parser against defined parsers.
	 *
	 * @param  string $parser The parser to be validated.
	 * @return string         A validated parser.
	 */
	public static function validate_parser( $parser ) {
		$friends = Friends::get_instance();
		$parsers = $friends->feed->get_registered_parsers();
		if ( isset( $parsers[ $parser ] ) ) {
			return $parser;
		}
		// We're lax with parsers to allow deactivating parser plugins without deleting this information.
		return $parser;
	}

	/**
	 * Validates the mime-type.
	 *
	 * @param  string $mime_type The mime-type to be validated.
	 * @return string            A validated mime-type.
	 */
	public static function validate_mime_type( $mime_type ) {
		return substr( preg_replace( '/[^a-z0-9\/_+-]/', '', $mime_type ), 0, 100 );
	}

	/**
	 * Validates the title.
	 *
	 * @param  string $title The title to be validated.
	 * @return string        A validated title.
	 */
	public static function validate_title( $title ) {
		return substr( $title, 0, 100 );
	}

	/**
	 * Validates the last log line.
	 *
	 * @param  string $last_log The log line to be validated.
	 * @return string           A validated log line.
	 */
	public static function validate_last_log( $last_log ) {
		return substr( $last_log, 0, 1000 );
	}

	/**
	 * Validates the active attribute.
	 *
	 * @param  string $active The active value to be validated.
	 * @return string         A validated active value.
	 */
	public static function validate_active( $active ) {
		return (bool) $active;
	}

	/**
	 * Registers the taxonomy
	 */
	public static function register_taxonomy() {
		$args = array(
			'labels'            => array(
				'name'          => _x( 'Feed URL', 'taxonomy general name' ),
				'singular_name' => _x( 'Feed URL', 'taxonomy singular name' ),
				'menu_name'     => __( 'Feed URL' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
		);
		register_taxonomy( self::TAXONOMY, 'user', $args );
		register_taxonomy_for_object_type( self::TAXONOMY, 'user' );

		register_term_meta(
			self::TAXONOMY,
			'post-format',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_post_format' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'mime-type',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_mime_type' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'title',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_title' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'last-log',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_last_log' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'active',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_active' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'parser',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_parser' ),
			)
		);
	}

	/**
	 * Delete all feeds for a user (when it its being deleted).
	 *
	 * @param      integer $user_id  The user id.
	 */
	public static function delete_user_terms( $user_id ) {
		wp_delete_object_term_relationships( $user_id, self::TAXONOMY );
	}

	/**
	 * Delete this feed.
	 */
	public function delete() {
		$friend_user = $this->get_friend_user();
		wp_remove_object_terms( $friend_user->ID, $this->term->term_id, self::TAXONOMY );
	}

	/**
	 * Convert the previous storage of a feed URL as a user option to use terms.
	 *
	 * @param  Friend_User $friend_user The user to be converted.
	 * @return array                    An array of newly created Friend_User_Feed items.
	 */
	public static function convert_user( Friend_User $friend_user ) {
		$feed_url = $friend_user->get_user_option( 'friends_feed_url' );
		if ( ! $feed_url ) {
			$feed_url = rtrim( $friend_user->user_url, '/' ) . '/feed/';
		}

		$term = self::save(
			$friend_user,
			$feed_url,
			array(
				'active'      => true,
				'parser'      => 'simplepie',
				'post-format' => 'standard',
				'mime-type'   => 'application/rss+xml',
				'title'       => $friend_user->display_name . ' RSS Feed',
			)
		);

		if ( is_wp_error( $term ) ) {
			return null;
		}

		// $friend_user->delete_user_option( 'friends_feed_url' );

		return array( new self( $term, $friend_user ) );
	}

	/**
	 * Saves multiple feeds for a user.
	 *
	 * See save() for possible options.
	 *
	 * @param      Friend_User $friend_user  The associated user.
	 * @param      array       $feeds        The feeds in the format array( url => options ).
	 *
	 * @return     array      Array of the newly created terms.
	 */
	public static function save_multiple( Friend_User $friend_user, array $feeds ) {
		$all_urls = array();
		foreach ( wp_get_object_terms( $friend_user->ID, self::TAXONOMY ) as $term ) {
			$all_urls[ $term->name ] = $term->term_id;
		}

		$term_ids = wp_set_object_terms( $friend_user->ID, array_keys( array_merge( $all_urls, $feeds ) ), self::TAXONOMY );
		if ( is_wp_error( $term_ids ) ) {
			return $term_ids;
		}

		foreach ( wp_get_object_terms( $friend_user->ID, self::TAXONOMY ) as $term ) {
			$all_urls[ $term->name ] = $term->term_id;
		}

		foreach ( $feeds as $url => $options ) {
			if ( ! isset( $all_urls[ $url ] ) ) {
				continue;
			}
			$term_id = $all_urls[ $url ];
			foreach ( $options as $key => $value ) {
				if ( in_array( $key, array( 'active', 'parser', 'post-format', 'mime-type', 'title' ) ) ) {
					if ( metadata_exists( 'term', $term_id, $key ) ) {
						update_metadata( 'term', $term_id, $key, $value );
					} else {
						add_metadata( 'term', $term_id, $key, $value, true );
					}
				}
			}
		}
		return $term_ids;
	}

	/**
	 * Saves a new feed as a term for the user.
	 *
	 * @param  Friend_User $friend_user The user to be associated.
	 * @param  string      $url         The feed URL.
	 * @param  array       $args        Further parameters. Possibly array keys: active, parser, post_format, mime_type, title.
	 * @return WP_Term                  A newly created term.
	 */
	public static function save( Friend_User $friend_user, $url, $args = array() ) {
		$all_urls = array();
		foreach ( wp_get_object_terms( $friend_user->ID, self::TAXONOMY ) as $term ) {
			$all_urls[ $term->name ] = $term->term_id;
		}

		if ( ! isset( $all_urls[ $url ] ) ) {
			$all_urls[ $url ] = false;

			$term_ids = wp_set_object_terms( $friend_user->ID, array_keys( $all_urls ), self::TAXONOMY );
			if ( is_wp_error( $term_ids ) ) {
				return $term_ids;
			}
			foreach ( wp_get_object_terms( $friend_user->ID, self::TAXONOMY ) as $term ) {
				$all_urls[ $term->name ] = $term->term_id;
			}
		}

		if ( ! isset( $all_urls[ $url ] ) ) {
			return false;
		}

		$term_id = $all_urls[ $url ];
		foreach ( $args as $key => $value ) {
			if ( in_array( $key, array( 'active', 'parser', 'post-format', 'mime-type', 'title' ) ) ) {
				if ( metadata_exists( 'term', $term_id, $key ) ) {
					update_metadata( 'term', $term_id, $key, $value );
				} else {
					add_metadata( 'term', $term_id, $key, $value, true );
				}
			}
		}

		return get_term( $term_id );
	}

	/**
	 * Generic function for updating User_Feed metadata.
	 *
	 * @param      string $key    The key.
	 * @param      string $value  The value.
	 */
	public function update_metadata( $key, $value ) {
		if ( metadata_exists( 'term', $this->term->term_id, $key ) ) {
			return update_metadata( 'term', $this->term->term_id, $key, $value );
		}
		return add_metadata( 'term', $this->term->term_id, $key, $value, true );
	}

	/**
	 * Update the last-log metadata.
	 *
	 * @param      string $value  The value to log.
	 *
	 * @return     int|false The inserted term id.
	 */
	public function update_last_log( $value ) {
		return $this->update_metadata( 'last-log', gmdate( 'Y-m-d H:i:s' ) . ': ' . $value );
	}

	/**
	 * Fetch the feeds associated with the Friend_User.
	 *
	 * @param  Friend_User $friend_user The user we're looking for.
	 * @return array                    An array of Friend_User_Feed objects.
	 */
	public static function get_for_user( Friend_User $friend_user ) {
		$term_query = new WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'object_ids' => $friend_user->ID,
			)
		);
		$feeds = array();
		foreach ( $term_query->get_terms() as $term ) {
			$feeds[ $term->term_id ] = new self( $term, $friend_user );
		}

		return $feeds;
	}

	/**
	 * Get the feed with the specific id.
	 *
	 * @param      int $id     The feed id.
	 *
	 * @return     object|WP_Error   A Friend_User_Feed object.
	 */
	public static function get_by_id( $id ) {
		$term_query = new WP_Term_Query(
			array(
				'taxonomy' => self::TAXONOMY,
				'include'  => $id,
			)
		);
		foreach ( $term_query->get_terms() as $term ) {
			return new self( $term );
		}

		return new WP_Error( 'term_not_found' );
	}

	/**
	 * Gets the identifier.
	 *
	 * @return     int  The identifier.
	 */
	public function get_id() {
		return $this->term->term_id;
	}
}
