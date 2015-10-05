<?php

/** AppMetadata and related classes */

namespace Battis;

/**
 * Transparently back an associative array with a MySQL database
 *
 * @author Seth Battis <seth@battis.net>
 **/
class AppMetadata extends \ArrayObject {
	
	/**
	 * @var \mysqli Database connection to backing store
	 **/
	protected $sql = null;
	
	/**
	 * @var string Name of the database table in backing store
	 **/
	protected $table = null;
	
	/**
	 * @var string Name of the app (used to look up data in the backing store)
	 **/
	protected $app = null;
	
	/** @var boolean Whether or not the database is prepared */
	private $initialized = false;
	
	/**
	 * Construct an AppMetadata object, loading in persistent data, including
	 * fields whose values derive from others (e.g. 'APP_ICON_URL' => '@APP_URL/icon.png')
	 *
	 * @param \mysqli $sql A database connection to the backing store for the AppMetadata object
	 * @param string $app A unique app ID to identify _this_ app's data in the backing store
	 * @param string optional $table Name of the table that backs the AppMetadata object (defaults to `app_metadata`)
	 * 
	 * @throws AppMetadata_Exception INVALID_MYSQLI_OBJECT f no database connection is provided
	 **/
	public function __construct($sql, $app, $table = 'app_metadata') {
		parent::__construct(array());
		if ($sql instanceof \mysqli) {
			$this->sql = $sql;
			$this->table = $sql->real_escape_string($table);
			$this->app = $sql->real_escape_string($app);
			if ($response = $sql->query("SELECT * FROM `{$this->table}` WHERE `app` = '{$this->app}'")) {
				while ($metadata = $response->fetch_assoc()) {
					$this->_offsetSet($metadata['key'], unserialize($metadata['value']), false);
				}
				$this->updateDerivedValues();
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
	 * @param int|string $key Associative array key
	 * @param mixed $value Value to store in that key ($key => $value)
	 *
	 * @return void (unless ArrayObject::offsetSet() returns a value... then this will too!)
	 *
	 * @throws AppMetadata_Exception UPDATE_FAIL if an existing key cannot be updated
	 * @throws AppMetadata_Exception INSERT_FAIL if a new key cannot be inserted
	 **/
	public function offsetSet($key, $value) {
		$result = $this->_offsetSet($key, $value);
		$this->updateDerivedValues($key, $value);
		return $result;
	}

	/**
	 * Transparently update the persistent app_metadata store when the data is changed
	 *
	 * @param int|string $key Associative array key
	 * @param mixed $value Value to store in that key ($key => $value)
	 * @param boolean $updateDatabase optional Used in __construct() to handle derived values (@APP_URL/icon.png)
	 *
	 * @return void (unless ArrayObject::offsetSet() returns a value... then this will too!)
	 *
	 * @throws AppMetadata_Exception UPDATE_FAIL if an existing key cannot be updated
	 * @throws AppMetadata_Exception INSERT_FAIL if a new key cannot be inserted
	 **/
	private function _offsetSet($key, $value, $updateDatabase = true) {
		if ($updateDatabase) {
			if ($this->sqlInitialized()) {
				$_key = $this->sql->real_escape_string($key);
				$_value = $this->sql->real_escape_string(serialize($value));
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
		}
		
		return parent::offsetSet($key, $value);
	}
	
	/**
	 * Transparently expunge the persistent app_metadata store when the data is unset
	 *
	 * @param int|string $key Array key whose value will be unset()
	 *
	 * @return void (unless ArrayObject::offsetUnset() returns a value... then this wil too!)
	 *
	 * @throws AppMetadata_Exception DELETE_FAIL if the deletion fails
	 **/
	public function offsetUnset($key) {
		$_key = $this->sql->real_escape_string($key);
		if (!$this->sql->query("DELETE FROM `{$this->table}` WHERE `app` = '{$this->app}' AND `key` = '$_key'")) {
			throw new AppMetadata_Exception(
				"Unable to delete app metadata (`$_key`). {$this->sql->error}",
				AppMetadata_Exception::DELETE_FAIL
			);
		}
		
		$result = parent::offsetUnset($key);

		$this->updateDerivedValues($key);
		
		return $result;
	}
	
	/**
	 * Create the supporting database table
	 *
	 * @return boolean TRUE iff the database tables were created, FALSE if some tables already existed in database (and were, therefore, not created and not over-written)
	 *
	 * @throws AppMetadata_Exception INVALID_MYSQLI_OBJECT if no valid mysqli object is provided to access the database
	 * @throws AppMetadata_Exception MISSING_SCHEMA if the schema file cannot be found
	 * @throws AppMetadata_Exception CREATE_TABLE_FAIL or PREPARE_DATABASE_FAIL if the schema tables cannot be loaded
	 **/
	public function buildDatabase() {
		if ($this->sql) {
			if ($this->sql->query("
				CREATE TABLE IF NOT EXISTS `{$this->table}` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`key` varchar(255) NOT NULL,
					`value` text,
					`validate` text,
					`timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					`app` varchar(255) NOT NULL DEFAULT '',
					PRIMARY KEY (`id`)
				);
			")) {
				$this->initialized = true;
				
				/* Upgrade pre-v1.2 app_metadata tables to include independent IDs */
				$this->sql->query("
					ALTER TABLE `{$this->table}`
						DROP PRIMARY KEY,
						ADD `id` INT(11) UNSIGNED PRIMARY KEY AUTO_INCREMENT FIRST;
				");
			}
		}
		return $this->initialized;
	}
	
	/**
	 * Test for database connection initialization
	 *
	 * If the database connection is not initalized, attempt to do so.
	 *
	 * @return boolean Returns `TRUE` if database connection is ready
	 *
	 * @throws AppMetadata_Exception PREPARE_DATABASE_FAIL If the backing
	 *		database connection cannot be initialized
	 **/
	protected function sqlInitialized() {
		if (!$this->initialized) {
			if (!$this->buildDatabase()) {
				throw new AppMetadata_Exception(
					'Backing database not initializable',
					AppMetadata_Exception::PREPARE_DATABASE_FAIL
				);
			}
		}
		return true;
	}
	
	/**
	 * Calculate the derived value for a particular offset
	 *
	 * For example...
	 ```PHP
	 $metadata['A'] = 'foo';
	 $metadata['B'] = '@A/bar';
	 echo $metadata['B']; // 'foo/bar';
	 $metadata['A'] = 'rutabega';
	 echo $metadata['B']; // 'rutabega/bar'
	 ```
	 *
	 * @param string $key (Optional) Limit the updates to values derived from `$key`
	 * @param string $value (Optiona) The value that is stored at `$key` which may
	 *		itself need to be derived further
	 *
	 * @return void
	 **/
	private function updateDerivedValues($key = null, $value = null) {
		
		/* 
		 * TODO
		 * I darkly suspect that there is a possibility that you could create a loop
		 * of derived references that would be irresolvable and not currently detected
		 * e.g. A => '@B', B=>'@C', C=>'@A'. Perhaps the best approach would be to
		 * limit the depth of the derivation search?
		 */
		
		$derived = array();

		/* determine breadth of derived fields affected */
		$derivedPattern = '%@_%';
		if (!empty($key)) {
			$_key = $this->sql->real_escape_string($key);
			$derivedPattern = "%@$_key%";
			
			if (!empty($value) && is_string($value)) {
				$derived[$key] = $value;
			}
		}
		
		/* build a list of affected key => value pairs */
		if ($result = $this->sql->query("
			SELECT *
				FROM `{$this->table}`
				WHERE
					`value` LIKE '$derivedPattern'
					
		")) {
			while($row = $result->fetch_assoc()) {
				$value = unserialize($row['value']);
				if (is_string($value)) {
					$derived[$row['key']] = $value;
				}
			}
		}
		
		/* generate derived fields based on prior fields */
		while (count($derived) > 0) {
			$next = array();
			foreach ($derived as $key => $value) {

				/* look for @keys in the value */				
				preg_match_all('/@(\w+)/', $value, $sources, PREG_SET_ORDER);
				
				$dirty = false;
				foreach($sources as $source) {
					if ($this->offsetExists($source[1]) && is_string($this->offsetGet($source[1]))) {
						$value = preg_replace("/{$source[0]}/", $this->offsetGet($source[1]), $value);
						$dirty = true;
					}
				}
				/* ...and queue up again to check */
				if ($dirty) {
					$next[$key] = $value;
				} else {
					$this->_offsetSet($key, $value, false);
				}
			}
			
			/* use new queue */
			$derived = $next;
		}
	}
}

/**
 * AppMetadata-specific exceptions (for easier exception catching)
 *
 * @author Seth Battis <seth@battis.net>
 **/
class AppMetadata_Exception extends \Exception {
	
	/** Invalid MySQL database connection */
	const INVALID_MYSQLI_OBJECT = 1;
	
	/** Unable to update a metadata value */
	const UPDATE_FAIL = 2;
	
	/** Unable to create a new metadata value */
	const INSERT_FAIL = 3;
	
	/** Unable to delete a metadata value */
	const DELETE_FAIL = 4;
	
	/** Unable to create an individual backing table */
	const CREATE_TABLE_FAIL = 5;
	
	/** Unable to prepare the database for use to back the array */
	const PREPARE_DATABASE_FAIL = 6;
}

?>