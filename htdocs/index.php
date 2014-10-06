<?php

require "../server/envmon.php";

/* 
 * Always set timezone to UTC, so as to ensure that
 * we do not enter daylight saving.
 *
 * UTC offset of local system is defined in siteconfig.json
 * and set in minutes as a property of the ENVMON object:
 *
 * ENVMON::config['geo']['utc_offset']
 */
date_default_timezone_set('UTC');

ob_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: OPTIONS,POST,GET');
header('Access-Control-Allow-Headers: Origin,Content-Type,Authorization,Accept');

/*
 * Handle options requests without instantiation ENVMON.
 */
if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) == 'OPTIONS' ) {
  header_remove( 'Content-type' );
  header( $_SERVER['SERVER_PROTOCOL'] . ' 200 OK ' );
  header( 'Allow: POST,GET' );
  header( 'Content-length: 0' );
  ob_flush();
  exit;
}

/*
 * Instantiate ENVMON object, and initialise.
 */
$em = new ENVMON;
$init_result = $em->init();

if ( $init_result < 0 ) {
  header( $_SERVER['SERVER_PROTOCOL'] . ' 500 Server Error' );
  echo '500 Server Error - Could not initialise system.';
  ob_flush();
  exit;
}

/*
 * Retrieve request headers.
 */
$raw_headers = apache_request_headers();
$headers = array();
foreach ( $raw_headers as $n => $v ) {
  $headers[strtolower($n)] = $v;
}

/*
 * All requests should be POSTed as JSON.
 */
if ( !array_key_exists('content-type', $headers) ) {
  bad_request( 'content type should be application/json' );
}

$ct = preg_split( "/;*\s+/", $headers['content-type'] );
if ( strtolower( $ct[0] ) != 'application/json' ) {
  bad_request( 'content type should be application/json' );
}


/*
 * Check authorisation.
 */
if ( array_key_exists( 'authorization', $headers ) ) {
  $authparams = explode( ' ', $headers['authorization'] );
  $userpass = explode( ':', base64_decode( $authparams[1] ) );

  if ( $userpass[0] != $em->config['auth']['user'] || $userpass[1] != $em->config['auth']['pass'] ) {
    header( $_SERVER['SERVER_PROTOCOL'] . ' 401 Authorization Required' );
    echo '401 Authorization Required';
    ob_flush();
    exit;
  }
} else {
  header( $_SERVER['SERVER_PROTOCOL'] . ' 401 Authorization Required' );
  echo '401 Authorization Required';
  ob_flush();
  exit;
}

/*
 * Retrieve POST payload and attempt to parse.
 */
$jdata = json_decode( file_get_contents( 'php://input' ), true );
if ( $jdata === null ) {
  bad_request('could not parse JSON.');
}

/***** If we reach this point, we are authenticated and have valid JSON data. *****/

/*
 * Check all mandatory parameters have been supplied.
 */
$mandatory_params = explode( ' ', 'device_id date timeslot data' );
foreach ( $mandatory_params as $thisparam ) {
  if ( !array_key_exists( $thisparam, $jdata ) ) {
    bad_request( 'mandatory parameter ' . $thisparam . ' missing.' );
  }
}


function bad_request( $text = 'could not process request.' ) {
  header( $_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request' );
  echo '400 Bad Request - ' . $text;
  ob_flush();
  exit;
}


header( $_SERVER['SERVER_PROTOCOL'] . ' 200 OK' );
echo '200 OK';
ob_flush();
exit;

?>
