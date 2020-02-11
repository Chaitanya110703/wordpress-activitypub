<?php
namespace Activitypub;

/**
 * ActivityPub Admin Class
 *
 * @author Matthias Pfefferle
 */
class Admin {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'admin_menu', array( '\Activitypub\Admin', 'admin_menu' ) );
		\add_action( 'admin_init', array( '\Activitypub\Admin', 'register_settings' ) );
		\add_action( 'show_user_profile', array( '\Activitypub\Admin', 'add_fediverse_profile' ) );
		\add_action( 'personal_options_update', array( '\Activitypub\Admin', 'save_profile' ), 11 );
		\add_action( 'edit_user_profile_update', array( '\Activitypub\Admin', 'save_profile' ), 11 );
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$settings_page = \add_options_page(
			'ActivityPub',
			'ActivityPub',
			'manage_options',
			'activitypub',
			array( '\Activitypub\Admin', 'settings_page' )
		);

		\add_action( 'load-' . $settings_page, array( '\Activitypub\Admin', 'add_settings_help_tab' ) );

		$followers_list_page = \add_users_page( \__( 'Followers', 'activitypub' ), __( 'Followers (Fediverse)', 'activitypub' ), 'read', 'activitypub-followers-list', array( '\Activitypub\Admin', 'followers_list_page' ) );

		\add_action( 'load-' . $followers_list_page, array( '\Activitypub\Admin', 'add_followers_list_help_tab' ) );
	}

	/**
	 * Load settings page
	 */
	public static function settings_page() {
		\load_template( \dirname( __FILE__ ) . '/../templates/settings.php' );
	}

	/**
	 * Load user settings page
	 */
	public static function followers_list_page() {
		\load_template( \dirname( __FILE__ ) . '/../templates/followers-list.php' );
	}

	/**
	 * Register ActivityPub settings
	 */
	public static function register_settings() {
		\register_setting(
			'activitypub', 'activitypub_post_content_type', array(
				'type' => 'string',
				'description' => \__( 'Use summary or full content', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'excerpt', 'content' ),
					),
				),
				'default' => 'content',
			)
		);
		\register_setting(
			'activitypub', 'activitypub_object_type', array(
				'type' => 'string',
				'description' => \__( 'The Activity-Object-Type', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'note', 'article', 'wordpress-post-format' ),
					),
				),
				'default' => 'note',
			)
		);
		\register_setting(
			'activitypub', 'activitypub_use_shortlink', array(
				'type' => 'boolean',
				'description' => \__( 'Use the Shortlink instead of the permalink', 'activitypub' ),
				'default' => 0,
			)
		);
		\register_setting(
			'activitypub', 'activitypub_use_hashtags', array(
				'type' => 'boolean',
				'description' => \__( 'Add hashtags in the content as native tags and replace the #tag with the tag-link', 'activitypub' ),
				'default' => 0,
			)
		);
		\register_setting(
			'activitypub', 'activitypub_add_tags_as_hashtags', array(
				'type' => 'boolean',
				'description' => \__( 'Add all tags as hashtags at the end of each activity', 'activitypub' ),
				'default' => 0,
			)
		);
		\register_setting(
			'activitypub', 'activitypub_support_post_types', array(
				'type'         => 'string',
				'description'  => \esc_html__( 'Enable ActivityPub support for post types', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => array( 'post', 'pages' ),
			)
		);
		\register_setting(
			'activitypub', 'activitypub_profile_fields', array(
				'type'         => 'array',
				'description'  => \esc_html__( 'You can have up to 4 items displayed as a table on your profile.', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => array(),
			)
		);
	}

	public static function add_settings_help_tab() {
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'overview',
				'title'   => \__( 'Overview', 'activitypub' ),
				'content' =>
					'<p>' . \__( 'ActivityPub is a decentralized social networking protocol based on the ActivityStreams 2.0 data format. ActivityPub is an official W3C recommended standard published by the W3C Social Web Working Group. It provides a client to server API for creating, updating and deleting content, as well as a federated server to server API for delivering notifications and subscribing to content.', 'activitypub' ) . '</p>',
			)
		);

		\get_current_screen()->set_help_sidebar(
			'<p><strong>' . \__( 'For more information:', 'activitypub' ) . '</strong></p>' .
			'<p>' . \__( '<a href="https://activitypub.rocks/">Test Suite</a>', 'activitypub' ) . '</p>' .
			'<p>' . \__( '<a href="https://www.w3.org/TR/activitypub/">W3C Spec</a>', 'activitypub' ) . '</p>' .
			'<p>' . \__( '<a href="https://github.com/pfefferle/wordpress-activitypub/issues">Give us feedback</a>', 'activitypub' ) . '</p>' .
			'<hr />' .
			'<p>' . \__( '<a href="https://notiz.blog/donate">Donate</a>', 'activitypub' ) . '</p>'
		);
	}

	public static function add_followers_list_help_tab() {
		// todo
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $user
	 * @return void
	 */
	public static function add_fediverse_profile( $user ) {
		\load_template( \dirname( __FILE__ ) . '/../templates/user-settings.php' );
	}

	/**
	 * Save the ActivityPub specific data.
	 *
	 * @param int $user_id
	 * @return void
	 */
	public static function save_profile( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		$profile_fields = array();

        if ( isset( $_POST['activitypub_profile_fields'] ) ) {
			foreach ( $_POST['activitypub_profile_fields'] as $key => $value ) {

			}
		}

		echo "<pre>";
		var_dump($_POST);
	}
}
