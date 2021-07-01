<?php
/**
 * Class Fake_HTTP_Requests_Response
 *
 * @package Friends
 */

/**
 * Minimal implementation of WP_HTTP_Requests_Response for use in a fake response.
 */
class Fake_HTTP_Requests_Response {
	/**
	 * The fake response array.
	 *
	 * @var array
	 */
	private $response;

	/**
	 * Constructs a new instance.
	 *
	 * @param      array $response  The response.
	 */
	public function __construct( array $response ) {
		$this->response = $response;
	}

	/**
	 * Gets the response object.
	 *
	 * @return     object  The response object.
	 */
	public function get_response_object() {
		return (object) $this->response;
	}
}
