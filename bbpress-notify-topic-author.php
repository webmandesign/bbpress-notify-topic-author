<?php if ( ! defined( 'WPINC' ) ) exit;

/**
 * Plugin Name:        bbPress Notify Topic Author
 * Plugin URI:         https://github.com/webmandesign/bbpress-notify-topic-author
 * Description:        Sends notification email to topic author when a topic is created.
 * Version:            1.0.0
 * Author:             WebMan Design - Oliver Juhas
 * Author URI:         https://www.webmandesign.eu
 * License:            GNU General Public License v3
 * License URI:        http://www.gnu.org/licenses/gpl-3.0.txt
 * Requires at least:  5.0
 * Tested up to:       5.2
 * GitHub Plugin URI:  webmandesign/bbpress-notify-topic-author
 */
class WM_bbP_Notify_Topic_Author {

	public static $option_page    = 'bbpress';
	public static $options        = [];
	public static $option_section = [];



	/**
	 * Initialization.
	 *
	 * @since    1.0.0
	 * @version  1.0.0
	 */
	public static function init() {

		// Requirements check

			if ( ! class_exists( 'bbPress' ) ) {
				return;
			}


		// Variables

			self::$option_section = [
				'id'    => 'bbp_settings_topic_notices',
				'title' => esc_html__( 'Topic Notifications', 'bbpress' ),
			];

			self::$options = [

				'message' => [
					'id'                => '_bbp_topic_author_notice_message',
					'label'             => esc_html__( 'Email body (topic author)', 'bbpress' ),
					'description'       => esc_html__( 'Email message sent to topic author when a new topic is created.', 'bbpress' )
					                       . '<br>'
					                       . sprintf(
					                       	esc_html__( 'Use %s tags.', 'bbpress' ),
					                       	'<code>{author}</code>, <code>{content}</code>, <code>{title}</code>, <code>{url}</code>'
					                       ),
					'field_type'        => 'textarea',
					'sanitize_callback' => 'wp_kses_post',
					'default'           => '{author} wrote:' . PHP_EOL . '{content}' . PHP_EOL . 'Post Link: {url}',
				],

				'subject'  => [
					'id'                => '_bbp_topic_author_notice_subject',
					'label'             => esc_html__( 'Email subject (topic author)', 'bbpress' ),
					'description'       => esc_html__( 'The subject of the notification email sent to topic author.', 'bbpress' )
					                       . '<br>'
					                       . sprintf(
					                       	esc_html__( 'Use %s tags.', 'bbpress' ),
					                       	'<code>{author}</code>, <code>{content}</code>, <code>{title}</code>, <code>{url}</code>'
					                       ),
					'field_type'        => 'text',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '[' . get_option( 'blogname' ) . '] {title}',
				],

			];


		// Processing

			add_action( 'admin_init', __CLASS__ . '::options', 100 );
			add_action( 'bbp_new_topic', __CLASS__ . '::send_email', 11, 4 );

	} // /init



	/**
	 * Adapted from `bbp_notify_forum_subscribers()` function.
	 *
	 * @since    1.0.0
	 * @version  1.0.0
	 *
	 * @param  int     $topic_id
	 * @param  int     $forum_id
	 * @param  boolean $anonymous_data
	 * @param  int     $topic_author
	 */
	public static function send_email( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0 ) {

		// Requirements check

			if ( ! bbp_is_subscriptions_active() ) {
				return false;
			}


		// Variables

			$args = (array) apply_filters( 'wm_bbp_notify_topic_author/args', [
				'forum_id' => bbp_get_forum_id( $forum_id ),
				'headers'  => [ 'From: ' . get_bloginfo( 'name' ) . ' <' . bbp_get_do_not_reply_address() . '>' ],
				'to'       => get_userdata( $topic_author )->user_email,
				'topic_id' => bbp_get_topic_id( $topic_id ),
			], $topic_id, $forum_id, $topic_author );

			$args['message'] = self::get_text( $args['topic_id'], 'message' );
			$args['subject'] = self::get_text( $args['topic_id'], 'subject' );


		// Requirements check

			if (
				! bbp_is_topic_published( $args['topic_id'] )
				|| empty( $args['message'] )
				|| empty( $args['subject'] )
				|| ! is_email( $args['to'] )
			) {
				return fasle;
			}


		// Processing

			wp_mail(
				$args['to'],
				$args['subject'],
				$args['message'],
				$args['headers']
			);


		// Output

			return true;

	} // /send_email



