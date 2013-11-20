<?php
/**
 * WordPress-Plugin Password Change Reminder
 *
 * PHP version 5.3
 *
 * @category   PHP
 * @package    PwCR
 * @subpackage PwCr\Backend
 * @author     Ralf Albert <me@neun12.de>
 * @license    GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 * @version    0.1.20131115
 * @link       http://wordpress.com
 */

namespace PwCR\Backend;

use PwCR\Options_Handler\Options_Handler;

require_once 'options_handler.php';

class Backend extends Options_Handler
{
	/**
	 * Constant for menu slug
	 * @var string
	 */
	const MENU_SLUG = 'pwcr';

	/**
	 * Text files with translations
	 * @var array
	 */
	public $html_files = array();

	/**
	 * Pagehook for the menu page
	 * @var string
	 */
	public $pagehook = '';

	/**
	 * Adding needed hooks&filters
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'init_translation' ), 1, 0 );
		add_action( 'admin_init', array( $this, 'settings_api_init' ), 1, 0 );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 10, 0 );

	}

	/**
	 * Initialize the translation
	 * - Load plugin textdomain
	 * - Read translated text files for backend
	 * @return boolean Always true
	 */
	public function init_translation() {

		// init translation
		$basename    = plugin_dir_path( dirname( __FILE__ ) );
		$lang_dir_tf = $basename . '/languages';

		$lang = ( defined( 'WPLANG' ) ) ?
		    substr( WPLANG, 0, 2 ) : 'en';

		if( is_dir( $lang_dir_tf . '/' . $lang ) )
			$lang_dir_tf .= '/' . $lang . '/';
		else
			$lang_dir_tf .= '/en/';


		$html_files = glob( $lang_dir_tf . '*.{htm,html}', GLOB_BRACE );

		foreach ( $html_files as $file ) {
			preg_match( '#.+/([^/]+)\.html?$#Uuis', $file, $match );

			if ( isset( $match[1] ) && ! empty( $match[1] ) ) {
				$this->html_files[ $match[1] ] = $match[0];
			}
		}

		return true;

	}

	/**
	 * Initialise the WordPress Settings-API
	 * - Register the settings
	 * - Register the sections
	 * - Register the fields for each section
	 * @return boolean Always true
	 */
	public function settings_api_init() {

		// the sections
		$sections = array(
			// section-id => title, callback
			'interval' => array( 'title' => __( 'Interval settings', 'pwcr_free' ), 'callback' => 'interval_section' ),
			'extras'   => array( 'title' => __( 'Extras settings', 'pwcr_free' ), 'callback' => 'extras_section' ),
		);

		// fields for the sections
		$fields = array(
				// field-id => in-section, title, callback
				'field_1'	=> array( 'section' => 'interval', 'title' => __( 'Interval', 'pwcr_free' ), 'callback' => 'interval_field' ),
				'field_2'	=> array( 'section' => 'extras', 'title' => __( 'Ignore Nag', 'pwcr_free' ), 'callback' => 'ignore_field' ),
				'field_3' => array( 'section' => 'extras', 'title' => __( 'Ignore Timeout', 'pwcr_free' ), 'callback' => 'ignore_timeout_field' ),
				'field_4' => array( 'section' => 'extras', 'title' => __( 'Extra Message', 'pwcr_free' ), 'callback' => 'extra_message_field' ),
		);

		// register settings
		register_setting(
		    self::OPTION_KEY,
		    self::OPTION_KEY,
		    array( $this, 'options_validate' )
		);

		// register each section
		foreach ( $sections as $id => $args ) {
			$title    = $args['title'];
			$callback = array( $this, $args['callback'] );

			add_settings_section( $id, $title, $callback, self::MENU_SLUG );
		}

		// register each field in it's section
		foreach ( $fields as $id => $args ) {
			$title    = $args['title'];
			$section  = $args['section'];
			$callback = array( $this, $args['callback'] );

			add_settings_field( $id, $title, $callback,	self::MENU_SLUG, $section );
		}

		return true;

	}

