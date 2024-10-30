<?php

/*	Props Romain Biard for original work - http://romainbiard.eu - @biskuit

	There are 7 main types of methods
		- Geo Methods
		- Check-in Methods
		- User Methods
		- Venue Methods
		- Tips Methods
		- Other Methods
		- Test Method

	Foursquare API returns either an error code, or in success cases XML or JSON.

	This class uses the v1 of Foursquare's API.

	When you create a new object, put your credentials as a query string
	of parameters, or as an array:
		Example1 : foursquare = new BP_Foursquare( 'login=foo&pass=bar&format=json' );
		Example2 : foursquare = new BP_Foursquare( array( 'login' => 'foo', 'pass' => 'bar', 'format' => 'json' ) );

	Then call a function simlar to above:
		Example : $json = $foursquare->history( 'limit=1&sinceid=' );
*/

if ( !defined( 'BP_4SQ_FILTER' ) )
	define( 'BP_4SQ_FILTER', 'bp_foursquare_' );

if ( !defined( 'BP_4SQ_API' ) )
	define( 'BP_4SQ_API', 'api.foursquare.com/v1' );

if ( !defined( 'BP_4SQ_FORMAT' ) )
	define( 'BP_4SQ_FORMAT', 'json' );

/**
 * Initialize default Foursquare connection
 */
function bp_4sq( $login, $pw, $format = '' ) {
	$fsq = new BP_Foursquare( $login, $pw );
	$fsq->format = $format ? $format : BP_4SQ_FORMAT;
	return $fsq;
}

/**
 * Initialize and return json Foursquare connection
 */
function bp_4sq_json( $login, $pw ) {
	return bp_4sq( $login, $pw, 'json' );
}

/**
 * Initialize and return xml Foursquare connection
 */
function bp_4sq_xml( $login, $pw ) {
	return bp_4sq( $login, $pw, 'xml' );
}

/**
 *  Main BuddyPress Foursquare class
 */
class BP_Foursquare {

	var $api;		// 'api.foursquare.com/v1'
	var $format;	// 'xml' or 'json'
	var $method;	// Built at runtime
	var $response;	// Stores api response
	var $login;		// API Login
	var $pass;		// API Password

	/**
	 * Instantionationalinator
	 */
	function bp_foursquare( $args = null ) {

		$defaults = array(
			'api'		=> BP_4SQ_API,
			'format'	=> BP_4SQ_FORMAT,
			'method'	=> null,
			'response'	=> null,
			'login'		=> null,
			'pass'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->api		= $api;
		$this->format	= $format;
		$this->method	= $method;
		$this->response	= $response;
		$this->login	= $login;
		$this->pass		= $pass;
	}

	/**
	 * Connect to the API
	 */
	function connect () {
		$api_call = clean_url( 'http://' . $this->api . $this->method );

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $api_call );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/4.0' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_USERPWD, $this->login . ':' . $this->pass );

		$connect = curl_exec( $ch );
		curl_close( $ch );
		unset ( $ch );

		return $connect;
	}

	/**
	 * Used through-out class to check values
	 */
	function checkval ( $value = null ) {
		if ( isset( $value ) && $value != null )
			return true;

		return false;
	}

	/**
	 * Apply the filter and return the value
	 */
	function get_response ( $filter ) {
		$this->method	= apply_filters( $filter, $this->method );
		$this->response = $this->connect();

		return $this->response;
	}

