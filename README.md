<p align="center">
  <img src="banner.png" alt="Shopware Plugin Boilerplate Banner">
</p>

# Shopware Plugin Boilerplate

A solid starting point for developing custom Shopware 6 extensions by Kommandhub.

## Features

- Pre-configured PHPUnit for testing.
- PHPStan for static analysis.
- PHP-CS-Fixer for coding standards.
- Docker-based development environment.
- Ready-to-use Makefile for common tasks.

## Getting Started

### Prerequisites

- Docker and Docker Compose
- Make

### Installation

1. Clone this repository into your Shopware `custom/plugins/` directory.
2. Navigate to the plugin directory:
   ```bash
   cd custom/plugins/ShopwarePluginBoilerplate
   ```
3. Start the development environment:
   ```bash
   make up
   ```

## Development

### Running Tests
```bash
make test
```

### Static Analysis
```bash
make analyse
```

### Coding Standards
```bash
make cs
make cs-fix
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
