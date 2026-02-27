# PhpOffice Setup

This folder is prepared for PhpOffice libraries used for DOCX templating.

## Install dependency

Run from project root:

```bash
cd PhpFiles/PhpOffice
composer install
```

After install, include:

```php
require_once __DIR__ . '/PhpOffice/vendor/autoload.php';
```

Main package configured:
- `phpoffice/phpword`
