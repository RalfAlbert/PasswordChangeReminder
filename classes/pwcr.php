<?php
/**
 * WordPress-Plugin Password Change Reminder
 *
 * PHP version 5.3
 *
 * @category   PHP
 * @package    PwCR
 * @subpackage PwCr\PwCR
 * @author     Ralf Albert <me@neun12.de>
 * @license    GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 * @version    0.1.20131121
 * @link       http://wordpress.com
 */

namespace PwCR;


use PwCR\Options_Handler\Options_Handler;

class PwCR extends Options_Handler
{
	/**
	 * Age of the password
	 * @var string
	 */
	protected $pw_age = null;

	/**
	 * Adding needed hooks&filters
	 */
	public function __construct() {

		// sorry for that. but translation have to be loaded very early or it won't work
		add_action( 'init', array( $this, 'init_translation' ), 1, 0 );

		add_action( 'admin_init', array( $this, 'add_scripts' ), 1, 0 );
		add_action( 'admin_init', array( $this, 'check_pw_age' ), 1, 0 );

		// ajax
		add_action( 'wp_ajax_ignore_nag', array( $this, 'ignore_nag' ), 10, 0 );

		// other
		add_action( 'personal_options_update', array( $this, 'pw_was_updated' ), 1, 1 );
		add_action( 'edit_user_profile_update', array( $this, 'pw_was_updated'), 1, 1 ); // maybe an admin update the pw for you

	}

	/**
	 * Initialize translation
	 */
	public function init_translation() {

		$basename    = plugin_dir_path( dirname( __FILE__ ) );
		$lang_dir_td = basename( $basename ) . '/languages';
		load_plugin_textdomain( 'pwcr_free', false, $lang_dir_td );

	}

	/**
	 * Load JavaScripts on all admin sites
	 */
	public function add_scripts() {

		$min = ( defined( 'SCRIPT_DEBUG' ) && true == SCRIPT_DEBUG ) ? '' : '.min';

		$basename   = plugin_dir_path( dirname( __FILE__ ) );
		$script_dir = basename( $basename ) . "/scripts/pwcr_backend$min.js";
		$script_url = plugins_url( $script_dir  );

		wp_register_script( 'pwcr', $script_url, 'jquery', false, true );

	}

	/**
	 * Simply reset the pw_set_date to the actual date
	 */
	public function pw_was_updated( $user_id = 0 ) {

		$pass1 = $_REQUEST['pass1'];
		$pass2 = $_REQUEST['pass2'];

		// passwords didn't match. WP won't change the password
		// one or both of the passwords are empty
		if ( ( $pass1 != $pass2 ) || empty( $pass1 ) || empty( $pass2 ) )
			return false;

		// check if really a new password was entered
		$user      = get_user_by( 'id', $user_id );
		$is_pw_new = wp_check_password( $pass1, $user->data->user_pass, $user->ID );

		if ( true == $is_pw_new )
			return false;

		// a new password was set. reset the pw_set_date
		$pw_set_date = new \DateTime( 'now' );
		self::set_usermeta( 'pw_set_date', $pw_set_date->format( 'c' ) );

		return true;

	}

	/**
	 * Checks the age of a password and display a nag screen if it is expired
	 */
	public function check_pw_age() {

		// bail if the nag screen is ignored
		if ( true === $this->is_nag_ignored() )
			return false;

		$max_days = self::get_option( 'pw_max_age' );

		// use default value if the maximum age for a pw was not saved
		if ( false == $max_days )
			$max_days = self::get_default_option( 'pw_max_age' );

		// calculate whether the pw is expired
		$date_obj_pw   = new \DateTime( $this->get_pw_set_date() );
		$date_obj_now  = new \DateTime( 'now' );
		$this->pw_age  = $date_obj_pw->diff( $date_obj_now );

		$debug = true;
		if ( $this->pw_age->days > $max_days ) {
			add_action(
				'admin_notices',
				array( $this, 'display_nag' ),
				10,
				0
			);
		}

		return true;

	}

