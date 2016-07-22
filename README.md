# AppMetadata

[![Latest Version](https://img.shields.io/packagist/v/battis/appmetadata.svg)](https://packagist.org/packages/battis/appmetadata)

This is one of those things that feels like it should already exist, but my cursory search of the interwebs failed to turn it up:
an associative array akin to `$_GLOBALS` that is backed by a persistent datastore (MySQL in this case, because it's the only
thing I ever use, because… lazy, I guess).

### Usage

Update your [`composer.json`](https://getcomposer.org) file to include the following.

```JSON
{
  "requires": {
    "battis/appmetadata": "~1.0"
  }
}
```

*Handy hint:* It always annoys me to have the overhead of documentation, unit tests, etc. for other projects included in mine. Per [this answer on Stack Overflow](http://stackoverflow.com/a/17069547), you can actually buy some small improvement by adding the `--prefer-dist` flag to `composer install` and `composer update`, as in:

```BASH
composer install --prefer-dist
```

Create an `AppMetadata` object and treat it as you would any other associative array.

```PHP
// instantiate a new mysqli database connection
$sql = new mysqli('localhost', 'root', 's00pers3kr3t', 'demo-db');

// first use (create database tables -- only needs to happen once!)
Battis\AppMetadata::prepareDatabase($sql);

// instantiate our metadata array
$metadata = new Battis\AppMetadata($sql, 'my-unique-app-key');

// store something into the database
$metadata['X'] = 'foobar';

// read something out of the database
echo $metadata['X']; // 'foobar'

// use one metadata value to derive another
$metadata['Y'] = '@X again'
echo $metadata['Y']; // 'foobar again';

// derived values update automagically
$metadata['X'] = 'xoxo';
echo $metadata['Y']; // 'xoxo again';

// remove something from the database
unset($metadata['X']);
echo $metadata['Y']; // '@X again', since no value X to derive from
```

Complete documentation is available [within the package](http://htmlpreview.github.io/?https://github.com/battis/appmetadata/blob/master/doc/index.html).
