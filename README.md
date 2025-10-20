# WordPress Plugin Activator

A comprehensive WordPress MU plugin management system that automates plugin activation based on environment-specific configurations, version constraints, and conditional logic.

## Features

- **Environment-Specific Activation** - Different plugin sets for development, staging, and production
- **Version Constraint Management** - Automatic validation of plugin compatibility requirements  
- **Multiple Activation Strategies** - Direct, group-based, hook-based, and settings-driven activation
- **Automated Cleanup** - Deactivates incompatible or unlisted plugins automatically
- **Comprehensive Logging** - Detailed error reporting and monitoring capabilities
- **Security Validation** - Permission checks, nonce verification, and input sanitization

## Installation

1. Upload the plugin to your `/wp-content/mu-plugins/` directory
2. Create a configuration file (see Configuration section)
3. The plugin will automatically load and manage plugins based on your configuration

## Configuration

Create a JSON configuration file with your plugin specifications:

```json
{
  "plugins": [
    {
      "file": "query-monitor/query-monitor.php",
      "required": true,
      "version": ">=3.0.0",
      "order": 1
    }
  ],
  "groups": {
    "development": {
      "url": "dev.yoursite.com",
      "plugins": [
        {
          "file": "debug-bar/debug-bar.php",
          "required": false
        }
      ]
    }
  },
  "filtered": [
    {
      "hook": "admin_init",
      "priority": 10,
      "plugins": [
        {
          "file": "admin-only-plugin/plugin.php"
        }
      ]
    }
  ],
  "settings": [
    {
      "field": "environment",
      "operator": "==",
      "value": "production",
      "plugins": [
        {
          "file": "production-plugin/plugin.php"
        }
      ]
    }
  ]
}
```

### Configuration Keys

| Key | Description | Example |
|-----|-------------|---------|
| `file` | Plugin file path relative to plugins directory | `"woocommerce/woocommerce.php"` |
| `required` | Whether plugin is essential for site operation | `true` or `false` |
| `version` | Version constraint for compatibility checking | `">=2.0.0"`, `"<3.0"` |
| `order` | Activation sequence (lower numbers first) | `1`, `10`, `20` |
| `hook` | WordPress action/filter name | `"init"`, `"wp_head"` |
| `priority` | Hook execution priority | `10` (default) |
| `field` | WordPress option/setting to check | `"active_theme"` |
| `operator` | Comparison operator | `"=="`, `"!="`, `">="`, `"contains"` |
| `value` | Expected value for comparison | `"production"`, `"enabled"` |
| `url` | Domain pattern for environment targeting | `"staging.site.com"` |

## Architecture

The plugin follows a modular architecture with single responsibility principles:

### Core Components

- **ActivationController** - Handles HTTP requests and coordinates activation
- **ActivatorInterface** - Common interface for all activation strategies
- **ActivationUtils** - Centralized utility methods for plugin management
- **ConfigLoader** - Configuration file loading and validation

### Activator Classes

- **PluginActivator** - Direct plugin activation with version constraints
- **GroupActivator** - Environment-based plugin groups
- **FilterActivator** - Hook-based conditional activation  
- **SettingsActivator** - Settings-driven conditional loading

## Usage Examples

### Basic Plugin Activation
```json
{
  "plugins": [
    {
      "file": "essential-plugin/plugin.php",
      "required": true,
      "version": ">=1.0.0"
    }
  ]
}
```

### Environment-Specific Groups
```json
{
  "groups": {
    "development": {
      "url": "localhost",
      "plugins": [
        {"file": "query-monitor/query-monitor.php"},
        {"file": "debug-bar/debug-bar.php"}
      ]
    },
    "production": {
      "url": "yoursite.com",
      "plugins": [
        {"file": "caching-plugin/cache.php"}
      ]
    }
  }
}
```

### Hook-Based Activation
```json
{
  "filtered": [
    {
      "hook": "admin_init",
      "priority": 5,
      "plugins": [
        {"file": "admin-only-plugin/plugin.php"}
      ]
    }
  ]
}
```