	/**
	 * Get email message or subject text.
	 *
	 * @since    1.0.0
	 * @version  1.0.0
	 *
	 * @param  int    $topic_id
	 * @param  string $scope
	 */
	public static function get_text( $topic_id = 0, $scope = '' ) {

		// Requirements check

			if ( ! isset( self::$options[ $scope ] ) ) {
				return '';
			}


		// Output

			return strtr(
				(string) get_option( self::$options[ $scope ]['id'] ),
				[
					'{author}'  => bbp_get_topic_author_display_name( $topic_id ),
					'{content}' => wp_strip_all_tags( bbp_get_topic_content( $topic_id ) ),
					'{title}'   => wp_strip_all_tags( bbp_get_topic_title( $topic_id ) ),
					'{url}'     => bbp_get_topic_permalink( $topic_id ),
				]
			);

	} // /get_text



	/**
	 * Setting options.
	 *
	 * @since    1.0.0
	 * @version  1.0.0
	 */
	public static function options() {

		// Processing

			if ( ! isset( $GLOBALS['wp_settings_sections'][ self::$option_page ][ self::$option_section['id'] ] ) ) {
				add_settings_section(
					self::$option_section['id'],
					self::$option_section['title'],
					'__return_empty_string',
					self::$option_page
				);
			}

			foreach ( self::$options as $id => $args ) {

				add_settings_field(
					$args['id'],
					$args['label'],
					__CLASS__ . '::option_fields',
					self::$option_page,
					self::$option_section['id'],
					array(
						'default'     => $args['default'],
						'description' => $args['description'],
						'field_type'  => $args['field_type'],
						'label'       => $args['label'],
						'option_name' => $args['id'],
					)
				);

				register_setting(
					self::$option_page,
					$args['id'],
					array(
						'default'           => $args['default'],
						'description'       => $args['description'],
						'sanitize_callback' => $args['sanitize_callback'],
						'type'              => 'string',
					)
				);

			}

	} // /options



	/**
	 * Render option fields.
	 *
	 * @since    1.0.0
	 * @version  1.0.0
	 *
	 * @param  array $args
	 */
	public static function option_fields( $args ) {

		// Requirements check

			if (
				! isset( $args['option_name'] )
				|| ! isset( $args['field_type'] )
			) {
				return;
			}


		// Variables

			$default = isset( $args['default'] ) ? ( $args['default'] ) : ( '' );
			$value   = bbp_get_form_option( $args['option_name'], $default );


		// Output

			switch ( $args['field_type'] ) {

				case 'textarea':
					?>

					<textarea
						name="<?php echo esc_attr( $args['option_name'] ); ?>"
						class="large-text"
						rows="15"
						id="<?php echo esc_attr( $args['option_name'] ); ?>"
						><?php

					echo esc_textarea( $value );

					?></textarea>

					<?php
					break;

				default:
					?>

					<input
						name="<?php echo esc_attr( $args['option_name'] ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						type="text"
						class="large-text"
						id="<?php echo esc_attr( $args['option_name'] ); ?>"
						/>

					<?php
					break;

			}

			if ( isset( $args['description'] ) ) {
				echo '<br><label for="' . esc_attr( $args['option_name'] ) . '">' . $args['description'] . '</label>';
			}

	} // /option_fields

} // /WM_bbP_Notify_Topic_Author

add_filter( 'init', 'WM_bbP_Notify_Topic_Author::init' );
