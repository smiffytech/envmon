<?php

class ENVMON  {
  /** var array template basic document template. */
  public $template;
  /** var array sensor_template template for sensor document. */
  public $sensor_template;
  /** var array thisdoc active document. */
  public $thisdoc;
  /** var array dbparams database connection parameters. */
  public $dbparams;
  /** var object db MongoDB client connection to database. */
  public $db;
  /** var array retrieved document from database. */
  public $retrieved;

  /**
   * Create a new document from template.
   */
  public function newdoc($date = date('Y-m-d')) {
    $this->thisdoc = $this->template;

    /* Does record already exist? */
    $this->get($date);
    if (count($this->retrieved) > 0) {
      return(0);
    }

    $_id = new MongoId($date);
    
  }

  /**
   * Retrieve a record by date.
   */
  public function get($date = date('Y-m-d')) {
    $_id = new MongoId($date);

    $this->retrieved = $this->db->{'data'}->findOne(array('id' => $_id));
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
    $cstring = 'mongodb://';

    if ( $this->dbparams['useauth'] === true ) {
      $cstring .= $this->dbparams['user'] . ':' . $this->dbparams['pass'] . '@';
    }

    $cstring .= $this->dbparams['host'] . ':' . $this->dbparams['port'];
    $mongo = new MongoClient($cstring);
    $this->db = $mongo->selectDB($this->dbparams['db']);
  }

  /**
   * Set up template for new documents, and default
   * database connection parameters.
   */
  public function __construct() {
    $config_json = file_get_contents('./siteconfig.json');

    $jdata = json_decode($config_json, true);

    $this->template = array(
      'date' => '0000-00-00',
    );

    $this->sensor_template = array(
      'type' => null,
      'device_id' => null,
      'description' => null,
      'location' => null
    );

    $this->dbparams = array(
      'useauth' => false,
      'user' => null,
      'pass' => null,
      'db' => 'envmon',
      'host' => 'localhost',
      'port' => 27017
    );
  }
}


?>
