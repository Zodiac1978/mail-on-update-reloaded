<?php
/*
Plugin Name: Mail On Update
Plugin URI: https://github.com/Zodiac1978/mail-on-update-reloaded
Description: Sends an eMail notification to one or multiple eMail addresses if new versions of plugins are available.
Version: 5.4.14
Author: Sven Kubiak, Matthias Kindler, Torsten Landsiedel
Author URI: https://torstenlandsiedel.de
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: mail-on-update
Domain Path: /languages

Copyright 2022 Torsten Landsiedel
Copyright 2008-2022 Sven Kubiak, Matthias Kindler

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

global $wp_version;
define( 'MOUISWP30', version_compare( $wp_version, '3.0', '>=' ) );
// TODO: Use WP_DEBUG
define( 'DEBUG', false );

if ( ! class_exists( 'MailOnUpdate' ) ) {
	require_once __DIR__ . '/inc/class-mailonupdate.php';
	new MailOnUpdate();
}
