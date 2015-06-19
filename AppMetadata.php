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
				if (!$this->sql->query('UPDATE `' . AppMetadata::TABLE_NAME . "` SET `value` = '$_value' WHERE `key` = '$_key'")) {
					throw new AppMetadata_Exception("Unable to update app metadata (`$_key` = '$_value'). {$this->sql->error}");
				}
			} else {
				if (!$this->sql->query('INSERT INTO `' . AppMetadata::TABLE_NAME . "` (`key`, `value`) VALUES ('$_key', '$_value')")) {
					throw new AppMetadata_Exception("Unable to insert app metadata (`$_key` = '$_value'). {$this->sql->error}");
				}
			}
		}
		return parent::offsetSet($key, $value);
	}
}

class AppMetadata_Exception extends Exception {}

?>