	/**
	 * Get the pw_age from usermeta
	 * - if the field exists and is not empty, check the age of pw
	 * - if the field does not exists, create it with the current date
	 */
	public function get_pw_set_date() {

		$psd = self::get_usermeta( 'pw_set_date' );

		$pw_set_date = ( !empty( $psd ) ) ?
			new \DateTime( $psd ) : false;

		if ( empty( $pw_set_date ) ) {
			$pw_set_date = new \DateTime( 'now' );
			self::set_usermeta( 'pw_set_date', $pw_set_date->format( 'c' ) );
		}

		return $pw_set_date->format( 'c' );

	}

	/**
	 * Check if user ignores the nag
	 */
	public function is_nag_ignored() {

		$user_can_ignore_nag = self::get_option( 'user_can_ignore_nag' );

		// if the user is not allowed to ignore the nag, return false (= always display the nag)
		if ( empty( $user_can_ignore_nag ) )
			return false;

		$ignore_nag_time = self::get_usermeta( 'ignore_nag_time' );		// string; new DateTime() (e.g. 2013-11-15T12:30:51+01:00)
		$max_ignore_time = self::get_option( 'max_ignore_time' );			// string; 00:00 (hh:mm)

		// return false if no timestamp was saved when ignoring the nag screen
		if ( empty( $ignore_nag_time ) )
			return false;

		// use default value if none was saved
		if ( empty( $max_ignore_time ) )
			$max_ignore_time = self::get_default_options( 'max_ignore_time' );

		// calculate whether the time has expired
		$ignore_time     = new \DateTime( $ignore_nag_time );
		$now             = new \DateTime( 'now' );
		$delta           = $now->diff( $ignore_time );

		$timeout         = $delta->format( '%H:%I' ) > $max_ignore_time;
		$more_than_a_day = $delta->d > 0;

		return ( true === $more_than_a_day ||  true === $timeout ) ?
			false : true;

	}

	/**
	 * Save the actual time when the user click on the ignore nag link
	 */
	public function ignore_nag() {

		$now = new \DateTime( 'now' );
		self::set_usermeta( 'ignore_nag_time', $now->format( 'c' ) );

		die(1);

	}

	/**
	 * Display the message on backend
	 */
	public function display_nag() {

		if( true == self::get_option( 'user_can_ignore_nag' ) )
			wp_enqueue_script( 'pwcr' );

		$out  = sprintf( '<h3>%s</h3>', __( 'Your password is outdated!', 'pwcr_free' ) );
		$out .= sprintf( '<p>' . __( 'The password is %d days old. ', 'pwcr_free' ), $this->pw_age->days );

		if ( $this->pw_age->y > 0 ) {
			$inner = sprintf( _n( '1 year.', '%d years.', $this->pw_age->y, 'pwcr_free' ), $this->pw_age->y );
		} elseif ( $this->pw_age->m > 0 ) {
			$inner = sprintf( _n( '1 month.', '%d months.', $this->pw_age->m, 'pwcr_free' ), $this->pw_age->m );
		} else {
			$inner = '';
		}

		if ( !empty( $inner ) )
			$out .= __( 'This means your password is older than ', 'pwcr_free' ) . $inner;

		$out .= '</p>';

		$extra_message = self::get_option( 'extra_message' );
		if ( !empty( $extra_message ) )
			$out .= sprintf( '<p>%s</p>', $extra_message );

		echo '<div class="error" id="pwcr_nag">';
		echo $out;
		echo '<hr>';
		echo $this->create_nag_links();
		echo '</div>';

	}

	/**
	 * Creating links for the nag
	 * @return	string	anonymous	String with links
	 */
	public function create_nag_links() {

		$ignoring_allowed = self::get_option( 'user_can_ignore_nag' );

		$links = array(
				'change_pw' => sprintf( '<a href="%s" id="pwcr_change_pw" class="button">%s</a>', admin_url( '/profile.php' ), __( 'Change password', 'pwcr_free' ) ),
				'ignore'    => sprintf( '<a href="#" id="pwcr_ignore_nag" class="button">%s</a>', __( 'Ignore', 'pwcr_free' ) ),
		);

		// if the user is not allowed to ignore the nag, remove the ignore nag link
		if ( false === $ignoring_allowed )
			unset( $links['ignore'] );

		// if the user is already on the profile page, do not display the link to it
		if ( 'profile.php' === $GLOBALS['pagenow'] )
			unset( $links['change_pw'] );

		$imploded = implode( '  ', $links );

		return sprintf( '<p>%s</p>', $imploded );

	}

}