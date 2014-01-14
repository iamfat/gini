# Gini PHP Framework


## Preface
* [Introduction](intro)
* [Philosophy](phi)

## Get Started

## CLI

## CGI

## API (HTTP JSON-RPC 2.0)

## Database

```php
namespace Gini;

$COMPOSER_DIR = getenv("COMPOSER_HOME") ?: getenv("HOME") . '/.composer';
require_once "$COMPOSER_DIR/vendor/iamfat/gini/lib/cgi.php";
```