### Conditional Activation
```json
{
  "settings": [
    {
      "field": "maintenance_mode",
      "operator": "!=",
      "value": "enabled",
      "plugins": [
        {"file": "public-plugin/plugin.php"}
      ]
    }
  ]
}
```

## Version Constraints

Supports semantic versioning with operators:

- `>=2.0.0` - Greater than or equal to version 2.0.0
- `<3.0` - Less than version 3.0
- `==1.5.0` - Exactly version 1.5.0
- `!=2.1.0` - Not version 2.1.0

## Logging

The plugin provides comprehensive logging for monitoring and debugging:

```
[PluginActivator] Plugin file not found: missing-plugin/plugin.php
[PluginActivator] REQUIRED plugin missing: essential-plugin/plugin.php
[PluginActivator] Version mismatch: old-plugin/plugin.php requires >=2.0.0, found 1.5.0
[PluginActivator] Deactivated unlisted plugins: unwanted-plugin/plugin.php
```

## Security Features

- **Permission Validation** - Checks `manage_plugins` capability
- **Nonce Verification** - CSRF protection for activation requests
- **Input Sanitization** - Prevents XSS and injection attacks
- **File Validation** - Ensures plugins exist before activation

## Testing

This plugin uses **Pest** for modern PHP testing with WordPress integration.

### Prerequisites

```bash
# Install development dependencies
composer install

# Setup WordPress test environment
composer run install-wp-tests
```

### Environment Configuration

Create a `.env.testing` file in the project root to customize test settings:

```bash
# Database settings for testing
WP_DB_NAME=wordpress_test
WP_DB_USER=root
WP_DB_PASS=your_password
WP_DB_HOST=localhost

# WordPress version to test against
WP_VERSION=latest

# Test directories (optional - defaults provided)
WP_CORE_DIR=/path/to/wordpress/core
WP_TESTS_DIR=/path/to/wp-tests-lib
```

### Running Tests

```bash
# Run all tests
composer run test

# Run with coverage report
composer run test:coverage

# Run with verbose output
composer run test:verbose

# Run specific test file
./vendor/bin/pest tests/phpunit/integration/ActivatorTest.php

# Run tests matching pattern
./vendor/bin/pest --filter="activates plugin"
```

### Test Coverage Areas

- ✅ Plugin activation/deactivation workflows
- ✅ Settings management and validation
- ✅ WordPress hook integration
- ✅ Filter-based conditional activation
- ✅ Error handling and edge cases
- ✅ Configuration validation
- ✅ Compatibility testing

## Code Quality & Linting

### PHP CodeSniffer (PHPCS)

Run code quality checks on source code:

```bash
# Check for all coding standard violations
composer run php:lint

# Auto-fix issues that can be automatically corrected
composer run php:fix

# Generate detailed report showing what needs manual fixes
composer run php:lint-report
```

**Note:** `php:fix` can automatically correct formatting issues (spacing, brackets, etc.) but **cannot** fix:
- Missing type hints
- Missing docblocks  
- Security violations
- Complex naming issues
- Logic-related standards

After running `php:fix`, you'll need to manually address remaining violations shown by `php:lint`.

### Converting Existing Files

```bash
# Auto-fix indentation and formatting issues
composer run php:fix

# Manual conversion from tabs to spaces (if needed)
find src/ -name "*.php" -exec sed -i '' 's/\t/    /g' {} \;
```

## Available Commands

| Command | Description |
|---------|-------------|
| `composer run test` | Run all Pest tests |
| `composer run test:coverage` | Run tests with coverage report |
| `composer run test:verbose` | Run tests with detailed output |
| `composer run php:lint` | Lint source code with highest standards |
| `composer run php:fix` | Auto-fix code style issues |
| `composer run php:lint-report` | Generate detailed linting report |
| `composer run install-wp-tests` | Setup WordPress test environment |

## Project Structure

