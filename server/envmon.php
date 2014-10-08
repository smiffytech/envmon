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

    /* Attempt to calculate day's sun times. */
    $srss = $this->srss( $date );
    if ( $srss['status'] === true ) {
      unset($srss['status']);
      $doc['sun_times'] = $srss;
    } else {
      $doc['sun_times'] = array();
    }

    /*
     * Copy sensor data from config, using device_id
     * as array key. 
     */
    foreach ( $this->config['sensors'] as $sensor ) {
      $device_id = $sensor['device_id'];
      unset( $sensor['device_id'] );
      $doc['sensors'][$device_id] = $sensor;
    }

    /* Create empty stats set. */
    $doc['stats'] = array();
    foreach ( $this->config['sensors'] as $sensor ) {
      $doc['stats'][$sensor['device_id']] = $this->config['ag_methods'][$sensor['ag_method']];
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
   * Update statistics for a sensor.
   *
   * Two methods are currently supported: maxminsd (for temperatures, and similar,) 
   * and count (for rain gauges and similar.) Further
   * methods of generating statistics for other sensor types may be added
   * to this function.
   *
   * @param string $date YYYY-MM-DD
   * @param unsigned integer $ts timeslot 0 - 278.
   * @param string $device_id unique identifier for sensor.
   */
  public function dostats( $date, $ts, $device_id ) {
    /* We have just updated the document, so need to retrieve it again. */
    $this->getbydate( $date );

    $method = $this->retrieved['sensors'][$device_id]['ag_method'];

    $target = 'stats' . '.' . $device_id;

    if ( $method == 'maxminsd' ) {
      $dataset = array();
      for ( $i = 0; $i <= $ts; $i++ ) {
        $dataset[] = $this->retrieved['data'][$i][$device_id]['mean_value'];
      }

      $stats = $this->maxminsd( $dataset );
      /* Sum is not relevant/recorded, remove. */
      unset($stats['sum']);

    } elseif ( $method == 'count' ) {

      $dataset = array();
      for ( $i = 0; $i <= $ts; $i++ ) {
        $dataset[] = $this->retrieved['data'][$i][$device_id]['count'];
      }

      $tmpstats = $this->maxminsd( $dataset ); 

      $stats = array( 'total' => $tmpstats['sum'], 'max' => $tmpstats['max'], 'max_ts' => $tmpstats['max_ts'] );
    }

    $query = array( '$set' => array( $target => $stats ) );

    $this->db->{'data'}->update( array( '_id' => new MongoID( $this->retrieved['recid'] ) ), $query );
  }

  /**
   * Simple statistical calculations.
   *
   * @param array $dataset array of numbers.
   * @return array of the sum, maximum, minimum, mean, standard deviation, array position of first occurrence of maximum value, array position of first occurrence of minimum value.
   */
  public function maxminsd( $dataset ) {
    $stats = array(
      'sum' => 0,
      'max' => 0,
      'min' => $dataset[0],
      'mean' => 0,
      'sd' => 0,
      'max_idx' => 0,
      'min_idx' => 0
    );

    $count = 0;

    /* 
     * Calculate sum, maximum, minimum,
     * and positions of maximum, minimum.
     */
    foreach ( $dataset as $n ) {
      $stats['sum'] += $n;
      if ( $n > $stats['max'] ) {
        $stats['max'] = $n;
        $stats['max_idx'] = $count;
      }
      if ( $n < $stats['min'] ) {
        $stats['min'] = $n;
        $stats['min_idx'] = $count;
      }
      $count++;
    }

    /* Calculate mean. */
    $stats['mean'] = $stats['sum'] / $count;

    /* Calculate variance. */
    $vt = 0;
    foreach ( $dataset as $n ) {
      $v =  $n - $stats['mean'];
      $vt += ( $v * $v );
    }
    $var = $vt / $count;

    /* Calculate standard deviation. */
    $stats['sd'] = sqrt( $var );

    return( $stats );
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

  /**
   * Calculate sunrise, sunset, twilight times.
   */
  public function srss( $date ) {
    $times = array( 'status' => true );

    /* Check lat/long are set. */
    if ( $this->config['geo']['lat'] === null ||
         $this->config['geo']['long'] === null ) {
      $times['status'] = false;
      return( $times );
    }

    $lat = $this->config['geo']['lat'];
    $long = $this->config['geo']['long'];
    $offset = $this->config['geo']['utc_offset'];

    $t = strtotime( $date . ' 00:00:00' ); 

    /* The following are derived from a comment on the PHP man page for date_sunrise(). */
    $zenith = 90 + (50 / 60);
    $times['sunrise'] = date_sunrise($t, SUNFUNCS_RET_STRING, $lat, $long, $zenith, $offset);
    $times['sunset'] = date_sunset($t, SUNFUNCS_RET_STRING, $lat, $long, $zenith, $offset);

    $zenith=96;
    $times['civil_twilight_start'] = date_sunrise($t, SUNFUNCS_RET_STRING, $lat, $long, $zenith, $offset);
    $times['civil_twilight_end'] = date_sunset($t, SUNFUNCS_RET_STRING, $lat, $long, $zenith, $offset);

    $zenith=102;
    $times['nautical_twilight_start'] = date_sunrise($t, SUNFUNCS_RET_STRING, $lat, $long, $zenith, $offset);
    $times['nautical_twilight_end'] = date_sunset($t, SUNFUNCS_RET_STRING, $lat, $long, $zenith, $offset);

    $zenith=108;
    $times['astronomical_twilight_start'] = date_sunrise($t, SUNFUNCS_RET_STRING, $lat, $long, $zenith, $offset);
    $times['astronomical_twilight_end'] = date_sunset($t, SUNFUNCS_RET_STRING, $lat, $long, $zenith, $offset);

    return( $times );
  }

}


?>
