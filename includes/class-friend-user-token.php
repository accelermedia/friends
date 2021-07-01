<?php
/**
 * Friends User Token
 *
 * This contains the functions for managing user tokens.
 *
 * @package Friends
 */

/**
 * This is the class for the user token part of the Friends Plugin.
 *
 * @since 1.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friend_User_Token {
	const TAXONOMY = 'friend-user-token';

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
	 * Gets the Token (= the term name).
	 *
	 * @return string The URL (= the term name).
	 */
	public function get_token() {
		return $this->term->name;
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
	 * The title of the feed.
	 *
	 * @return string The feed title.
	 */
	public function get_code_challenge() {
		return self::sanitize_code_challenge( get_metadata( 'term', $this->term->term_id, 'code-challenge', true ) );
	}

	/**
	 * Validates the valid until attribute.
	 *
	 * @param  string $active The active value to be validated.
	 * @return string         A validated active value.
	 */
	public static function sanitize_valid_until( $active ) {
		return intval( $active );
	}

	/**
	 * Validates the code_challenge attribute.
	 *
	 * @param  string $code_challenge The code_challenge value to be validated.
	 * @return string         A validated code_challenge value.
	 */
	public static function sanitize_code_challenge( $code_challenge ) {
		if ( 'S256$' === substr( $code_challenge, 0, 5 ) ) {
			if ( 69 === strlen( $code_challenge ) ) {
				return $code_challenge;
			}
		}
		return 'S256$invalid';
	}

	/**
	 * Registers the taxonomy
	 */
	public static function register_taxonomy() {
		$args = array(
			'labels'            => array(
				'name'          => _x( 'User Token', 'taxonomy general name' ),
				'singular_name' => _x( 'User Token', 'taxonomy singular name' ),
				'menu_name'     => __( 'User Token' ),
			),
			'default_term'      => 'invalid',
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
		);
		register_taxonomy( self::TAXONOMY, 'user', $args );

		register_term_meta(
			self::TAXONOMY,
			'valid-until',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_valid_until' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'code-challenge',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_code_challenge' ),
			)
		);
	}

	/**
	 * Delete all tokens for a user (when it its being deleted).
	 *
	 * @param      integer $user_id  The user id.
	 */
	public static function delete_user_terms( $user_id ) {
		wp_delete_object_term_relationships( $user_id, self::TAXONOMY );
	}

	/**
	 * Delete this user token.
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
		return;
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
	 * Generate a new token for the user.
	 *
	 * @param      Friend_User $friend_user  The user to be associated.
	 * @param      integer     $valid_until  Timestamp until when the token is valid.
	 * @param      string      $code_challenge  The.
	 *
	 * @return     Friend_User_Token      A newly created term.
	 */
	public static function generate( Friend_User $friend_user, $valid_until, $code_challenge ) {
		$token = wp_generate_password( 128, false );
		return self::save( $friend_user, $token, $valid_until, $code_challenge );
	}

	/**
	 * Saves a new token as a term for the user.
	 *
	 * @param      Friend_User $friend_user  The user to be associated.
	 * @param      string      $token  The token.
	 * @param      integer     $valid_until  Timestamp until when the token is valid.
	 * @param      string      $code_challenge  The.
	 *
	 * @return     Friend_User_Token      A newly created term.
	 */
	public static function save( Friend_User $friend_user, $token, $valid_until, $code_challenge ) {
		if ( strlen( $token ) < 32 ) {
			return new WP_Error( 'token-too-weak', __( 'The token neesd to be at least 32 characters long.', 'friends' ) );
		}
		$term_id = wp_set_object_terms( $friend_user->ID, $token, self::TAXONOMY );
		if ( is_wp_error( $term_id ) ) {
			return $term_id;
		}

		$term_id = $term_id[0];
		$args = array(
			'valid-until'    => $valid_until,
			'code-challenge' => $code_challenge,
		);
		foreach ( $args as $key => $value ) {
			if ( in_array( $key, array( 'valid-until', 'code-challenge' ) ) ) {
				if ( metadata_exists( 'term', $term_id, $key ) ) {
					update_metadata( 'term', $term_id, $key, $value );
				} else {
					add_metadata( 'term', $term_id, $key, $value, true );
				}
			}
		}

		return new self( get_term( $term_id ), $friend_user );
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
	 * Find the token.
	 *
	 * @param      string $token     The token.
	 *
	 * @return     object|WP_Error   A Friend_User_Feed object.
	 */
	public static function get_by_token( $token ) {
		$term_query = new WP_Term_Query(
			array(
				'taxonomy' => self::TAXONOMY,
				'name'     => $token,
			)
		);

		foreach ( $term_query->get_terms() as $term ) {
			// TODO implement expiry check.
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