	/**
	 * Returns a list of recent checkins from friends
	 *
	 * If you pass in a geolat/geolong pair (optional, but recommended),
	 * we'll send you back a <distance> inside each <checkin> object
	 * that you can use to sort your results.
	 *
	 * Parameters
	 * geolat:	(optional, but recommended)
	 * geolong:	(optional, but recommended)
	*/
	function checkins ( $args = null ) {

		$defaults = array(
			'geolat'		=> null,
			'geolong'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/checkins.' . $this->format . '?';

		if ( $this->checkval( $geolat ) )
			$this->method .= '&geolat=' . $geolat;

		if ( $this->checkval( $geolong ) )
			$this->method .= '&geolong=' . $geolong;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Allows you to check-in to a place
	 *
	 * A <mayor>  block will be returned if there's any mayor information
	 * for this place. It'll include a node <type>  which has the following
	 * values: new (the user has been appointed mayorship), nochange
	 * (the previous mayorship is still valid), stolen (the user stole
	 * mayorship from the previous mayor).
	 *
	 * A <specials>  block will be returned if there are any specials
	 * associated with this check-in. It'll include subnodes <special>
	 * which may have various types. The types can be one of: mayor, count,
	 * frequency, or other. If the special is at a nearby venue
	 * (instead of at the currently checked-into venue), you'll see a
	 * <venue> node inside <special>  that will highlight the nearby venue.
	 *
	 * Parameters
	 * vid:		(optional)	not necessary on 'shout' or have a venue name).
	 *						ID of the venue where you want to check-in
	 *
	 * venue:	(optional)	not necessary on 'shouting' or have a vid
	 *						if you don't have a venue ID or would rather prefer a 'venueless'
	 *						checkin pass the venue name as a string using this parameter. it
	 *						will become an 'orphan' (no address or venueid but with geolat, geolong)
	 *
	 * shout:	(optional)	a message about your check-in. the maximum length
	 *						of this field is 140 characters
	 *
	 * private: (optional)	"1" means "don't show your friends". "0" means "show everyone"
	 *
	 * twitter:	(optional)	"1" means "send to Twitter". "0" means "don't send to Twitter"
	 *						defaults to the user's setting
	 *
	 * facebook:(optional)	"1" means "send to Facebook". "0" means "don't send to Facebook"
	 *						defaults to the user's setting
	 *
	 * geolat:	(optional, but recommended)
	 * geolong:	(optional, but recommended)
	*/
	function checkin ( $args = null ) {

		$defaults = array(
			'vid'			=> null,
			'venue'			=> null,
			'shout'			=> null,
			'private'		=> null,
			'twitter'		=> null,
			'facebook'		=> null,
			'geolat'		=> null,
			'geolong'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/checkin.' . $this->format . '?';

		if ( $this->checkval( $vid ) && ( $vid > 0 ) )
			$this->method .= '&vid=' . $vid;

		if ( $this->checkval( $venue ) && ( $venue > 0 ) )
			$this->method .= '&venue=' . $venue;

		if ( $this->checkval( $shout ) && strlen( $shout ) > 140 )
			$this->method .= '&shout=' . $shout;

		if ( $this->checkval( $private ) && ( $private == 0 || $private == 1 ) )
			$this->method .= '&private=' . $private;

		if ( $this->checkval( $twitter ) && ( $twitter == 0 || $twitter == 1 ) )
			$this->method .= '&twitter=' . $twitter;

		if ( $this->checkval( $facebook ) && ( $facebook == 0 || $facebook == 1 ) )
			$this->method .= '&facebook=' . $facebook;

		if ( $this->checkval( $geolat ) )
			$this->method .= '&geolat=' . $geolat;

		if ( $this->checkval( $geolong ) )
			$this->method .= '&geolong=' . $geolong;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Returns a history of checkins for the authenticated user
	 * (across all cities)
	 *
	 * Parameters
	 * limit:	(optional)	is the limit of results (default: 20)
	 *						number of checkins to return
	 *
	 * sinceid:	(optional)	id to start returning results from
	 *						if omitted returns most recent results
	 */
	function history ( $args = null ) {

		$defaults = array(
			'limit'			=> null,
			'sinceid'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/history.' . $this->format . '?';

		if ( $this->checkval( $limit ) && ( $limit > 0 ) )
			$this->method .= '&l=' . $limit;

		if ( $this->checkval( $sinceid ) && ( $sinceid > 0 ) )
			$this->method .= '&sinceid=' . $sinceid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Returns profile information (badges, etc) for a given user
	 *
	 * If the user has recent check-in data (ie, if the user is self or is
	 * a friend of the authenticating user), this data will be returned as
	 * well in a <checkin> block.
	 *
	 * If the user requested is 'self' (ie, the authenticating user), a
	 * <settings> block with defaults will be returned. This settings block
	 * includes attributes like <sendtotwitter>, <sendtofacebook> and the
	 * user's RSS/KML private feeds key. sendtotwitter will indicate whether the
	 * default action is to tweet check-in information to Twitter (the possible
	 * values are true and false). sendtofacebook will indicate whether the
	 * default action is to send check-in information to the user's Facebook
	 * news feed. pings will indicate whether the user will receive check-in
	 * notification pings from client apps (iPhone, Android, Blackberry, ...).
	 *
	 * The possible values for pings are: on (send pings), off (don't send pings)
	 * and goodnight  (don't send pings again until 7AM in the user's current timezone).
	 *
	 * Lastly, if the user is a friend of the authenticating user, you'll have
	 * access to the requested user's phone, email, Twitter and Facebook ID.
	 * In addition to this, you'll also see a setting get_pings. get_pings will
	 * indicate whether the authenticating user is setup to receive check-in
	 * pings from the friend (push notifications, etc). The possible values for
	 * get_pings are true and false.
	 *
	 * Parameters
	 * uid:		(optional)	userid for the user whose information you want
	 *						to retrieve. If you do not specify a 'uid', the
	 *						authenticated user's profile data will be returned.
	 * mayor:	(optional)	set to true ("1") to also show venues for which
	 *						this user is a mayor. by default, this will show
	 *						mayorships worldwide. (default: false)
	 * badges:	(optional)	set to true ("1") to also show badges for this user.
	 *						By default, this will only show badges from the
	 *						authenticated user's current city. (default: false)
	 */
	function user_details ( $args = null ) {

		$defaults = array(
			'uid'			=> null,
			'mayor'			=> null,
			'badges'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/user.' . $this->format . '?';

		if ( $this->checkval( $uid ) && ( $uid > 0 ) )
			$this->method .= '&uid=' . $uid;

		if ( $this->checkval( $mayor ) && ( $mayor == 0 || $mayor == 1 ) )
			$this->method .= '&mayor=' . $mayor;

		if ( $this->checkval( $badges ) && ( $badges ==0 || $badges == 1 ) )
			$this->method .= '&badges=' . $badges;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Returns a list of friends
	 *
	 * If you do not specify uid, the authenticating
	 * user's list of friends will be returned. If the friend has allowed it,
	 * you'll also see links to their Twitter and Facebook accounts.
	 *
	 * Parameters
	 * uid:	(optional)	user id of the person for whom you want to pull a friend graph
	 */
	function get_friends ( $uid = null ) {
		$this->method = '/friends.' . $this->format;

		if ( $this->checkval( $uid ) && ( $uid > 0 ) )
			$this->method .= '?uid=' . $uid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Returns a list of friends
	 *
	 * If you do not specify uid, the authenticating
	 * user's list of friends will be returned. If the friend has allowed it,
	 * you'll also see links to their Twitter and Facebook accounts.
	 * Note that most of the fields returned inside <venue> can be optional.
	 * The user may create a venue that has no address, city or state (the
	 * venue is created instead at the geolat/geolong specified).
	 * Your client should handle these conditions safely.
	 *
	 * Parameters
	 * geolat:	(required)	latitude
	 * geolong:	(required)	longitude
	 * limit:	(optional)	limit of results (default: 10, maximum: 50)
	 * q:		(optional)	keyword search
	 */
	function nearby_and_search ( $args ) {

		$defaults = array(
			'geolat'		=> null,
			'geolong'		=> null,
			'limit'			=> null,
			'q'				=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/venues.' . $this->format . '?geolat=' . $geolat . '&geolong=' . $geolong;

		if ( $this->checkval( $limit ) && ( $limit > 0 ) && ( $limit <= 50 ) )
			$this->method .= '&l=' . $limit;

		if ( $this->checkval( $q ) && ( $q > 0 ) )
			$this->method .= '&q=' . $q;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Returns venue data, including mayorship, tips/to-dos and tags
	 *
	 * A <specials>  block will be returned if there are any specials associated
	 * with this venue. It'll include subnodes <special> which may have various
	 * types. The types can be one of: mayor, count, frequency, or other. If the
	 * special is at a nearby venue (instead of at the currently visible venue),
	 * you'll see a <venue> node inside <special>  that will highlight
	 * the nearby venue.
	 *
	 * If you authenticate, you'll get back social meta data:
	 *		+ stats		-	shows whether you and your friends have ever
	 *						checked in here (<beenhere>)
	 *
	 *		+ checkins	-	show the people currently checked into this location
	 *						(ie, last three hours). you'll see <shout> and full
	 *						<lastname> if they are friends with the authenticating user
	 * Parameters
	 * vid: (required) the ID for the venue for which you want information
	 */
	function venue_details ( $vid ) {
		$this->method = '/venue.' . $this->format . '?vid=' . $vid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}


	/**
	 * Add a new venue
	 *
	 * If you find this method returns an <error>, give the user the option to
	 * edit their inputs. In addition to this, give users the ability to say
	 * "never mind, check-in here anyway" and perform a manual ("venueless")
	 * checkin by specifying just the venue name to /v1/checkin. You'll rarely
	 * run into this case, but there's a chance you'll see this case if the
	 * user wants to force a duplicate venue.
	 *
	 * All fields are optional, but you must specify either a valid address
	 * or a geolat/geolong pair. It's recommended that you pass a 
	 * geolat/geolong pair in every case.
	 *
	 * Parameters
	 * name:		(optional)	the name of the venue
	 * address:		(optional)	the address of the venue (e.g., "202 1st Avenue")
	 * crossstreet:	(optional)	the cross streets (e.g., "btw Grand & Broome")
	 * city:		(optional)	the city name where this venue is
	 * state:		(optional)	the state where the city is
	 * zip:			(optional)	the ZIP code for the venue
	 * phone:		(optional)	the phone number for the venue
	 * geolat:		(optional,	but recommended)
	 * geolong:		(optional,	but recommended)
	 */
	function venue_add ( $args ) {

		$defaults = array(
			'name'			=> null,
			'address'		=> null,
			'crossstreet'	=> null,
			'city'			=> null,
			'state'			=> null,
			'zip'			=> null,
			'phone'			=> null,
			'geolat'		=> null,
			'geolong'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/addvenue.' . $this->format . '?';

		if ( $this->checkval( $name ) )
			$this->method .= '&name=' . $name;

		if ( $this->checkval( $address ) )
			$this->method .= '&address=' . $address;

		if ( $this->checkval( $crossstreet ) )
			$this->method .= '&crossstreet=' . $crossstreet;

		if ( $this->checkval( $city ) )
			$this->method .= '&city=' . $city;

		if ( $this->checkval( $state ) )
			$this->method .= '&state=' . $state;

		if ( $this->checkval( $zip ) )
			$this->method .= '&zip=' . $zip;

		if ( $this->checkval( $phone ) )
			$this->method .= '&phone=' . $phone;

		if ( $this->checkval( $geolat ) )
			$this->method .= '&geolat=' . $geolat;

		if ( $this->checkval( $geolong ) )
			$this->method .= '&geolong=' . $geolong;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Propose a change to a venue
	 *
	 * Parameters
	 * vid:			(required)	the venue for which you want to propose an edit
	 * name:		(required)	the name of the venue
	 * address:		(required)	the address of the venue (e.g., "202 1st Avenue")
	 * crossstreet: (optional)	the cross streets (e.g., "btw Grand & Broome")
	 * city:		(required)	the city name where this venue is
	 * state:		(required)	the state where the city is
	 * zip:			(optional)	the ZIP code for the venue
	 * phone:		(optional)	the phone number for the venue
	 * geolat:		(required)
	 * geolong:		(required)
	 */
	function venue_edit ( $args ) {

		$defaults = array(
			'vid'			=> null,
			'name'			=> null,
			'address'		=> null,
			'crossstreet'	=> null,
			'city'			=> null,
			'state'			=> null,
			'zip'			=> null,
			'phone'			=> null,
			'geolat'		=> null,
			'geolong'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );
		
		$this->method = '/venue/proposeedit.' . $this->format . '?vid=' . $vid . '&name=' . $name . '&address=' . $address . '&city=' . $city . '&state=' . $state . '&geolat=' . $geolat . '&geolong=' . $geolong;

		if ( $this->checkval( $crossstreet ) )
			$this->method .= '&crossstreet=' . $crossstreet;

		if ( $this->checkval( $zip ) )
			$this->method .= '&zip=' . $zip;

		if ( $this->checkval( $phone ) )
			$this->method .= '&phone=' . $phone;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}


	/**
	 * Mark venue as closed
	 *
	 * Parameters
	 * vid:	(required)	the venue that you want marked closed
	 */
	function venue_closed ( $vid ) {
		$this->method = '/venue/flagclosed.' . $this->format . '?vid=' . $vid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Returns list of tips near the area specified
	 * The distance returned is in meters
	 * 
	 * Parameters
	 * geolat:	(required)	latitude
	 * geolong:	(required)	longitude
	 * limit:	(optional)	limit of results (default: 30)
	 */
	function nearby_tips( $geolat, $geolong, $limit ) {
		$this->method = '/tips.' . $this->format . '?geolat=' . $geolat . '&geolong=' . $geolong;

		if ( $this->checkval( $limit ) && ( $limit > 0 ) )
			$this->method .= '&l=' . $limit;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Add a new tip or to-do at a venue
	 * 
	 * Parameters
	 * vid:		(required)	the venue where you want to add this tip
	 * tex:		(required)	the text of the tip or to-do item
	 * type:	(optional)	specify one of 'tip' or 'todo' (default: tip)
	 * geolat:	(optional)	latitude (recommended)
	 * geolong:	(optional)	longitude (recommended)
	 */
	function add_tip( $args ) {

		$defaults = array(
			'vid'			=> null,
			'tex'			=> null,
			'type'			=> null,
			'geolat'		=> null,
			'geolong'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/addtip.' . $this->format . '?vid=' . $vid . '&text=' . $text;

		if ( $this->checkval( $type ) && strcmp( $type, 'tip' ) == 0 && strcmp( $type, 'todo' ) == 0 )
			$this->method .= '&type=' . $type;

		if ( $this->checkval( $geolat ) )
			$this->method .= '&geolat=' . $geolat;

		if ( $this->checkval( $geolong ) )
			$this->method .= '&geolong=' . $geolong;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Mark a tip as a to-do item
	 * 
	 * Parameters
	 * tid:	(required)	the tip that you want to mark to-do
	 */
	function mark_tip_todo( $tid ) {
		$this->method = '/tip/marktodo.' . $this->format . '?tid=' . $tid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Mark a tip as complete
	 * 
	 * Parameters
	 * tid:	(required)	the tip that you want to mark to-do
	 */
	function mark_tip_done( $tid ) {
		$this->method = '/tip/markdone.' . $this->format . '?tid=' . $tid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Shows you a list of users with whom you have a pending friend request
	 * (ie, they've requested to add you as a friend, but you have not approved)
	 */
	function friend_requests() {
		$this->method = '/friend/requests.' . $this->format;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Allows you to mark a tip as done
	 * 
	 * Parameters
	 * uid:	(required) the user ID of the user who you want to approve
	 */
	function approve_friend_request( $uid ) {
		$this->method = '/friend/approve.' . $this->format . '?uid=' . $uid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Denies a pending friend request from another user
	 * On success, returns the <user> object
	 *
	 * Parameters
	 * uid:	(required)	the user ID of the user who you want to deny
	 */
	function deny_friend_request( $uid ) {
		$this->method = '/friend/deny.' . $this->format . '?uid=' . $uid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Sends a friend request to another user
	 * On success, returns the <user> object
	 *
	 * Parameters
	 * uid:	(required)	the user ID of the user to whom you want to send a friend request
	 */
	function send_friend_request( $uid ) {
		$this->method = '/friend/sendrequest.' . $this->format . '?uid=' . $uid;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Handles all friend searching
	 *
	 * When passed a free-form text string, returns a list of matching <user>
	 * objects. The method only returns matches of people with whom you
	 * are not already friends.
	 *
	 * When passed phone number(s), returns a list of matching <user> objects.
	 * The method only returns matches of people with whom you are not already
	 * friends. You can pass a single number as a parameter, or you can pass
	 * multiple numbers separated by commas
	 *
	 * When passed a Twitter name (user A), returns a list of matching <user>
	 * objects that correspond to user A's friends on Twitter. The method only
	 * returns matches of people with whom you are not already friends.
	 *
	 * If you don't pass in a Twitter name, it will attempt to use the Twitter
	 * name associated with the authenticating user.
	 *
	 * Parameters
	 * by:	(optional)	possible values (name, phone, twitter) (default: name)
	 * q:	(optional)	search string (required for twitter)
	 */
	function find_friends( $args = null ) {
		$defaults = array(
			'by'	=> 'name',
			'q'		=> null,
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/findfriends/by' . $by . '.' . $this->format;

		if ( $this->checkval( $q ) )
			$this->method .= '?q=' . $q;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 * Allows you to change notification options for yourself (self) globally
	 * as well as for each individual friend (identified by their uid)
	 *
	 * For example: To set pings on for a user identified by UID 33: "33=on".
	 * To set pings to 'goodnight' for yourself: "self=goodnight".
	 *
	 * Parameters
	 * who:		(optional)	the ping status for yourself (default: self)
	 *						possible values are self and user id
	 * value:	(optional)	set the ping status for a friend.
	 *						possible values are on, off and goodnight.
	 */
	function set_pings( $args ) {
		$defaults = array(
			'who'			=> 'self',
			'value'			=> 'off',
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$this->method = '/settings/setpings' . '.' . $this->format;

		if ( strcmp( $who, 'self' ) == 0 )
			$this->method .= '?self=' . $value;
		else
			$this->method .= '?' . $who . '=' . $value;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}

	/**
	 *  Returns the string "ok"
	 */
	function test() {
		$this->method = '/test' . '.' . $this->format;

		return $this->get_response( BP_4SQ_FILTER . __FUNCTION__ );
	}
}

?>