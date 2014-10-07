<?php
/**
 * The MIT License (MIT)
 * 
 * Copyright (c) 2014 Matthew Steven Smith
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @package ENVMON
 * @author Matthew Smith <matt@smiffytech.com>
 */
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
   *
   * @param string $date YYYY-MM-DD
   */
  public function newdoc ( $date ) {

    $this->doc['_id'] = new MongoID();
    $this->doc['date'] = $date;
    /* Timezone ignored - mdate only used for date range queries. */
    $this->doc['mdate'] = new MongoDate( strtotime( $date . ' 00:00:00' ) );
    $this->doc['geo'] = $this->config['geo'];

    /* Copy of sensor data from config. */
    $this->doc['sensors'] = $this->config['sensors'];

    /* Create empty aggregates set. */
    $this->doc['aggregates'] = array();
    foreach ( $this->config['sensors'] as $sensor ) {
      $this->doc['aggregates'][$sensor['device_id']] = $this->config['ag_methods'][$sensor['ag_method']];
    }

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
      array( '_id' => $this->retrieved['_id'] ),
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
