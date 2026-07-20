# DocbookCS

A static-analysis linter for DocBook XML files. It scans XML documentation sources and reports style and convention violations.

**Full documentation:** [php.github.io/docbook-cs](https://php.github.io/docbook-cs)

---

## Contributing

### Requirements

- PHP 8.5+
- Extensions: `dom`, `libxml`, `simplexml`

### Setup

```bash
composer install
```

### Running checks

```bash
# Tests
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan

# Code style
vendor/bin/phpcs
```

### Writing a sniff

Implement `DocbookCS\Sniff\SniffInterface` (or extend `AbstractSniff`):

```php
namespace Acme\DocbookSniffs;

use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Source\File;

final class MySniff extends AbstractSniff
{
    public static function getCode(): string
    {
        return 'Acme.MySniff';
    }

    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];
        // ... inspect $document, add violations via $this->createViolation(...)
        return $violations;
    }
}
```

Register it in your config:

```xml
<sniff class="Acme\DocbookSniffs\MySniff" />
```

## CLI Scope

By default, DocbookCS scans the selected files and any XML files reached through
referenced `SYSTEM` entities. `--strict` limits the run to exactly the files or
directories selected on the command line or in the configuration.

With `--diff`, source files remain restricted to violations whose source range
intersects an added line. Referenced target files are scanned as whole files
unless `--strict` is set. An atomic fix may update every affected range of a
selected violation, such as both names of a matching opening and closing tag.

## License

Apache 2.0
