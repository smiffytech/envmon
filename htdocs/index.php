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
if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) == 'POST' ) {
  if ( !array_key_exists('content-type', $headers) ) {
    bad_request( 'content type should be application/json' );
  }

  $ct = preg_split( "/;*\s+/", $headers['content-type'] );
  if ( strtolower( $ct[0] ) != 'application/json' ) {
    bad_request( 'content type should be application/json' );
  }
}


/*
 * Check authorisation.
 */
/*
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
*/

if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) == 'POST' ) {
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

  /*
   * We can handle timeslots given as either an index ( 0 - 287 )
   * or as a time ( 00:00 - 23:55, increments of five minutes only.)
   */
  $ts = $em->get_timeslot( $jdata['timeslot'] );
  if ( $ts < 0 ) {
    bad_request( 'invalid timeslot ' . $jdata['timeslot'] );
  }

  /*
   * Attempt to retrieve the day's document. If one does not 
   * exist, create, then retrieve.
   */
  $em->getbydate( $jdata['date'] );
  if ( count( $em->retrieved ) == 0 ) {
    $em->newdoc( $jdata['date'] );
    $em->getbydate( $jdata['date'] );
  }

  /* Does a record for this timeslot/sensor exist? */
  $exists = array_key_exists( $jdata['device_id'], $em->retrieved['data'][ $jdata['timeslot'] ]);

  /* Is replace parameter present, and true? */
  $replace = ( array_key_exists( 'replace', $jdata ) && 
      $jdata['replace'] === true ? true : false );


  if ( ( $exists === true && $replace === true ) || $exists === false ) {
    /* Save record. */
    $em->save( $ts, $jdata['device_id'], $jdata['data'] );
  } else {
    /* Record exists, replace not set - return conflict. */
    header( $_SERVER['SERVER_PROTOCOL'] . ' 409 Conflict' );
    echo '409 Conflict';
    ob_flush();
    exit;
  }

  /* Return document MongoID as plain text. */
  send_text( $em->retrieved['recid'] );
 
} else {
  /* Handle GET */
  
  /* Validate parameters. */
  if ( count( $_GET ) == 0 ) {
    bad_request('missing GET parameters.');
  }

  if ( array_key_exists( 'date', $_GET ) && ( array_key_exists( 'date_from', $_GET ) || array_key_exists( 'date_to', $_GET ) ) ) {
    bad_request('cannot use date parameter with date_from or date_to');
  }

  if ( !array_key_exists( 'date', $_GET ) && ( array_key_exists( 'timeslot', $_GET ) || array_key_exists( 'device_id', $_GET ) ) ) {
    bad_request('timeslot/device_id parameters require the date parameter to be set.');
  }

  if ( !array_key_exists( 'timeslot', $_GET ) && array_key_exists( 'device_id', $_GET ) ) {
    bad_request('device_id parameter requires date, and timeslot parameters to be present.');
  }

  if ( array_key_exists( 'date', $_GET ) ) {
    /* Retrieve single document. */
    $em->getbydate( $_GET['date'] );
    if ( count( $em->retrieved ) == 0 ) {
      not_found();
    }

    /* Timeslot provided. */
    if ( array_key_exists( 'timeslot', $_GET ) ) {

      $ts = $em->get_timeslot( $_GET['timeslot'] );
      if ( $ts < 0 ) {
        bad_request( 'invalid timeslot ' . $_GET['timeslot'] );
      }

      /* Device ID provided. */
      if ( array_key_exists( 'device_id', $_GET ) ) {
        if ( count( $em->retrieved['data'][$ts][$_GET['device_id']] ) == 0 ) {
          not_found();
        } else {
          send_json( $em->retrieved['data'][$ts][$_GET['device_id']] );
        }
      } else {
        send_json( $em->retrieved['data'][$ts] );
      }
    } else {
      send_json( $em->retrieved );
    }
    
  } else {
    /* Retrieve multiple documents. */

    if ( !array_key_exists( 'date_from', $_GET ) || !array_key_exists( 'date_to', $_GET ) ) {
      bad_request('date_from must be used in combination with date_to');
    }

    $em->getbydaterange( $_GET['date_from'], $_GET['date_to'] );

    if ( count( $em->retrieved ) == 0 ) {
      not_found();
    } else {
      send_json( $em->retrieved );
    }
  }

}


/***** Functions *****/

function send_text( $text ) {
  header('Content-type: text/plain');
  header( $_SERVER['SERVER_PROTOCOL'] . ' 200 OK' );
  echo '200 OK ' . $text;
  ob_flush();
  exit;
}

function send_json( $jdata ) {
  header('Content-type: application/json');
  header( $_SERVER['SERVER_PROTOCOL'] . ' 200 OK' );
  echo json_encode( $jdata, JSON_PRETTY_PRINT );
  ob_flush();
  exit;
}

function not_found() {
  header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );
  echo '404 Not Found';
  ob_flush();
  exit;
}

function bad_request( $text = 'could not process request.' ) {
  header( $_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request' );
  echo '400 Bad Request - ' . $text;
  ob_flush();
  exit;
}

?>
