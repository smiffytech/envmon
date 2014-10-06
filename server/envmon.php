<?php

class ENVMON  {
  /** var array template basic document template. */
  public $template;
  /** var array sensor_template template for sensor document. */
  public $sensor_template;
  /** var array thisdoc active document. */
  public $thisdoc;
  /** var array config system configuration parameters. */
  public $config;
  /** var object db MongoDB client connection to database. */
  public $db;
  /** var array retrieved document from database. */
  public $retrieved;
  /** var string date today's date, by local timezone. */
  public $date;

  /**
   * Create a new document from template.
   */
  public function newdoc( $date = null ) {

    $this->thisdoc = array();

    $this->thisdoc['date'] = ( $date === null ? $this->date : $date );

    $this->thisdoc['_id'] = new MongoID();

    /* Does record already exist? */
    $this->get($date);
    if (count($this->retrieved) > 0) {
      return( -1 );
    }
    
    return( $this->thisdoc['_id']->{'$id'} );
  }

  /**
   * Retrieve a record by date.
   */
  public function get( $date = null ) {
    if ( $date === null ) {
      $date = $this->date;
    }

    $this->retrieved = $this->db->{'data'}->findOne( array( 'date' => $date ) );
    $this->retrieved['recid'] = $this->retrieved['_id']->{'$id'};
  }

  /**
   * Insert new document ($this->thisdoc) into
   * database.
   */
  public function insert() {
    $this->db->{'data'}->insert($this->thisdoc);
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
   * Set up sensor record template.
   */
  public function __construct() {
    $this->date = date( 'Y-m-d' );

    // 288 timeslots.

    $this->sensor_template = array(
      'type' => null,
      'device_id' => null,
      'description' => null,
      'location' => null
    );

  }
}


?>
