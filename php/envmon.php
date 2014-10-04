<?php

class ENVMON {
  /** var array template basic document template. */
  public var template;
  /** var array thisdoc active document. */
  public var thisdoc;
  /** var array dbparams database connection parameters. */
  public var dbparams;
  /** var object db MongoDB client connection to database. */
  public var db;

  /**
   * Create a new document from template.
   */
  public function newdoc() {
    $this->thisdoc = $this->template();
  }

  /**
   * Insert new document ($this->thisdoc) into
   * database.
   */
  public function insert() {
  }


  /**
   * Initialisation, including database connection.
   * Database connection information should be set first.
   */
  public function init() {
    $cstring = 'mongodb://' . $this->dbparams['host'] . ':' . $this->dbparams['port'];
    $mongo = new MongoClient($cstring);
    $this->db = $mongo->$this->dbparams['db'];
  }

  /**
   * Set up template for new documents, and default
   * database connection parameters.
   */
  public function __construct() {
    $this->template = array(
      'date' => '0000-00-00',
      'geo' => array(
          'active' => false,
          'lat' => null,
          'long' => null,
          'alt' => null
        ),
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