```
satori-wp-plugin-activator/
├── .vscode/
│   └── settings.json         # VS Code configuration
├── src/
│   ├── Interfaces/
│   │   └── ActivatorInterface.php
│   ├── Activators/
│   │   ├── PluginActivator.php
│   │   ├── FilterActivator.php
│   │   ├── SettingsActivator.php
│   │   └── GroupActivator.php
│   ├── Controllers/
│   │   └── ActivationController.php
│   ├── Helpers/
│   │   ├── ConfigLoader.php
│   │   └── ActivationUtils.php
│   ├── Config/
│   │   └── ActivatorOptions.php
│   └── index.php
├── tests/
│   ├── phpunit/
│   │   ├── unit/             # Unit tests
│   │   │   ├── ActivationControllerTest.php
│   │   │   ├── ActivationUtilsTest.php
│   │   │   ├── FilterActivatorTest.php
│   │   │   ├── GroupActivatorTest.php
│   │   │   └── PluginActivatorTest.php
│   │   ├── integration/      # WordPress integration tests
│   │   │   ├── FilterHookExecutionTest.php
│   │   │   ├── SettingsToggleTest.php
│   │   │   └── ValidationTest.php
│   │   ├── helpers.php       # Test helper functions
│   │   └── bootstrap.php     # Test bootstrap file
│   └── bin/
│       └── install-wp-tests.sh
├── composer.json
├── phpcs.xml.dist            # Code quality configuration
└── README.md
```

## Test Structure

```
tests/
├── phpunit/
│   ├── unit/                 # Unit tests
│   │   ├── ActivationControllerTest.php
│   │   ├── ActivationUtilsTest.php
│   │   ├── FilterActivatorTest.php
│   │   ├── GroupActivatorTest.php
│   │   └── PluginActivatorTest.php
│   ├── integration/          # WordPress integration tests
│   │   ├── FilterHookExecutionTest.php
│   │   ├── SettingsToggleTest.php
│   │   └── ValidationTest.php
│   ├── helpers.php           # Test helper functions
│   └── bootstrap.php         # Test bootstrap file
└── bin/
    └── install-wp-tests.sh   # WordPress test setup script
```

## Debugging Tests

### Verbose Output
```bash
# Pest with detailed output
./vendor/bin/pest --verbose

# Run specific test with debugging
./vendor/bin/pest --filter="activates plugin successfully" --verbose
```

### Individual Test Execution
```bash
# Run specific test file
./vendor/bin/pest tests/phpunit/integration/ActivatorTest.php

# Run specific test method
./vendor/bin/pest --filter="activates plugin successfully"
```

### Test Database

Tests use a separate database (`wordpress_test` by default) that is:
- Created automatically during setup
- Cleaned between test runs
- Isolated from your development database

## Coverage Reports

Generate test coverage reports:

```bash
# Generate HTML coverage report
./vendor/bin/pest --coverage --coverage-html=coverage-report

# Generate coverage with minimum threshold
./vendor/bin/pest --coverage --min=80
```

Coverage reports will be generated in the `coverage-report/` directory.

## Continuous Integration

For CI/CD pipelines, use this sequence:

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader

# 2. Setup test environment
composer run install-wp-tests

# 3. Run code quality checks
composer run php:lint

# 4. Run all tests
composer run test
```

## Manual Fixes Required

Common issues requiring manual intervention after running `php:fix`:
- **Type Hints**: Add return types and parameter types
- **Documentation**: Add proper docblocks for classes and methods
- **Strict Comparisons**: Use `===` instead of `==`
- **Property Types**: Add type declarations to class properties

## Contributing

1. Follow the established coding standards
2. Run `composer run php:lint` before committing
3. Ensure all tests pass with `composer run test`
4. Add tests for new functionality
5. Update documentation as needed

## License

This project is licensed under the MIT License.

**Note:** All tests use Pest syntax (modern PHP testing) but maintain WordPress compatibility through the bootstrap configuration.