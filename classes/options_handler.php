<?php
/**
 * WordPress-Plugin Password Change Reminder
 *
 * PHP version 5.3
 *
 * @category   PHP
 * @package    PwCR
 * @subpackage PwCr\Options_Handler
 * @author     Ralf Albert <me@neun12.de>
 * @license    GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 * @version    0.2.20131123
 * @link       http://wordpress.com
 */

namespace PwCR\Options_Handler;

class Options_Handler
{
	/**
	 * Constant for option key
	 * @var string
	 */
	const OPTION_KEY = 'PwCR';

	/**
	 * Contant for meta_key in usermeta
	 * @var string
	 */
	const USER_META_KEY = 'PwCR_user_meta';

	/**
	 * Option values from db table options
	 * @var array
	 */
	public static $options = array();

	/**
	 * Userdata from db table usermeta
	 * @var array
	 */
	public static $umeta = array();

	/**
	 * Returns a single option or all options for this plugin from db
	 * @param string $what Name of the option to retrieve (optional)
	 * @return mixed anonymous Depending on the option name, the single option if available or all option values if no option name was given
	 */
	public static function get_option( $what = '' ) {

		// get options from db if not already done
		if ( empty( self::$options ) ) {
			self::$options = get_option( self::OPTION_KEY );

			// if the options doesn't exists, create an array
			if ( !is_array( self::$options ) )
				self::$options = array();

		}

		// return single option
		return ( !empty( $what ) && key_exists( $what, self::$options) ) ?
			self::$options[$what] : null;

		// return all options
		return self::$options;

	}

	/**
	 * Returns a single default option or all default options if no option was specified
	 * @param string $what Name of the option (optional)
	 * @return mixed anonymous Depending on single option
	 */
	public static function get_default_option( $what = '' ) {

		$default_options = array(
				'pw_max_age'          => 90,		        // integer; days
				'pw_max_age_periode'  => 'days',        // string (one of days, weeks, months, years)
				'extra_message'       => __( 'We expect that the password is changed at least every 3 months.', 'pwchangereminder' ),
				'user_can_ignore_nag' => true,          // boolean
				'max_ignore_time'     => '00:15',		    // in hh:mm; 2 digits w. leading 0
				'frontend_allowed'    => true,          // true if the nag scrteen should also been displayed on frontend
		);

		// return single option
		if ( !empty( $what ) && key_exists( $what, $default_options ) )
			return $default_options[$what];

		// return all options
		return $default_options;

	}

	/**
	 * Set the default options and return them as array
	 * @return bool true
	 */
	public static function set_options() {

		$default_options = self::get_default_option();

		add_option( self::OPTION_KEY, $default_options );

		return true;

	}

	/**
	 * Returens a single value from the user meta. Returns null if the meta is not set or not found
	 * @param string $what Name of the meta-data to retrieve
	 * @return mixed anonymous Meta value if set, else null
	 */
	public static function get_usermeta( $what = '' ) {

		if ( empty( $what ) )
			return null;

		$user_meta = get_user_meta( get_current_user_id(), self::USER_META_KEY, true );

		if ( !is_array( $user_meta ) )
			$user_meta = array();

		return key_exists( $what, $user_meta) && isset( $user_meta[$what] ) ?
			$user_meta[$what] : null;

	}

	/**
	 * Set a value in the usermeta of the current user
	 * @param string $what Name of the value
	 * @param string $value Value itself
	 * @return boolean
	 */
	public static function set_usermeta( $what = '', $value = null ) {

		if ( empty( $what ) )
			return false;

		$defaults = array(
				'pw_set_date'     => new \DateTime( 'now' ),    // set pw_set_date to actual date on activation
				'ignore_nag_time' => false,                     // user does not have ignored the nag
		);

		if ( null !== $value ) {
			$user_id         = get_current_user_id();
			$usermeta        = get_user_meta( $user_id, self::USER_META_KEY, true );

			if ( empty( $usermeta ) ) {
				$defaults[$what] = $value;
				add_user_meta( $user_id, self::USER_META_KEY, $defaults, true );
			} else {
				$usermeta[$what] = $value;
				update_user_meta( $user_id, self::USER_META_KEY, $usermeta );
			}

			return true;
		}

		return false;

	}
}
