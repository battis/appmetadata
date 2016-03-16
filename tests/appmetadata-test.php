<?php

require_once('common.inc.php');

try {
	$metadata = new Battis\AppMetadata($sql, __FILE__);
	
	$metadata['A'] = 'foo';
	echo "\$metadata['A'] = 'foo';\n";
	html_var_dump($metadata['A']);
	html_var_dump($metadata['B']);
	$metadata['B'] = '@A/bar';
	echo "\$metadata['B'] = '@A/bar';";
	html_var_dump($metadata['A']);
	html_var_dump($metadata['B']);
	$metadata['A'] = 'rutabega';
	echo "\$metadata['A'] = 'rutabega';";
	html_var_dump($metadata['A']);
	html_var_dump($metadata['B']);
	unset($metadata['A']);
	echo "unset(\$metadata['A']);";
	html_var_dump($metadata['A']);
	html_var_dump($metadata['B']);
	$metadata['A'] = 'watermelon';
	echo "\$metadata['A'] = 'watermelon';";
	html_var_dump($metadata['A']);
	html_var_dump($metadata['B']);
	
	echo "\$metadata['C'] = false;";
	$metadata['C'] = false;
	html_var_dump($metadata['C']);
	
	echo "\$metadata['D'] = new CanvasPest('a','b');";
	$metadata['D'] = new CanvasPest('a','b');
	html_var_dump($metadata['D']);
	
	echo "testing \$metadata['E'] which is not set";
	html_var_dump($metadata['E']);
	
	echo "\$metadata->derivedValues('@A foo @B bar@C @D')";
	html_var_dump($metadata->derivedValues('@A foo @B bar@C @D'));
	
} catch (\Battis\AppMetadata_Exception $e) {
	html_var_dump($e);
}
?>