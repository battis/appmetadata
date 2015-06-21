<?php

/**
 * CanvasAPIviaLTI App metadata
 **/
class AppMetadata extends ArrayObject {
	
	protected $sql = null;
	protected $table = null;
	protected $app = null;
	
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
				
				/* generate derived fields based on preset fields */
				while (count($derived) > 0) {
					foreach ($derived as $key => $value) {
						preg_match_all('/@(\w+)/', $value, $sources, PREG_SET_ORDER);
						$dirty = false;
						foreach ($sources as $source) {
							if ($this->offsetExists($source[1])) {
								$value = preg_replace("|{$source[0]}|", $this->offsetGet($source[1]), $value);
							} else {
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
			throw new AppMetadata_Exception('Invalid mysqli object: unable to construct app metadata');
		}
	}
	
	/**
	 * Transparently update the persistent app_metadata store when the data is changed
	 * @throws AppMetadata_Exception On Mysql errors.
	 **/
	public function offsetSet($key, $value, $updateDatabase = true) {
		if ($updateDatabase) {
			$_key = $this->sql->real_escape_string($key);
			$_value = $this->sql->real_escape_string($value);
			if ($this->offsetExists($key)) {
				if (!$this->sql->query("UPDATE `{$this->table}` SET `value` = '$_value' WHERE `app` = '{$this->app}' AND `key` = '$_key'")) {
					throw new AppMetadata_Exception("Unable to update app metadata (`$_key` = '$_value'). {$this->sql->error}");
				}
			} else {
				if (!$this->sql->query("INSERT INTO `{$this->table}` (`app`, `key`, `value`) VALUES ('{$this->app}', '$_key', '$_value')")) {
					throw new AppMetadata_Exception("Unable to insert app metadata (`$_key` = '$_value'). {$this->sql->error}");
				}
			}
		}
		return parent::offsetSet($key, $value);
	}
	
	/**
	 * Create the supporting database table
	 * @param mysqli $sql A mysqli object representing the database connection
	 * @param string $schema Path to a schema file for insertion into the database
	 * @return boolean TRUE iff the database tables were created
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
	
			// TODO it would be grand to scan the schema to check to see if the table(s) already exist
			if (file_exists($schema)) {
				$tables = explode(";", file_get_contents($schema));
				foreach ($tables as $table) {
					if (strlen(trim($table))) {
						if (!$sql->query($table)) {
							throw new AppMetadata_Exception("Error creating app metadata database tables: {$sql->error}");
						}
					}
				}
			} else {
				throw new AppMetadata_Exception("Schema file missing ($schema).");
			}
			
			return true;
		} else {
			throw new AppMetadata_Exception("Expected valid mysqli object.");
		}
	}
}

class AppMetadata_Exception extends Exception {}

?>