	/**
	 * Add a page to the dashboard-menu
	 * @return boolean Always true
	 */
	public function add_menu_page(){

		if( ! current_user_can( 'manage_options' ) )
			return false;

		$this->pagehook = add_options_page(
		    __( 'PW Change Reminder', 'pwcr_free' ),
		    __( 'PW Change Reminder', 'pwcr_free' ),
		    'manage_options',
		    self::MENU_SLUG,
		    array( $this, 'main_section' ),
		    false,
		    'bottom'
		);

		add_action(
		    'load-'.$this->pagehook,
		    array( $this, 'add_help_tab' ),
		    10,
		    0
		);

		return true;

	}

	/**
	 * Add a help tab to the AvatarPlus options page
	 * @return boolean True if the help tab was created, otherwise false
	 */
	public function add_help_tab() {

		$screen = get_current_screen();

		if( $screen->id !== $this->pagehook )
			return false;

		$screen->add_help_tab(
		    array(
                'id'       => 'pwcr_free',
                'title'    => 'PW Change Reminder',
                'content'  => $this->get_text( 'help_tab_content' ),
            )
		);

		return true;

	}

	/**
	 * Validate saved options
	 * @param array $input Options send
	 * @return array $input Validated options
	 */
	public function options_validate( $input ) {

		$options = self::get_option();
		if ( !is_array( $options ) )
			$options = self::get_default_option();

		$input = array_merge( $options, $input );

		$input['user_can_ignore_nag'] = ( isset( $input['user_can_ignore_nag'] ) && 'on' === $input['user_can_ignore_nag'] ) ? true : false;

		// format pw_max_age: positive integer
		$input['pw_max_age']         = abs( filter_var( $input['pw_max_age'], FILTER_SANITIZE_NUMBER_INT ) );
		$input['pw_max_age_periode'] = ( in_array( (string) $input['pw_max_age_periode'], array( 'days', 'weeks', 'months', 'years' ) ) ) ?
			(string) $input['pw_max_age_periode'] : 'days';

		// convert periode into days
		$convert = array(
				'days'   => 1,
				'weeks'  => 7,
				'months' => 30,
				'years'  => 365
		);

		// calculate days from periode and pw_max_age
		$input['pw_max_age'] = (int) $convert[$input['pw_max_age_periode']] * $input['pw_max_age'];

		// format max_ignore_time: hh:mm; two digits with leading zero
		$input['max_ignore_time_h'] = (int) abs( $input['max_ignore_time_h'] );
		$input['max_ignore_time_m'] = (int) abs( $input['max_ignore_time_m'] );
		$input['max_ignore_time'] = sprintf( '%02d:%02d', $input['max_ignore_time_h'], $input['max_ignore_time_m'] );
		unset( $input['max_ignore_time_h'], $input['max_ignore_time_m'] );

		return $input;

	}

	/**
	 * Return content of a text file
	 * @param string $section Section/identifier of the text file
	 * @return string $anonymous File content
	 */
	public function get_text( $section = '' ) {

		if( empty( $section ) )
			return false;

		return ( isset( $this->html_files[ $section ] ) && file_exists( $this->html_files[ $section ] ) ) ?
			file_get_contents( $this->html_files[ $section ] ) : false;

	}

	/**
	 * Main section of the settings page
	 * @return boolean Always true
	 */
	public function main_section() {

		if( ! current_user_can( 'manage_options' ) )
			return;

		echo '<div class="wrap"><h1>Password Change Reminder</h1>';

		echo $this->get_text( __FUNCTION__ );

		echo '<form action="options.php" method="post">';

		settings_fields( self::OPTION_KEY );
		do_settings_sections( self::MENU_SLUG );

		submit_button( __( 'Save Changes', 'pwcr_free' ), 'primary', 'submit_options', true );

		echo '</form>';
		echo '</div>';

		return true;

	}

	/**
	 * Section for setting the reminder interval
	 * @return boolean Always true
	 */
	public function interval_section() {

		echo $this->get_text( __FUNCTION__ );

		return true;

	}

