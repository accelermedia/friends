<?php
/**
 * Friends REST
 *
 * This contains the functions for REST.
 *
 * @package Friends
 */

/**
 * This is the class for the REST part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_REST {
	const PREFIX = 'friends/v1';
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );
		add_action( 'wp_trash_post', array( $this, 'notify_remote_friend_post_deleted' ) );
		add_action( 'before_delete_post', array( $this, 'notify_remote_friend_post_deleted' ) );
		add_action( 'set_user_role', array( $this, 'notify_remote_friend_request_accepted' ), 20, 3 );
	}

	/**
	 * Add the REST API to send and receive friend requests
	 */
	public function add_rest_routes() {
		register_rest_route(
			self::PREFIX,
			'friend-request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_friend_request' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::PREFIX,
			'indieauth',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_indieauth' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::PREFIX,
			'accept-friend-request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_accept_friend_request' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::PREFIX,
			'post-deleted',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_friend_post_deleted' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Receive a notification via REST that a friend request was accepted
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_accept_friend_request( WP_REST_Request $request ) {
		$request_id     = $request->get_param( 'request' );
		$friend_user_id = get_option( 'friends_request_' . sha1( $request_id ) );
		$friend_user    = false;
		if ( $friend_user_id ) {
			$friend_user = new Friend_User( $friend_user_id );
		}

		if ( ! $request_id || ! $friend_user || is_wp_error( $friend_user ) || ! $friend_user->user_url ) {
			return new WP_Error(
				'friends_invalid_parameters',
				'Not all necessary parameters were provided.',
				array(
					'status' => 403,
				)
			);
		}

		$future_in_token = get_user_option( 'friends_future_in_token_' . sha1( $request_id ), $friend_user_id );
		$proof            = $request->get_param( 'proof' );
		if ( ! $proof || sha1( $future_in_token . $request_id ) !== $proof ) {
			return new WP_Error(
				'friends_invalid_proof',
				'An invalid proof was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$friend_user_login = Friend_User::get_user_login_for_url( $friend_user->user_url );
		if ( ! $friend_user->has_cap( 'pending_friend_request' ) ) {
			return new WP_Error(
				'friends_offer_no_longer_valid',
				'The friendship offer is no longer valid.',
				array(
					'status' => 403,
				)
			);
		}

		$future_out_token = $request->get_param( 'key' );
		if ( ! is_string( $future_out_token ) || empty( $future_out_token ) ) {
			return new WP_Error(
				'friends_invalid_key',
				'The key must be a non-empty string.',
				array(
					'status' => 403,
				)
			);
		}
		$friend_user->make_friend( $future_out_token, $future_in_token );

		$friend_user->update_user_icon_url( $request->get_param( 'icon_url' ) );
		if ( $request->get_param( 'name' ) ) {
			wp_update_user(
				array(
					'ID'           => $friend_user->ID,
					'nickname'     => $request->get_param( 'name' ),
					'first_name'   => $request->get_param( 'name' ),
					'display_name' => $request->get_param( 'name' ),
				)
			);
		}

		delete_user_option( $friend_user_id, 'friends_future_in_token_' . sha1( $request_id ) );

		do_action( 'notify_accepted_friend_request', $friend_user );
		return array(
			'signature' => sha1( $future_out_token . $future_in_token ),
		);
	}

	/**
	 * Handle an incoming account creation request via IndieAuth.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	protected function handle_create_account_request( WP_REST_Request $request ) {
		if ( $request->get_param( 'account_role' ) !== 'friend' ) {
			return new WP_Error( 'not-a-friend-request', __( 'We can only handle friend requests.', 'friends' ) );
		}

		$client_id = $request->get_param( 'client_id' );
		if ( ! $client_id ) {
			return new WP_Error( 'unknown-user', __( "Sorry, we don't have you on record.", 'friends' ) );
		}

		$friend_user_login = Friend_User::get_user_login_for_url( $client_id );
		$friend_user = Friend_User::get_user( $friend_user_login );
		if ( ! $friend_user || is_wp_error( $friend_user ) ) {
			return new WP_Error( 'unknown-user', __( "Sorry, we don't have you on record.", 'friends' ) );
		}
		if ( ! $friend_user->is_valid_friend() && ! $friend_user->has_cap( 'pending_friend_request' ) ) {
			return new WP_Error( 'invalid-state', __( "Sorry, we haven't been waiting for your request.", 'friends' ) );
		}

		$token = Friend_User_Token::generate( $friend_user, time() + 86400, $request->get_param( 'code_challenge_method' ) . '$' . $request->get_param( 'code_challenge' ) );

		return array(
			'state'                 => $request->get_param( 'state' ),
			'code'                  => $token->get_token(),
			'code_challenge'        => hash( 'sha256', $friend_user->generate_indieauth_code_verifier() ),
			'code_challenge_method' => 'S256',
		);
	}
	/**
	 * Receive validation that a friend request was really requested by the remote party and give them the code.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_indieauth( WP_REST_Request $request ) {
		$state = $request->get_param( 'state' );
		if ( $state ) {
			$state = get_transient( 'friend_request_' . substr( sha1( home_url() ), 0, 10 ) . '_' . $state );
			if ( ! isset( $state['me'] ) ) {
				$state = false;
			}
		}

		if ( ! $state ) {
			$scopes = explode( ' ', $request->get_param( 'scope' ) );
			if ( in_array( 'create_account', $scopes ) ) {
				return $this->handle_create_account_request( $request );
			}

			// Fallback to the IndieAuth WordPress plugin.
			$args = array_filter(
				array(
					'action'        => 'indieauth',
					'client_id'     => $request->get_param( 'client_id' ),
					'redirect_uri'  => $request->get_param( 'redirect_uri' ),
					'state'         => $request->get_param( 'state' ),
					'me'            => $request->get_param( 'me' ),
					'response_type' => $request->get_param( 'response_type' ),
				)
			);

			$login_form_url = add_query_arg( $args, wp_login_url() );
			return new WP_REST_Response( array( 'redirect' => $login_form_url ), 302, array( 'Location' => $login_form_url ) );
		}

		$code = $request->get_param( 'code' );
		if ( ! $code ) {
			return new WP_Error( 'code-missing', __( 'No code was provided.', 'friends' ) );
		}

		$friend_username = Friend_User::get_user_login_for_url( $state['me'] );
		$friend_user = Friend_User::create( $friend_username, 'friend_request', $state['me'] );
		$token = Friend_User_Token::generate( $friend_user, time() + 86400, $request->get_param( 'code_challenge_method' ) . '$' . $request->get_param( 'code_challenge' ) );
		$friend_user->set_indieauth_code( $code, $state['redirect_uri'], $state['code_verifier'] );

		return array(
			'code' => $token->get_token(),
		);
	}

	/**
	 * Receive a friend request via REST
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_request( WP_REST_Request $request ) {
		$version = $request->get_param( 'version' );
		if ( 2 !== intval( $version ) ) {
			$me = $request->get_param( 'me' );
			if ( filter_var( $me, FILTER_VALIDATE_URL ) ) {
				// IndieAuth request.

				$data = Friends_Mf2\fetch( $me );
				if ( empty( $data['rels']['authorization_endpoint'] ) ) {
					return new WP_Error( 'no-indieauth', __( "Couldn't find the IndieAuth authorization_endpoint.", 'friends' ) );
				}

				if ( empty( $data['rels']['token_endpoint'] ) ) {
					return new WP_Error( 'no-indieauth', __( "Couldn't find the IndieAuth token_endpoint.", 'friends' ) );
				}

				$auth_url = false;
				$token_url = false;
				if ( isset( $data['rels']['friends-base-url'] ) ) {
					$friends_base_url = reset( $data['rels']['friends-base-url'] );
					foreach ( $data['rels']['authorization_endpoint'] as $url ) {
						if ( 0 === strpos( $url, $friends_base_url ) ) {
							// Just in case there are multiple authorization_endpoint URLs, pick the friends one.
							$auth_url = $url;
							break;
						}
					}
					foreach ( $data['rels']['token_endpoint'] as $url ) {
						if ( 0 === strpos( $url, $friends_base_url ) ) {
							// Just in case there are multiple authorization_endpoint URLs, pick the friends one.
							$token_url = $url;
							break;
						}
					}
				}

				if ( ! $auth_url ) {
					$auth_url = reset( $data['rels']['authorization_endpoint'] );
				}

				if ( ! $token_url ) {
					$token_url = reset( $data['rels']['token_endpoint'] );
				}

				$state = wp_generate_password( 56, false );
				$code_verifier = wp_generate_password( 90, false );
				$redirect_uri = get_rest_url() . Friends_REST::PREFIX . '/indieauth';

				set_transient(
					'friend_request_' . substr( sha1( home_url() ), 0, 10 ) . '_' . $state,
					array(
						'me'            => $me,
						'code_verifier' => $code_verifier,
						'redirect_uri'  => $redirect_uri,
						'token_url'     => $token_url,
					),
					1200
				);

				$auth_url = add_query_arg(
					array(
						'response_type'         => 'code',
						'state'                 => $state,
						'client_id'             => home_url(),
						'scope'                 => 'create_account',
						'account_role'          => 'friend',
						'response_type'         => 'code',
						'code_challenge'        => hash( 'sha256', $code_verifier ),
						'code_challenge_method' => 'S256',
						'redirect_uri'          => $redirect_uri,
					),
					$auth_url
				);

				return new WP_REST_Response( array( 'redirect' => $auth_url ), 302, array( 'Location' => $auth_url ) );
			}

			return new WP_Error(
				'friends_unsupported_protocol_version',
				'Incompatible Friends protocol version.',
				array(
					'status' => 403,
				)
			);
		}

		$codeword = $request->get_param( 'codeword' );
		if ( get_option( 'friends_require_codeword' ) && get_option( 'friends_codeword', 'friends' ) !== $codeword ) {
			return new WP_Error(
				'friends_invalid_codeword',
				get_option( 'friends_wrong_codeword_message', 'An invalid codeword was provided.' ),
				array(
					'status' => 403,
				)
			);
		}

		$url = trim( $request->get_param( 'url' ) );
		if ( ! is_string( $url ) || ! Friends::check_url( $url ) || 0 === strcasecmp( home_url(), $url ) ) {
			return new WP_Error(
				'friends_invalid_site',
				'An invalid site was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$future_out_token = $request->get_param( 'key' );
		if ( ! is_string( $future_out_token ) || empty( $future_out_token ) ) {
			return new WP_Error(
				'friends_invalid_key',
				'The key must be a non-empty string.',
				array(
					'status' => 403,
				)
			);
		}
		$user_login = Friend_User::get_user_login_for_url( $url );
		$friend_user = Friend_User::create( $user_login, 'friend_request', $url, $request->get_param( 'name' ), $request->get_param( 'icon_url' ) );
		if ( $friend_user->has_cap( 'friend' ) ) {
			if ( get_user_option( 'friends_out_token', $friend_user->ID ) && ! get_user_option( 'friends_out_token', $friend_user->ID ) ) {
				// TODO: trigger an accept friend request right away?
			}
			$friend_user->set_role( 'friend_request' );
		}
		$friend_user->update_user_icon_url( $request->get_param( 'icon_url' ) );
		$friend_user->update_user_option( 'friends_future_out_token', $request->get_param( 'key' ) );
		$friend_user->update_user_option( 'friends_request_message', mb_substr( $request->get_param( 'message' ), 0, 2000 ) );

		$request_id = wp_generate_password( 128, false );
		$friend_user->update_user_option( 'friends_request_id', $request_id );

		return array(
			'request' => $request_id,
		);
	}

	/**
	 * Notify friends of a deleted post
	 *
	 * @param  int $post_id The post id of the post that is deleted.
	 */
	public function notify_remote_friend_post_deleted( $post_id ) {
		$post = WP_Post::get_instance( $post_id );
		if ( 'post' !== $post->post_type ) {
			return;
		}

		$friends = Friend_User_Query::all_friends();
		$friends = $friends->get_results();

		foreach ( $friends as $friend_user ) {
			$friend_rest_url = $friend_user->get_rest_url();

			$response = wp_safe_remote_post(
				$friend_rest_url . '/post-deleted',
				array(
					'body'        => array(
						'post_id' => $post_id,
						'friend'  => $friend_user->get_user_option( 'friends_out_token' ),
					),
					'timeout'     => 20,
					'redirection' => 5,
				)
			);
		}
	}

	/**
	 * Receive a REST message that a post was deleted.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_post_deleted( $request ) {
		$token   = $request->get_param( 'friend' );
		$auth    = $request->get_param( 'auth' );
		$user_id = $this->friends->access_control->verify_token( $token, null, $auth );
		if ( ! $user_id ) {
			return new WP_Error(
				'friends_request_failed',
				'Could not respond to the request.',
				array(
					'status' => 403,
				)
			);
		}
		$friend_user     = new Friend_User( $user_id );
		$remote_post_id  = $request->get_param( 'post_id' );
		$remote_post_ids = $friend_user->get_remote_post_ids();

		if ( ! isset( $remote_post_ids[ $remote_post_id ] ) ) {
			return array(
				'deleted' => false,
			);
		}

		$post_id = $remote_post_ids[ $remote_post_id ];
		$post    = WP_Post::get_instance( $post_id );
		if ( Friends::CPT === $post->post_type ) {
			wp_delete_post( $post_id );
		}

		return array(
			'deleted' => true,
		);
	}

	/**
	 * Discover the REST URL for a friend site
	 *
	 * @param  array $feeds The URLs of the site.
	 * @return string|WP_Error The REST URL or an error.
	 */
	public function get_rest_url( array $feeds ) {
		foreach ( $feeds as $feed_url => $feed ) {
			if ( isset( $feed['parser'] ) && 'friends' === $feed['parser'] ) {
				return $feed_url;
			}
		}

		return false;
	}

		/**
		 * Discover the REST URL for a friend site
		 *
		 * @param  string $url The URL of the site.
		 * @return string|WP_Error The REST URL or an error.
		 */
	public function discover_rest_url( $url ) {
		if ( ! is_string( $url ) || ! Friends::check_url( $url ) ) {
			return new WP_Error( 'invalid-url-given', 'An invalid URL was given.' );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$dom = new DOMDocument();
			set_error_handler( '__return_null' );
			$dom->loadHTML( wp_remote_retrieve_body( $response ) );
			restore_error_handler();

			$xpath = new DOMXpath( $dom );
			foreach ( $xpath->query( '//link[@rel and @href]' ) as $link ) {
				if ( 'friends-base-url' === $link->getAttribute( 'rel' ) ) {
					$rest_url = $link->getAttribute( 'href' );
					if ( is_string( $rest_url ) && Friends::check_url( $rest_url ) ) {
						return $rest_url;
					}
				}
			}
		}

		return null;
	}
		/**
		 * Notify the friend's site via REST about the accepted friend request.
		 *
		 * Accepting a friend request is simply setting the role to "friend".
		 *
		 * @param  int    $user_id   The user id.
		 * @param  string $new_role  The new role.
		 * @param  string $old_roles The old roles.
		 */
	public function notify_remote_friend_request_accepted( $user_id, $new_role, $old_roles ) {
		if ( 'friend' !== $new_role && 'acquaintance' !== $new_role ) {
			return;
		}

		$request_token = get_user_option( 'friends_request_id', $user_id );
		if ( ! $request_token ) {
			// We were accepted, so no need to notify the other.
			return;
		}

		$friend_user = new Friend_User( $user_id );

		$friend_rest_url  = $friend_user->get_rest_url();
		$request_id       = $friend_user->get_user_option( 'friends_request_id' );
		$future_out_token = $friend_user->get_user_option( 'friends_future_out_token' );
		$future_in_token  = wp_generate_password( 128, false );

		$current_user = wp_get_current_user();
		$response     = wp_safe_remote_post(
			$friend_rest_url . '/accept-friend-request',
			array(
				'body'        => array(
					'request'  => $request_id,
					'proof'    => sha1( $future_out_token . $request_id ),
					'key'      => $future_in_token,
					'name'     => $current_user->display_name,
					'icon_url' => get_avatar_url( $current_user->ID ),
				),
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// TODO find a way to message the user.
			return;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! isset( $json->signature ) || sha1( $future_in_token . $future_out_token ) !== $json->signature ) {
			$friend_user->set_role( 'friend_request' );
			// TODO find a way to message the user.
			return;
		}

		$friend_user->make_friend( $future_out_token, $future_in_token );
		$friend_user->delete_user_option( 'friends_request_id' );
		$friend_user->delete_user_option( 'friends_future_out_token' );

		/*
		TODO
		if ( isset( $json->user_icon_url ) ) {
		$this->friends->access_control->update_user_icon_url( $friend_user->ID, $json->user_icon_url );
		}
		When their friend request is no longer valid
		$friend_user->set_role( 'pending_friend_request' );
		if ( isset( $json->friend_request_pending ) ) {
		}
		}
		*/
	}
}
