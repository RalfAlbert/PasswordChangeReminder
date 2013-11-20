<?php
/**
 * WordPress-Plugin Password Change Reminder
 *
 * PHP version 5.4
 *
 * @category   PHP
 * @package    WordPress
 * @subpackage PwCR
 * @author     Ralf Albert <me@neun12.de>
 * @license    GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 * @version    0.1.20131115
 * @link       http://wordpress.com
 */

/**
 * Plugin Name:	Password Change Reminder
 * Plugin URI:	http://yoda.neun12.de
 * Description:	Reminds the user to frequently change the password. Display a nag screen if the password is to old.
 * Version:     0.1.20131120
 * Author:      Ralf Albert
 * Author URI: 	http://yoda.neun12.de
 * Text Domain: pwcr_free
 * Domain Path: /languages
 * Network:     false
 * License:     GPLv3
 */

namespace PwCR;

use PwCR\Options_Handler\Options_Handler;

use PwCR\Backend\Backend;

( ! defined( 'ABSPATH' ) ) AND die( "Standing On The Shoulders Of Giants" );

/**
 * Initialize plugin on plugins_loaded
 * Using the plugis_loaded hook to hook up asap. The contructor of both classes
 * only create their needed hooks&filters.
 *
 */
add_action(
	'plugins_loaded',
	__NAMESPACE__ . '\init',
	10,
	0
);

function init() {

	// load classes
	$classes  = glob( plugin_dir_path( __FILE__ ) . 'classes/*.php' );

	foreach( $classes as $class ) {
		require_once $class;
	}

	new PwCR;
	new Backend;

}

register_activation_hook(
	__FILE__,
	__NAMESPACE__ . '\activate'
);

register_uninstall_hook(
	__FILE__,
	__NAMESPACE__ . '\uninstall'
);

/**
 * On activation:
 * - set default options if not exists
 */
function activate() {

	init();

	$opt_handler = new Options_Handler();
	$defaults    = $opt_handler::get_option();

	// set default options only if no older options are available
	if ( empty( $defaults ) || !is_array( $defaults ) )
		$opt_handler::set_options();

}

/**
 * On uninstall:
 * - remove field 'pw_age' from usermeta
 * - remove options
 */
function uninstall() {

	global $wpdb;

	// remove options
	delete_option( Options_Handler::OPTION_KEY );

	// remove usermeta
	$sql    = $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key = %s;", Options_Handler::USER_META_KEY );
	$result = $wpdb->query( $sql );

}