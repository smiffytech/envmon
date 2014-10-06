<?php

class ENVMON  {
  /** var array template basic document template. */
  public $template;
  /** var array sensor_template template for sensor document. */
  public $sensor_template;
  /** var array doc active document. */
  public $doc;
  /** var array config system configuration parameters. */
  public $config;
  /** var object db MongoDB client connection to database. */
  public $db;
  /** var array retrieved document from database. */
  public $retrieved;
  /** var string date today's date, by local timezone. */
  public $date;

  /**
   * Create a new day's document.
   */
  public function newdoc ( $date ) {

    $this->doc['_id'] = new MongoID();
    $this->doc['date'] = $date;
    $this->doc['mdate'] = new MongoDate( strtotime( $date . ' 00:00:00' ) );
    $this->doc['geo'] = $this->config['geo'];
    /* Show property not required in document. */
    unset( $this->doc['geo']['show'] );

    /*
     * Create array of 288 timeslots.
     */
    $this->doc['data'] = array();
    for ( $h = 0; $h < 24; $h++ ) {
      for ( $m = 0; $m < 60; $m+=5 ) {
        $t = sprintf("%02d:%02d", $h, $m);
        $this->doc['data'][] = array( 't' => $t );
      }
    }

    $this->db->{'data'}->insert( $this->doc, array( 'w' => 1 ) );
    
    return( 0 );
  }

  /**
   * Retrieve a record by date.
   */
  public function getbydate( $date ) {
    $this->retrieved = $this->db->{'data'}->findOne( array( 'date' => $this->doc['date'] ) );
  }


  /**
   * Save a sensor reading.
   */
  public function save( $timeslot, $device_id, $data ) {

    $target = 'data.' . $timeslot . '.' . $device_id;

    $this->db->{'data'}->update(
      array( '_id' => $this->retrieved['_id'] ),
      array( '$set' => array ( $target => $data ) )
    );
  }

  /**
   * Initialisation, including database connection.
   * Database connection information should be set first.
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

  /**
   * Set up sensor record template.
   */
  public function __construct() {
    $this->doc = array();
    $this->date = date( 'Y-m-d' );

    $this->sensor_template = array(
      'type' => null,
      'device_id' => null,
      'description' => null,
      'location' => null
    );

  }
}


?>
