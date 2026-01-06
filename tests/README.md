# Test Suite for Bleikøya 2023 Theme

This directory contains the PHPUnit test suite for the Bleikøya 2023 WordPress theme.

## Test Types

### Unit Tests (Default)
Unit tests run without WordPress, using mock functions. They're fast and test isolated logic.

```bash
# Run unit tests
composer test:unit
# or
./vendor/bin/phpunit --testsuite Unit
```

### Integration Tests
Integration tests run with a real WordPress installation and database. They test how code works with WordPress APIs.

```bash
# First, set up the test environment (one-time)
bash tests/bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run integration tests
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
TEST_TYPE=integration ./vendor/bin/phpunit --testsuite Integration
```

## Directory Structure

```
tests/
├── bin/
│   └── install-wp-tests.sh  # WordPress test lib installer
├── integration/             # Integration tests (require WP)
│   ├── LocationConnectionsIntegrationTest.php
│   └── LocationCoordinatesIntegrationTest.php
├── mocks/
│   └── wordpress-mocks.php  # Mock WP functions for unit tests
├── unit/                    # Unit tests (no WP required)
│   ├── ICalFeedTest.php
│   ├── LocationConnectionsTest.php
│   └── LocationCoordinatesTest.php
├── bootstrap.php            # PHPUnit bootstrap
└── README.md
```

## Test Coverage

| Area | Unit Tests | Integration Tests |
|------|-----------|-------------------|
| Location Connections API | ✅ 39 tests | ✅ 18 tests |
| Location Coordinates API | ✅ 51 tests | ✅ 20 tests |
| iCal Feed Helpers | ✅ 47 tests | - |

## Writing Tests

### Unit Tests
Unit tests should test pure logic without database or WordPress dependencies:

```php
class MyTest extends TestCase
{
    protected function setUp(): void
    {
        reset_mock_data();  // Clear mock state
    }

    #[Test]
    public function my_function_does_something(): void
    {
        global $mock_posts;
        $mock_posts[100] = ['post_type' => 'kartpunkt'];

        $result = my_function(100);

        $this->assertEquals('expected', $result);
    }
}
```

### Integration Tests
Integration tests extend `WP_UnitTestCase` and use WordPress factories:

```php
class MyIntegrationTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        // Use factories to create test data
        $this->post_id = $this->factory->post->create();
    }

    #[Test]
    public function my_function_persists_to_database(): void
    {
        my_function($this->post_id, 'value');

        // Verify using real WordPress functions
        $stored = get_post_meta($this->post_id, '_my_meta', true);
        $this->assertEquals('value', $stored);
    }
}
```

## Setting Up Integration Tests

### Requirements
- MySQL/MariaDB database server
- PHP with mysqli extension
- svn command (for downloading WP test suite)

### macOS with Homebrew
```bash
# Install MySQL (if not already)
brew install mysql
brew services start mysql

# Create test database
mysql -u root -e "CREATE DATABASE wordpress_test;"

# Install test suite
bash tests/bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Ubuntu/Debian
```bash
# Install MySQL
sudo apt-get install mysql-server

# Create test database
sudo mysql -e "CREATE DATABASE wordpress_test;"

# Install test suite
bash tests/bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

## Continuous Integration

For GitHub Actions, add this workflow:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install

      - name: Run unit tests
        run: composer test:unit

      - name: Install WP test suite
        run: bash tests/bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

      - name: Run integration tests
        run: TEST_TYPE=integration ./vendor/bin/phpunit --testsuite Integration
        env:
          WP_TESTS_DIR: /tmp/wordpress-tests-lib
```
