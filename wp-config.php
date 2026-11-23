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
define( 'DB_NAME', 'boutique' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

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
define( 'AUTH_KEY',         '}1c`&e:O4oBS(R1~Bow_/dd#kgXkh/6a,R4Y/8*bD;:m.Gen<gD.2sk(ULJXlx{^' );
define( 'SECURE_AUTH_KEY',  '@ZKa^2bO[<=pg)b=9pCMo6a1OsDLy2(u9M|BCG?o5(yMy/hgXbRT%?-&zD4%h^eo' );
define( 'LOGGED_IN_KEY',    '3#&Z|I+rzVz)lgu52mUl4^9G55}WOl1: fB-~]o,Xtb=+totE}Dw6BDcxHaM7#$J' );
define( 'NONCE_KEY',        'E0ZVg~*n$D*_S~uX>+JOVT0sTlt*Pc`) lZc;*f`)<awM<9Ph=iJ;N1*d2[wA2]9' );
define( 'AUTH_SALT',        '%T<oTtQ,nU,wViF1X@8eMO ,+,c{ PeNuioa[8]}_=:7gvFVFa<:7}az!Jm`m_UJ' );
define( 'SECURE_AUTH_SALT', 'K-ktR>HNPxqJ`e+r~:lC{d0hg&mn^kUt+s}%zB!$^p`}tt<kF7&K9KPP2Vb<xJa$' );
define( 'LOGGED_IN_SALT',   'Vxrr*cEXu[me$]v#>*X(>6:B~Z,j:8_PF,;TN~M4/xMN|zdOOz5iKb{$wXSM=W)R' );
define( 'NONCE_SALT',       'tqk,^|rP&gxG-gqPexf.>Dzfyzy&BQQ)K,-FX#-)E9=%0,7d6jxgho}t0&2N=[Jf' );

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
$table_prefix = 'wp_';

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
