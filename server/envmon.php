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
   * Create a new document from template.
   */
  public function newdoc() {

    /* Does record already exist? */
    $this->get();
    if (count($this->retrieved) > 0) {
      return( -1 );
    }

    $this->doc['_id'] = new MongoID();
    $this->doc['recid'] = $this->doc['_id']->{'$id'};
    
    return( 0 );
  }

  /**
   * Retrieve a record by date.
   */
  public function get() {
    $query = array( 
      'date' => $this->doc['date'], 
      'timeslot' => $this->doc['timeslot'],
      'device_id' => $this->doc['device_id']
    );


    $this->retrieved = $this->db->{'data'}->findOne( $query );

    if ( count( $this->retrieved ) > 0 ) {
      $this->retrieved['recid'] = $this->retrieved['_id']->{'$id'};
    }
  }

  /**
   * Insert new document ($this->thisdoc) into
   * database.
   */
  public function insert() {
    unset( $this->doc['replace'] );
    $this->db->{'data'}->insert($this->doc);
  }

  /**
   * Update document.
   */
  public function update() {
    unset( $this->doc['_id'] );
    $this->db->{'data'}->update(
      array( '_id' => $this->retrieved['_id'] ),
      array( '$set' => $this->doc )
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
