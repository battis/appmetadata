# AppMetadata

This is one of those things that feels like it should already exist, but my cursory search of the interwebs failed to turn it up:
an associative array akin to `$_GLOBALS` that is backed by a persistent datastore (MySQL in this case, because it's the only
thing I ever use, because… lazy, I guess).

### Usage

Update your [`composer.json`](https://getcomposer.org) file to include the following.

```JSON
{
  "requires": {
    "battis/appmetadata": "dev-master"
  }
}
```

Create an `AppMetadata` object and treat it as you would any other associative array.

```PHP
// instantiate a new mysqli database connection
$sql = new mysqli('localhost', 'root', 's00pers3kr3t', 'demo-db');

// first use (create database tables -- only needs to happen once!)
AppMetadata::prepareDatabase($sql);

// instantiate our metadata array
$metadata = new AppMetadata($sql, 'my-unique-app-key');

// store something into the database
$metadata['X'] = 'foobar';

// read something out of the database
echo $metadata['X'];

// remove something from the database
unset($metadata['X']);
```
