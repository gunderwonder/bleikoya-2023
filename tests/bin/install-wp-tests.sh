#!/usr/bin/env bash

# WordPress Test Suite Installation Script
#
# This script downloads and sets up the WordPress test library needed
# for integration tests. It creates a temporary WordPress installation
# with a test database.
#
# Usage: ./install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# Example:
#   ./install-wp-tests.sh wordpress_test root '' localhost latest
#
# After running this script, set the WP_TESTS_DIR environment variable
# to the path shown in the output, then run:
#   TEST_TYPE=integration ./vendor/bin/phpunit --testsuite Integration

set -e

if [ $# -lt 3 ]; then
    echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
    echo ""
    echo "Example:"
    echo "  $0 wordpress_test root '' localhost latest"
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

# Directories
TMPDIR=${TMPDIR:-/tmp}
WP_TESTS_DIR=${WP_TESTS_DIR:-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-$TMPDIR/wordpress}

download() {
    if [ "$(which curl)" ]; then
        curl -s "$1" > "$2"
    elif [ "$(which wget)" ]; then
        wget -nv -O "$2" "$1"
    fi
}

# Get WordPress version
if [ "$WP_VERSION" == "latest" ]; then
    WP_VERSION=$(download https://api.wordpress.org/core/version-check/1.7/ - | grep -oP '"version":"\K[^"]+' | head -1)
    if [ -z "$WP_VERSION" ]; then
        echo "Error: Could not determine latest WordPress version"
        exit 1
    fi
fi

# Determine WordPress test tag
if [[ "$WP_VERSION" =~ [0-9]+\.[0-9]+ ]]; then
    WP_TESTS_TAG="tags/${WP_VERSION}"
else
    WP_TESTS_TAG="trunk"
fi

echo "Installing WordPress test suite..."
echo "  WordPress version: $WP_VERSION"
echo "  Test tag: $WP_TESTS_TAG"
echo "  WP_TESTS_DIR: $WP_TESTS_DIR"
echo "  WP_CORE_DIR: $WP_CORE_DIR"
echo ""

# Install WordPress core
install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then
        echo "WordPress core already installed at $WP_CORE_DIR"
        return
    fi

    mkdir -p "$WP_CORE_DIR"

    if [ "$WP_VERSION" == "latest" ]; then
        local ARCHIVE_URL="https://wordpress.org/latest.tar.gz"
    else
        local ARCHIVE_URL="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    fi

    echo "Downloading WordPress from $ARCHIVE_URL..."
    download "$ARCHIVE_URL" "$TMPDIR/wordpress.tar.gz"
    tar --strip-components=1 -zxmf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"

    echo "WordPress core installed."
}

# Install WordPress test suite
install_test_suite() {
    if [ -d "$WP_TESTS_DIR" ]; then
        echo "Test suite already installed at $WP_TESTS_DIR"
        return
    fi

    mkdir -p "$WP_TESTS_DIR"

    # Use develop.svn for test files
    local SVN_URL="https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit"

    echo "Downloading test suite from $SVN_URL..."

    # Download includes
    svn export --quiet "$SVN_URL/includes/" "$WP_TESTS_DIR/includes"

    # Download data
    svn export --quiet "$SVN_URL/data/" "$WP_TESTS_DIR/data"

    echo "Test suite installed."
}

# Create wp-tests-config.php
install_config() {
    if [ -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
        echo "Config already exists at $WP_TESTS_DIR/wp-tests-config.php"
        return
    fi

    echo "Creating wp-tests-config.php..."

    # Download sample config
    download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"

    # Configure database
    if [ "$(uname -s)" == "Darwin" ]; then
        local SED_CMD="sed -i ''"
    else
        local SED_CMD="sed -i"
    fi

    $SED_CMD "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
    $SED_CMD "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
    $SED_CMD "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
    $SED_CMD "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
    $SED_CMD "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config.php"

    echo "Config created."
}

# Create test database
install_db() {
    local MYSQL_ARGS="-u$DB_USER"

    if [ -n "$DB_PASS" ]; then
        MYSQL_ARGS="$MYSQL_ARGS -p$DB_PASS"
    fi

    if [ "$DB_HOST" != "localhost" ]; then
        MYSQL_ARGS="$MYSQL_ARGS -h$DB_HOST"
    fi

    echo "Creating test database '$DB_NAME'..."

    # Check if MySQL is available
    if ! command -v mysql &> /dev/null; then
        echo "Warning: mysql command not found. Please create the database manually:"
        echo "  CREATE DATABASE IF NOT EXISTS $DB_NAME;"
        return
    fi

    mysql $MYSQL_ARGS -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" 2>/dev/null || {
        echo "Warning: Could not create database. Please create it manually:"
        echo "  CREATE DATABASE IF NOT EXISTS $DB_NAME;"
    }
}

# Run installation
install_wp
install_test_suite
install_config
install_db

echo ""
echo "=========================================="
echo "WordPress test suite installed!"
echo ""
echo "To run integration tests:"
echo ""
echo "  export WP_TESTS_DIR=$WP_TESTS_DIR"
echo "  TEST_TYPE=integration ./vendor/bin/phpunit --testsuite Integration"
echo ""
echo "=========================================="
