<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends
 * Version: 1.8.1
 *
 * Description: Decentralized Social Networking with WordPress. Connect WordPresses through friend requests and read each other’s (private) posts in a feed reader.
 *
 * License: GPL2
 * Text Domain: friends
 * Domain Path: /languages/
 *
 * @package Friends
 */

/**
 * This file loads all the dependencies the Friends plugin.
 */

defined( 'ABSPATH' ) || exit;
define( 'FRIENDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FRIENDS_PLUGIN_FILE', plugin_dir_path( __FILE__ ) . '/' . basename( __FILE__ ) );

require_once __DIR__ . '/libs/Mf2/Parser.php';
require_once __DIR__ . '/libs/gutenberg-everywhere/classes/gutenberg-handler.php';
require_once __DIR__ . '/includes/class-gutenberg-everywhere-friends-message.php';

require_once __DIR__ . '/includes/class-friend-user.php';
require_once __DIR__ . '/includes/class-friend-user-feed.php';
require_once __DIR__ . '/includes/class-friend-user-query.php';

// Classes to be implemented or used by parser plugins.
require_once __DIR__ . '/feed-parsers/class-friends-feed-parser.php';
require_once __DIR__ . '/feed-parsers/class-friends-feed-item.php';

require_once __DIR__ . '/includes/class-friends-access-control.php';
require_once __DIR__ . '/includes/class-friends-admin.php';
require_once __DIR__ . '/includes/class-friends-automatic-status.php';
require_once __DIR__ . '/includes/class-friends-blocks.php';
require_once __DIR__ . '/includes/class-friends-feed.php';
require_once __DIR__ . '/includes/class-friends-frontend.php';
require_once __DIR__ . '/includes/class-friends-logging.php';
require_once __DIR__ . '/includes/class-friends-messages.php';
require_once __DIR__ . '/includes/class-friends-notifications.php';
require_once __DIR__ . '/includes/class-friends-plugin-installer.php';
require_once __DIR__ . '/includes/class-friends-reactions.php';
require_once __DIR__ . '/includes/class-friends-rest.php';
require_once __DIR__ . '/includes/class-friends-shortcodes.php';
require_once __DIR__ . '/includes/class-friends-template-loader.php';
require_once __DIR__ . '/includes/class-friends-3rd-parties.php';
require_once __DIR__ . '/includes/class-friends.php';

add_action( 'plugins_loaded', array( 'Friends', 'init' ) );
add_action( 'admin_init', array( 'Friends_Plugin_Installer', 'register_hooks' ) );
register_activation_hook( __FILE__, array( 'Friends', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( 'Friends', 'deactivate_plugin' ) );
register_uninstall_hook( __FILE__, array( 'Friends', 'uninstall_plugin' ) );

// Register widgets.
require_once __DIR__ . '/widgets/class-friends-widget-refresh.php';
add_action( 'widgets_init', array( 'Friends_Widget_Refresh', 'register' ) );

require_once __DIR__ . '/widgets/class-friends-widget-friend-list.php';
add_action( 'widgets_init', array( 'Friends_Widget_Friend_List', 'register' ) );

require_once __DIR__ . '/widgets/class-friends-widget-friend-request.php';
add_action( 'widgets_init', array( 'Friends_Widget_Friend_Request', 'register' ) );

require_once __DIR__ . '/widgets/class-friends-widget-new-private-post.php';
add_action( 'widgets_init', array( 'Friends_Widget_New_Private_Post', 'register' ) );

require_once __DIR__ . '/widgets/class-friends-widget-post-formats.php';
add_action( 'widgets_init', array( 'Friends_Widget_Post_Formats', 'register' ) );

require_once __DIR__ . '/widgets/class-friends-widget-header.php';
add_action( 'widgets_init', array( 'Friends_Widget_Header', 'register' ) );

// Register bundled parsers.
add_action(
	'friends_register_parser',
	function( Friends_Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-friends-feed-parser-simplepie.php';
		$friends_feed->register_parser( 'simplepie', new Friends_Feed_Parser_SimplePie );
	}
);

add_action(
	'friends_register_parser',
	function( Friends_Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-friends-feed-parser-microformats.php';
		$friends_feed->register_parser( 'microformats', new Friends_Feed_Parser_Microformats );
	}
);

add_action(
	'friends_register_parser',
	function( Friends_Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-friends-feed-parser-json-feed.php';
		$friends_feed->register_parser( 'jsonfeed', new Friends_Feed_Parser_JSON_Feed );
	}
);