	/**
	 * Callback for interval field
	 * @return boolean Always true
	 */
	public function interval_field() {

		$max_age         = (int) self::get_option( 'pw_max_age' );
		$max_age_periode = (string) self::get_option( 'pw_max_age_periode' );

		// convert days into periode
		$convert = array(
				'days'   => 1,
				'weeks'  => 7,
				'months' => 30,
				'years'  => 365
		);

		if ( key_exists( $max_age_periode, $convert ) )
			$max_age = $max_age / $convert[$max_age_periode];

    printf(
        '<input type="text" size="1" style="text-align:right" name="%1$s[pw_max_age]" id=name="%1$s-pw_max_age" value="%2$s">',
        self::OPTION_KEY,
        esc_attr( $max_age )
    );

		$option_values = array(
			'days'   => __( 'Day(s)', 'pwcr_free' ),
			'weeks'  => __( 'Week(s)', 'pwcr_free' ),
			'months' => __( 'Month(s)', 'pwcr_free' ),
			'years'  => __( 'Year(s)', 'pwcr_free' )
		);

		$options_output = '';

		$select_skeleton = '<select name="%1$s[pw_max_age_periode]" id="%1$s-pw_max_age_periode">%2$s</select>';

		foreach ( $option_values as $value => $text ) {
			$selected = ( $value === $max_age_periode ) ?
				' SELECTED' : '';

			$options_output .= sprintf( "\t<option value=\"%s\"%s>%s</option>\n", $value, $selected, $text );
		}

		printf( $select_skeleton, self::OPTION_KEY, $options_output );

		return true;

	}

	/**
	 * Section for extra settings
	 * @return boolean Always true
	 */
	public function extras_section() {

		echo $this->get_text( __FUNCTION__ );

		return true;

	}

	/**
	 * Callback for ignoring the nag screen
	 * @return boolean Always true
	 */
	public function ignore_field() {

		$allow_ignore = self::get_option( 'user_can_ignore_nag' );

    printf(
        '<input type="checkbox" name="%1$s[user_can_ignore_nag]" id="%1$s-user_can_ignore_nag"%2$s> <label for="%1$s-user_can_ignore_nag">%3$s</label>',
        self::OPTION_KEY,
        checked( $allow_ignore, true, false ),
        __( 'User can ignore (hide) the nag screen?', 'pwcr_free' )
    );

		return true;

	}

	/**
	 * Callback for ignore timeout field
	 * @return boolean Always true
	 */
	public function ignore_timeout_field() {

		$max_ignore_time = self::get_option( 'max_ignore_time' );
		$hint = __( 'After which periode should an ignored nag screen will be displayed again (hh:mm)', 'pwcr_free' );
		$unit = __( 'hh:mm', 'pwcr_free' );

		$mit = explode( ':', $max_ignore_time );
		if ( !is_array( $mit ) || 2 > sizeof( $mit ) )
			$mit = array( '00', '15' );

		$mit[0] = sprintf( '%02d', $mit[0] );
		$mit[1] = sprintf( '%02d', $mit[1] );

    printf(
        '<input type="text" size="1" style="text-align:right" name="%1$s[max_ignore_time_h]" id=name="%1$s-max_ignore_time_h" value="%2$s">:
         <input type="text" size="1" style="text-align:right" name="%1$s[max_ignore_time_m]" id=name="%1$s-max_ignore_time_m" value="%3$s"> %5$s
         <br><label for="%1$s-max_ignore_time">%4$s</label>',

        self::OPTION_KEY,
        esc_attr( $mit[0] ),
        esc_attr( $mit[1] ),
        $hint,
        $unit
    );

		return true;

	}

	/**
	 * Callback for extra message inside the nag screen
	 * @return boolean
	 */
	public function extra_message_field() {

		$extra_message = self::get_option( 'extra_message' );
		$hint          = __( 'Enter an additional message for the user, it will be displayed in the nag screen.', 'pwcr_free' );
		$hint          .= '<br>';
		$hint          .= __( 'Keep blank for no message. Some HTML is allowed.', 'pwcr_free' );

		printf(
				'<textarea name="%1$s[extra_message]" id="%1$s-extra_message" cols="50" rows="5">%2$s</textarea><br>%3$s',
				self::OPTION_KEY,
				$extra_message,
				$hint
		);

		return true;

	}

}