<?php
/**
 * @package ENVMON
 * @author Matthew Smith <matt@smiffytech.com>
 */
class ENVMON  {
  /** var array $config system configuration parameters. */
  public $config;
  /** var object $db MongoDB client connection to database. */
  public $db;
  /** var array $retrieved document from database. */
  public $retrieved;

  /**
   * Create a new day's document.
   *
   * @param string $date YYYY-MM-DD
   */
  public function newdoc ( $date ) {
    $doc = array();
    $doc['_id'] = new MongoID();
    $doc['date'] = $date;
    /* Timezone ignored - mdate only used for date range queries. */
    $doc['mdate'] = new MongoDate( strtotime( $date . ' 00:00:00' ) );
    $doc['geo'] = $this->config['geo'];

    /* Copy of sensor data from config. */
    $doc['sensors'] = $this->config['sensors'];

    /* Create empty aggregates set. */
    $doc['aggregates'] = array();
    foreach ( $this->config['sensors'] as $sensor ) {
      $doc['aggregates'][$sensor['device_id']] = $this->config['ag_methods'][$sensor['ag_method']];
    }

    /*
     * Create array of 288 timeslots.
     */
    $doc['data'] = array();
    for ( $h = 0; $h < 24; $h++ ) {
      for ( $m = 0; $m < 60; $m+=5 ) {
        $t = sprintf("%02d:%02d", $h, $m);
        $doc['data'][] = array( 't' => $t );
      }
    }

    $this->db->{'data'}->insert( $doc, array( 'w' => 1 ) );
    
  }

  /**
   * Retrieve a record by date.
   *
   * Results written to $this->retrieved.
   * 
   * @param string $date YYYY-MM-DD
   */
  public function getbydate( $date ) {
    $this->retrieved = $this->db->{'data'}->findOne( array( 'date' => $date ) );
    if ( count( $this->retrieved ) > 0 ) {
      $this->retrieved['recid'] = $this->retrieved['_id']->{'$id'};
      unset( $this->retrieved['_id'] );
      unset( $this->retrieved['mdate'] );
    }
  }

  /**
   * Retrieve records by date range.
   *
   * Results written to $this->retrieved.
   *
   * @param string $date_from YYYY-MM-DD
   * @param string $date_to YYYY-MM-DD
   */
  public function getbydaterange( $date_from, $date_to ) {
    $from = new MongoDate( strtotime( $date_from . ' 00:00:00' ) );
    $to = new MongoDate( strtotime( $date_to . ' 00:00:00' ) );

    $results = iterator_to_array(
      $this->db->{'data'}->find( array( 'mdate' => array( '$gte' => $from, '$lte' => $to ) ) ),
      FALSE
    );

    /* Remove mongo-ish stuff. */
    foreach ( $results as $result ) {
      $result['recid'] = $result['_id']->{'$id'};
      unset( $result['_id'] );
      unset( $result['mdate'] );
      $this->retrieved[] = $result;
    }

  }

  /**
   * Save a sensor reading.
   *
   * @param unsigned integer $timeslot
   * @param string $device_id
   * @param array $data
   */
  public function save( $timeslot, $device_id, $data ) {

    $target = 'data.' . $timeslot . '.' . $device_id;

    $this->db->{'data'}->update(
      array( '_id' => new MongoID( $this->retrieved['recid'] ) ),
      array( '$set' => array ( $target => $data ) )
    );
  }

  /**
   * Initialisation, including database connection.
   * Database connection information should be set first.
   *
   * @return integer < 0 = error, 0 = success.
   */
  public function init() {
    $config_json = file_get_contents( __DIR__ . '/siteconfig.json' );

    if ( $config_json === false ) {
      /* Return error - could not open config file. */
      return( -1 );
    }

    $this->config = json_decode( $config_json, true );

    if ( $this->config === null ) {
      /* Return error - could not parse JSON. */
      return( -2 );
    }

    $cstring = 'mongodb://';

    if ( $this->config['database']['useauth'] === true ) {
      $cstring .= $this->config['database']['user'] . ':' . $this->config['database']['pass'] . '@';
    }

    $cstring .= $this->config['database']['host'] . ':' . $this->config['database']['port'];
    $mongo = new MongoClient($cstring);
    $this->db = $mongo->selectDB($this->config['database']['db']);

    return( 0 );
  }

  /**
   * Validate/convert timeslot.
   * 
   * @param string $ts [integer or time hh:mm]
   * @return integer timeslot or < 0 for error.
   */
  public function get_timeslot( $ts ) {
    if ( preg_match( "/^\d{1,2}:\d{1,2}$/", $ts ) ) {
      $hm = explode( ':', $ts );
      if ( $hm[0] > 23 || $hm[1] > 59 ) {
        return( -1 );
      }

      /* Not a five-minute slot. */
      if ( $hm[1] % 5 != 0 ) {
        return( -2 );
      }

      $slot = ( $hm[0] * 12 ) + ( $hm[1] / 5 );

      return( $slot );
    } elseif ( preg_match( "/^\d{1,3}$/", $ts ) ) {
      if ( $ts > 287 ) {
        return( -3 );
      }
      return( $ts );
    } else {
      return( -4 );
    }
  }

}


?>
