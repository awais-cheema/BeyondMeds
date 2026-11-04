<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'gqniibfoeu_wp842' );

/** Database username */
define( 'DB_USER', 'gqniibfoeu_wp842' );

/** Database password */
define( 'DB_PASSWORD', 'Sph!v![3a8Bv@.64' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'qbsfztz1oa4el6vsvkiyyz9syfnd7utcit6k3fbwiyzdors6tg71m0y2bmxy31ua' );
define( 'SECURE_AUTH_KEY',  'xioka3m1boyk1fhyornl4wam21j7fkovvvv24rzgl2wvrs1ks4pec6jcgw36cdnm' );
define( 'LOGGED_IN_KEY',    'j1rzvuts7o3xc08wnmkrxawkharyzrgb2cyeraytxuabeeztr5lvuxes6eubexzm' );
define( 'NONCE_KEY',        'oje1bbq9jtdchvqfa0ngpi2rtfqfuhcnxipztaq6wl6eh83bl4t8va0ogx9rqgj8' );
define( 'AUTH_SALT',        '9r1vgfxbhbaafs4azlulqbydtt3hit50qettbqn5k3wltu46n3gntw8tzuytjncv' );
define( 'SECURE_AUTH_SALT', '5vpvd15opsudmtdepfij9n30lsgzupso7dvvwxocqhlwwyza4uwuy5y8ckjbjtg0' );
define( 'LOGGED_IN_SALT',   'nddtkoxbnr1hqeg4ageim20vnqp3tzuww45aggv1mu7hwa1aboutta6vcgspwimb' );
define( 'NONCE_SALT',       '11m0rx7yj46liqaigembsr7jh8enfkpljb2wefd5bhot5vtom8cnli7eq8bvahp5' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wpgt_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
