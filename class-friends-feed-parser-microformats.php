<?php
/**
 * Friends microformats Parser Wrapper
 *
 * With this parser, we can import RSS and Atom Feeds for a friend.
 *
 * @package Friends
 */

/**
 * This is the class for the feed part of the Friends Plugin.
 *
 * @since 1.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Feed_Parser_Microformats extends Friends_Feed_Parser {
	/**
	 * Determines if this is a supported feed.
	 *
	 * @param      string $url        The url.
	 * @param      string $mime_type  The mime type.
	 * @param      string $title      The title.
	 *
	 * @return     boolean  True if supported feed, False otherwise.
	 */
	public function is_supported_feed( $url, $mime_type, $title ) {
		switch ( $mime_type ) {
			case 'text/html':
				return true;
		}

		return false;
	}

	/**
	 * Format the feed title and autoselect the posts feed.
	 *
	 * @param      array $feed_details  The feed details.
	 *
	 * @return     array  The (potentially) modified feed details.
	 */
	public function update_feed_details( $feed_details ) {
		$feed_details['title'] = trim( str_replace( array( '&raquo; Feed', '» Feed' ), '', $feed_details['title'] ) );

		foreach ( get_post_format_strings() as $format => $title ) {
			if ( preg_match( '/\b' . preg_quote( $format, '/' ) . '\b/i', $feed_details['url'] ) ) {
				$feed_details['post-format'] = $format;
				break;
			}
		}

		return $feed_details;
	}

	/**
	 * Discover the feeds available at the URL specified.
	 *
	 * @param      string $content  The content for the URL is already provided here.
	 * @param      string $url      The url to search.
	 *
	 * @return     array  A list of supported feeds at the URL.
	 */
	public function discover_available_feeds( $content, $url ) {
		if ( ! function_exists( 'Mf2\parse' ) ) {
			require_once __DIR__ . '/libs/Mf2/Parser.php';
		}

		$mf = Mf2\parse( $content, $url );
		if ( isset( $mf['rel-urls'] ) ) {
			foreach ( $mf['rel-urls'] as $feed_url => $link ) {
				foreach ( array( 'me', 'alternate' ) as $rel ) {
					if ( in_array( $rel, $link['rels'] ) ) {
						$discovered_feeds[ $feed_url ] = array(
							'rel' => $rel,
						);
					}
				}

				if ( ! isset( $discovered_feeds[ $feed_url ] ) ) {
					continue;
				}

				if ( isset( $link['type'] ) ) {
					$discovered_feeds[ $feed_url ]['type'] = $link['type'];
				}
				if ( isset( $link['title'] ) ) {
					$discovered_feeds[ $feed_url ]['title'] = $link['text'];
				}
			}
		}

		if ( isset( $mf['items'] ) ) {
			$discovered_feeds[ $url ] = array(
				'type'        => 'text/html',
				'rel'         => 'self',
				'post-format' => 'autodetect',
			);

			if ( isset( $mf['items'][0]['properties']['name'] ) ) {
				if ( is_array( $mf['items'][0]['properties']['name'] ) ) {
					$discovered_feeds[ $url ]['title'] = reset( $mf['items'][0]['properties']['name'] );
				}
			}
		}

		return $discovered_feeds;
	}

	/**
	 * Parse a h-card.
	 *
	 * @param      array $data      The data.
	 * @param      bool  $category  It is a category.
	 *
	 * @return     string  The discovered value.
	 */
	private function parse_hcard( $data, $category = false ) {
		$name = '';
		$link = '';
		// Check if h-card is set and pass that information on in the link.
		if ( isset( $data['type'] ) && in_array( 'h-card', $data['type'] ) ) {
			if ( isset( $data['properties']['name'][0] ) ) {
				$name = $data['properties']['name'][0];
			}
			if ( isset( $data['properties']['url'][0] ) ) {
				$link = $data['properties']['url'][0];
				if ( '' === $name ) {
					$name = $link;
				} else {
					// can't have commas in categories.
					$name = str_replace( ',', '', $name );
				}
				$person_tag = $category ? '<span class="person-tag"></span>' : '';
				return '<a class="h-card" href="' . $link . '">' . $person_tag . $name . '</a>';
			}
		}
		return isset( $data['value'] ) ? $data['value'] : '';
	}

	/**
	 * Fetches a feed and returns the processed items.
	 *
	 * @param      string $url        The url.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url ) {
		if ( ! function_exists( 'Mf2\parse' ) ) {
			require_once __DIR__ . '/libs/Mf2/Parser.php';
		}

		$mf = Mf2\fetch( $url );
		if ( ! $mf ) {
			// translators: %s is a URL.
			return new Wp_Error( 'microformats Parser', sprintf( __( 'Could not parse %s.', 'friends' ), $url ) );
		}

		$feed_items = array();
		$entries = array();

		// The following section is adapted from the SimplePie source.
		$h_feed = array();
		foreach ( $mf['items'] as $mf_item ) {
			if ( in_array( 'h-feed', $mf_item['type'] ) ) {
				$h_feed = $mf_item;
				break;
			}
			// Also look for h-feed or h-entry in the children of each top level item.
			if ( ! isset( $mf_item['children'][0]['type'] ) ) {
				continue;
			}
			if ( in_array( 'h-feed', $mf_item['children'][0]['type'] ) ) {
				$h_feed = $mf_item['children'][0];
				// In this case the parent of the h-feed may be an h-card, so use it as
				// the feed_author.
				if ( in_array( 'h-card', $mf_item['type'] ) ) {
					$feed_author = $mf_item;
				}
				break;
			} elseif ( in_array( 'h-entry', $mf_item['children'][0]['type'] ) ) {
				$entries = $mf_item['children'];
				// In this case the parent of the h-entry list may be an h-card, so use
				// it as the feed_author.
				if ( in_array( 'h-card', $mf_item['type'] ) ) {
					$feed_author = $mf_item;
				}
				break;
			}
		}

		if ( isset( $h_feed['children'] ) ) {
			$entries = $h_feed['children'];
		} elseif ( empty( $entries ) ) {
			$entries = $mf['items'];
		}

		foreach ( $entries as $entry ) {
			if ( isset( $entry['properties']['deleted'][0] ) || ! isset( $entry['properties']['published'][0] ) ) {
				continue;
			}

			$item = array(
				'date' => gmdate( 'Y-m-d H:i:s', strtotime( $entry['properties']['published'][0] ) ),
			);

			if ( isset( $entry['properties']['url'][0] ) ) {
				$link = $entry['properties']['url'][0];
				if ( isset( $link['value'] ) ) {
					$link = $link['value'];
				}
				$item['permalink'] = array( array( 'data' => $link ) );
			}

			if ( isset( $entry['properties']['uid'][0] ) ) {
				$guid = $entry['properties']['uid'][0];
				if ( isset( $guid['value'] ) ) {
					$guid = $guid['value'];
				}
				$item['permalink'] = $guid;
			}

			if ( isset( $entry['properties']['name'][0] ) ) {
				$title = $entry['properties']['name'][0];
				if ( isset( $title['value'] ) ) {
					$title = $title['value'];
				}
				$item['title'] = $title;
			}

			if ( isset( $entry['properties']['photo'][0] ) ) {
				// If a photo is also in content, don't need to add it again here.
				$content = '';
				if ( isset( $entry['properties']['content'][0]['html'] ) ) {
					$content = $entry['properties']['content'][0]['html'];
				}
				$photo_list = array();
				for ( $j = 0; $j < count( $entry['properties']['photo'] ); $j++ ) {
					$photo = $entry['properties']['photo'][ $j ];
					if ( ! empty( $photo ) && strpos( $content, $photo ) === false ) {
						$photo_list[] = $photo;
					}
				}
				// When there's more than one photo show the first and use a lightbox.
				// Need a permanent, unique name for the image set, but don't have
				// anything unique except for the content itself, so use that.
				$count = count( $photo_list );
				if ( $count > 1 ) {
					$image_set_id = preg_replace( '/[[:^alnum:]]/', '', $photo_list[0] );
					$content = '<p>';
					foreach ( $photo_list as $j => $photo ) {
						$hidden = 0 === $j ? '' : 'class="hidden" ';
						$content .= '<a href="' . $photo . '" ' . $hidden .
							'data-lightbox="image-set-' . $image_set_id . '">' .
							'<img src="' . $photo . '"></a>';
					}
					$content .= '<br><b>' . $count . ' photos</b></p>';
				} elseif ( 1 === $count ) {
					$content = '<p><img src="' . $photo_list[0] . '"></p>';
				}
				$item['post-format'] = 'photo';
			}
			if ( isset( $entry['properties']['content'][0]['html'] ) ) {
				// e-content['value'] is the same as p-name when they are on the same
				// element. Use this to replace title with a strip_tags version so
				// that alt text from images is not included in the title.
				if ( $entry['properties']['content'][0]['value'] === $title ) {
					$title = strip_tags( $entry['properties']['content'][0]['html'] );
					$item['title'] = $title;
				}
				$content .= $entry['properties']['content'][0]['html'];
				if ( isset( $entry['properties']['in-reply-to'][0] ) ) {
					$in_reply_to = '';
					if ( is_string( $entry['properties']['in-reply-to'][0] ) ) {
						$in_reply_to = $entry['properties']['in-reply-to'][0];
					} elseif ( isset( $entry['properties']['in-reply-to'][0]['value'] ) ) {
						$in_reply_to = $entry['properties']['in-reply-to'][0]['value'];
					}
					if ( '' !== $in_reply_to ) {
						$content .= '<p><span class="in-reply-to"></span> ' .
							'<a href="' . $in_reply_to . '">' . $in_reply_to . '</a><p>';
					}
				}
				$item['content'] = $content;
			}

			if ( isset( $entry['properties']['category'] ) ) {
				$category_csv = '';
				// Categories can also contain h-cards.
				foreach ( $entry['properties']['category'] as $category ) {
					if ( '' !== $category_csv ) {
						$category_csv .= ', ';
					}
					if ( is_string( $category ) ) {
						// Can't have commas in categories.
						$category_csv .= str_replace( ',', '', $category );
					} else {
						$category_csv .= $this->parse_hcard( $category, true );
					}
				}
				$item['category'] = $category_csv;
			}

			$feed_items[] = (object) $item;
		}

		return $feed_items;
	}
}