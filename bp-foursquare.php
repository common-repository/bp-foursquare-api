<?php
/*
Plugin Name: BP Foursquare API
Plugin URI: http://wordpress.org/extend/plugins/foursquare-api/
Description: Adds ability to use Foursquare functions to your WordPress blog
Version: 0.1
Author: John James Jacoby
Author URI: http://johnjamesjacoby.com
Tags: Foursquare, geolocation, geotagging, buddypress
*/

/**
 * Smart load when BuddyPress is around
 */
if ( defined( 'BP_VERSION' ) )
	bp_4sq_wrapper();
else
	add_action( 'bp_init', 'bp_4sq_wrapper' );

/**
 * BuddyPress specific code loading
 */
function bp_4sq_wrapper() {
	require_once ( './bp-foursquare-classes' );
}

?>
