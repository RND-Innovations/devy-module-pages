# DeVy Pages Module

Page management module for the **DeVy Framework**.

The Pages module provides a flexible system for creating, managing, organizing, building, and publishing custom pages across DeVy applications.

## Requirements

* PHP 8.3+
* DeVy Core `^1.0`

## Installation

The Pages module is installed by the **DeVy Installer**.

It is placed in the application's modules directory:

```text
modules/
└── Pages/
```

Manual installation is not recommended unless you are developing or testing the module.

## Module Structure

The module follows the standard DeVy module structure:

```text
Pages/
├── src/
│   ├── Controllers/
│   └── Services/
├── assets/
│   └── ...
├── views/
│   └── ...
├── module.php
├── composer.json
├── README.md
└── LICENSE
```

## Namespace

The module uses the following PHP namespace:

```php
DeVy\Modules\Pages
```

The physical module directory is:

```text
modules/Pages/
```

The directory name and PHP namespace are intentionally separate.

## Features

The Pages module provides functionality for:

* Create and manage custom pages
* Edit page content and metadata
* Publish and manage page status
* Build pages using configurable fields
* Organize pages into a hierarchical structure
* Page tree and page organization management
* Custom page templates
* Public page rendering
* Administrative page management
* Page builder functionality
* Integration with DeVy themes and templates
* Integration with the DeVy hook and rendering systems

The module is designed to provide the page-management layer independently from the site's visual theme.

## Page Builder

The Pages module includes a page builder system for creating structured page content using configurable fields.

This allows applications and themes to define reusable content structures without requiring individual page templates to contain all content directly.

## Development

Clone the DeVy framework and place this module in:

```text
modules/Pages/
```

After making changes to PHP classes, regenerate Composer's autoloader if necessary:

```bash
composer dump-autoload
```

## Compatibility

| DeVy Core | Pages Module |
| --------- | ------------ |
| 1.x       | 1.x          |

## License

This module is released under the MIT License.

See [LICENSE](LICENSE) for the full license text.

---

**DeVy Framework**
Developed by **RND Innovations**
