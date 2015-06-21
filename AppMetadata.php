<?php

/**
 * CanvasAPIviaLTI App metadata
 **/
class AppMetadata extends ArrayObject {
	
	/**
	 * var mysqli $sql Database connection to backing store
	 **/
	protected $sql = null;
	
	/**
	 * var string $table Name of the database table in backing store
	 **/
	protected $table = null;
	
	/**
	 * var string $app Name of the app (used to look up data in the backing store)
	 **/
	protected $app = null;
	
	/**
	 * Construct an AppMetadata object, loading in persistent data, including
	 * fields whose values derive from others (e.g. 'APP_ICON_URL' => '@APP_URL/icon.png')
	 *
	 * @param mysqli $sql A database connection to the backing store for the AppMetadata object
	 * @param string $app A unique app ID to identify _this_ app's data in the backing store
	 * @param string $table Name of the table that backs the AppMetadata object (defaults to `app_metadata`)
	 * 
	 * @throws AppMetadata_Exception If no database connection is provided
	 **/
	public function __construct($sql, $app, $table = 'app_metadata') {
		parent::__construct(array());
		if ($sql instanceof mysqli) {
			$this->sql = $sql;
			$this->table = $sql->real_escape_string($table);
			$this->app = $sql->real_escape_string($app);
			if ($response = $sql->query("SELECT * FROM `{$this->table}` WHERE `app` = '{$this->app}'")) {
				$derived = array();
				while ($metadata = $response->fetch_assoc()) {
					/* booleanize booleans */
					switch($metadata['value']) {
						case 'TRUE':
							$metadata['value'] = true;
							break;
						case 'FALSE':
							$metadata['value'] = false;
							break;
					}
					
					/* allow for app metadata fields to be derived from each other */
					if (preg_match('/@\w+/', $metadata['value'])) {
						$derived[$metadata['key']] = $metadata['value'];
					}
					$this->offsetSet($metadata['key'], $metadata['value'], false);
				}
				
				/* generate derived fields based on prior fields */
				while (count($derived) > 0) {
					foreach ($derived as $key => $value) {
						preg_match_all('/@(\w+)/', $value, $sources, PREG_SET_ORDER);
						$dirty = false;
						foreach ($sources as $source) {
							/* can we update this $value now, or do we have to wait for a later pass? */
							if ($this->offsetExists($source[1])) {
								$value = preg_replace("|{$source[0]}|", $this->offsetGet($source[1]), $value);
							} else {
								/* if we'll be able to update it in a later pass, it's still "dirty" */
								if (array_key_exists($source[1], $derived)) {
	 								$dirty = true;
								}
							}
						}

						if (!$dirty) {
							$this->offsetSet($key, $value, false);
							unset($derived[$key]);
						}
					}
				}
			}
		} else {
			throw new AppMetadata_Exception(
				'Invalid mysqli object: unable to construct app metadata',
				AppMetadata_Exception::INVALID_MYSQLI_OBJECT
			);
		}
	}
	
	/**
	 * Transparently update the persistent app_metadata store when the data is changed
	 *
	 * @return void (unless ArrayObject::offsetSet() returns a value... then this will too!)
	 *
	 * @throws AppMetadata_Exception On Mysql errors.
	 **/
	public function offsetSet($key, $value, $updateDatabase = true) {
		if ($updateDatabase) {
			$_key = $this->sql->real_escape_string($key);
			$_value = $this->sql->real_escape_string($value);
			if ($this->offsetExists($key)) {
				if (!$this->sql->query("UPDATE `{$this->table}` SET `value` = '$_value' WHERE `app` = '{$this->app}' AND `key` = '$_key'")) {
					throw new AppMetadata_Exception(
						"Unable to update app metadata (`$_key` = '$_value'). {$this->sql->error}",
						AppMetadata_Exception::UPDATE_FAIL
					);
				}
			} else {
				if (!$this->sql->query("INSERT INTO `{$this->table}` (`app`, `key`, `value`) VALUES ('{$this->app}', '$_key', '$_value')")) {
					throw new AppMetadata_Exception(
						"Unable to insert app metadata (`$_key` = '$_value'). {$this->sql->error}",
						AppMetadata_Exception::INSERT_FAIL
					);
				}
			}
		}
		return parent::offsetSet($key, $value);
	}
	
	/**
	 * Create the supporting database table
	 *
	 * @param mysqli $sql A mysqli object representing the database connection
	 * @param string $schema Path to a schema file for insertion into the database
	 *
	 * @return boolean TRUE iff the database tables were created, FALSE if some tables already existed in database (and were, therefore, not created and not over-written)
	 *
	 * @throws AppMetadata_Exception If no valid mysqli object is provided to access the database
	 * @throws AppMetadata_Exception If the schema file cannot be found
	 * @throws AppMetadata_Exception If the schema tables cannot be loaded
	 **/
	public static function prepareDatabase($sql, $schema = false) {
		if ($sql instanceof mysqli) {
			/* if no schema file passed in, default to local schema.sql */
			if (!$schema) {
				$schema = __DIR__ . '/schema.sql';
			}
	
			if (file_exists($schema)) {
				$queries = explode(";", file_get_contents($schema));
				$created = true;
				foreach ($queries as $query) {
					if (!empty(trim($query))) {
						if (preg_match('/CREATE\s+TABLE\s+(`([^`]+)`|\w+)/i', $query, $tableName)) {
							$tableName = (empty($tableName[2]) ? $tableName[1] : $tableName[2]);
							if ($sql->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0) {
								$created = false;
							} else {
								if (!$sql->query($query)) {
									throw new AppMetadata_Exception(
										"Error creating app metadata database tables: {$sql->error}",
										AppMetadata_Exception::CREATE_TABLE_FAIL
									);
								}
							}
						} else {
							if (!$sql->query($query)) {
								throw new AppMetadata_Exception(
									"Error creating app metadata database tables: {$sql->error}",
									AppMetadata_Exception::PREPARE_DATABASE_FAIL
								);
							}
						}
					}
				}
			} else {
				throw new AppMetadata_Exception(
					"Schema file missing ($schema).",
					AppMetadata_Exception::MISSING_SCHEMA
				);
			}
			
			return $created;
		} else {
			throw new AppMetadata_Exception(
				"Expected valid mysqli object.",
				AppMetadata_Exception::INVALID_MYSQLI_OBJECT
			);
		}
	}
}

/**
 * So that we can throw catch-able exceptions
 **/
class AppMetadata_Exception extends Exception {
	const INVALID_MYSQLI_OBJECT = 0;
	const UPDATE_FAIL = 1;
	const INSERT_FAIL = 2;
	const CREATE_TABLE_FAIL = 3;
	const PREPARE_DATABASE_FAIL = 4;
	const MISSING_SCHEMA = 5;
}

?>