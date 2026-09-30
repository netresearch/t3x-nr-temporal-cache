#!/usr/bin/env bash

#
# TYPO3 temporal_cache extension test runner
#
# Adopted from TYPO3 core testing setup. Unlike the core runner it starts no
# containers: it runs .Build/bin/phpunit with the PHP binary on the PATH, so
# run `composer install` first.
#

# Function to write a .env file in Build/testing-docker/local directory
# This file is used to load environment variables into docker-compose
setUpDockerComposeDotEnv() {
    # Delete possibly existing local .env file
    [ -e Build/testing-docker/local/.env ] && rm Build/testing-docker/local/.env

    # Set up a new .env file for docker-compose
    echo "COMPOSE_PROJECT_NAME=local" >> Build/testing-docker/local/.env
    echo "HOST_UID=$(id -u)" >> Build/testing-docker/local/.env
    echo "DOCKER_PHP_IMAGE=ghcr.io/typo3/core-testing-$(echo "php${PHP_VERSION}" | sed -e 's/\.//'):latest" >> Build/testing-docker/local/.env
    echo "DOCKER_DATABASE=database" >> Build/testing-docker/local/.env
    echo "SCRIPT_VERBOSE=${SCRIPT_VERBOSE}" >> Build/testing-docker/local/.env
    echo "CGLCHECK_DRY_RUN=${CGLCHECK_DRY_RUN}" >> Build/testing-docker/local/.env
    echo "DATABASE_DRIVER=${DATABASE_DRIVER}" >> Build/testing-docker/local/.env
}

# Options -a and -d depend on each other. The function
# validates input combinations and sets defaults.
handleDbmsOptions() {
    case ${DBMS} in
        mysql|mariadb)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid database driver ${DATABASE_DRIVER} for ${DBMS}" >&2
                echo >&2
                echo "call \"./Build/Scripts/runTests.sh -h\" to display help" >&2
                exit 1
            fi
            ;;
        postgres|sqlite)
            # Doctrine DBAL names the PostgreSQL PDO driver pdo_pgsql, not pdo_postgres.
            EXPECTED_DRIVER="pdo_sqlite"
            [ "${DBMS}" = "postgres" ] && EXPECTED_DRIVER="pdo_pgsql"
            if [ -n "${DATABASE_DRIVER}" ] && [ "${DATABASE_DRIVER}" != "${EXPECTED_DRIVER}" ]; then
                echo "Invalid database driver ${DATABASE_DRIVER} for ${DBMS}" >&2
                echo >&2
                echo "call \"./Build/Scripts/runTests.sh -h\" to display help" >&2
                exit 1
            fi
            DATABASE_DRIVER="${EXPECTED_DRIVER}"
            ;;
        *)
            echo "Invalid DBMS ${DBMS}" >&2
            echo >&2
            echo "call \"./Build/Scripts/runTests.sh -h\" to display help" >&2
            exit 1
            ;;
    esac
}

# Load help text into $HELP
read -r -d '' HELP <<EOF
TYPO3 temporal_cache test runner.

Usage: $0 [options] [file]

Options:
    -s <...>
        Specifies which test suite to run
            - unit (default): Run unit tests
            - functional: Run functional tests
            - acceptance: not implemented, exits with status 1

    -d <mysql|mariadb|postgres|sqlite>
        Only with -s functional
        Sets typo3DatabaseDriver (mysqli, pdo_pgsql or pdo_sqlite).
        No database server is started: for mysql, mariadb and postgres
        export typo3DatabaseHost, typo3DatabaseName, typo3DatabaseUsername
        and typo3DatabasePassword first. sqlite needs nothing else.
            - mysql (default)
            - mariadb
            - postgres
            - sqlite

    -p <8.1|8.2|8.3>
        Only validated and printed. The tests run with the PHP binary
        on the PATH, whatever its version.
            - 8.1
            - 8.2 (default)
            - 8.3

    -v
        Enable verbose script output

    -h
        Show this help.

Examples:
    # Run unit tests
    ./Build/Scripts/runTests.sh -s unit

    # Run functional tests with SQLite
    ./Build/Scripts/runTests.sh -s functional -d sqlite

    # Run specific unit test
    ./Build/Scripts/runTests.sh -s unit Tests/Unit/EventListener/TemporalCacheLifetimeTest.php
EOF

# Defaults
TEST_SUITE="unit"
DBMS="mysql"
PHP_VERSION="8.2"
SCRIPT_VERBOSE=0
DATABASE_DRIVER=""

# Option parsing
while getopts ":s:d:p:vh" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        d)
            DBMS=${OPTARG}
            ;;
        p)
            PHP_VERSION=${OPTARG}
            ;;
        v)
            SCRIPT_VERBOSE=1
            ;;
        h)
            echo "${HELP}"
            exit 0
            ;;
        \?)
            echo "Invalid option: -${OPTARG}" >&2
            echo >&2
            echo "${HELP}" >&2
            exit 1
            ;;
        :)
            echo "Option -${OPTARG} requires an argument" >&2
            echo >&2
            echo "${HELP}" >&2
            exit 1
            ;;
    esac
done

# Remove the parsed options
shift $((OPTIND - 1))

# Get remaining arguments (test file/path)
EXTRA_TEST_OPTIONS=("$@")

# Validation
if [ "${PHP_VERSION}" != "8.1" ] && [ "${PHP_VERSION}" != "8.2" ] && [ "${PHP_VERSION}" != "8.3" ]; then
    echo "Invalid PHP version ${PHP_VERSION}" >&2
    echo >&2
    echo "${HELP}" >&2
    exit 1
fi

handleDbmsOptions

# Run the test
case ${TEST_SUITE} in
    unit)
        echo "Running unit tests with PHP ${PHP_VERSION}"
        .Build/bin/phpunit -c Build/phpunit/UnitTests.xml "${EXTRA_TEST_OPTIONS[@]}"
        ;;
    functional)
        echo "Running functional tests with PHP ${PHP_VERSION} and ${DBMS}"
        export typo3DatabaseDriver="${DATABASE_DRIVER}"
        .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml "${EXTRA_TEST_OPTIONS[@]}"
        ;;
    acceptance)
        echo "Acceptance tests not yet implemented"
        exit 1
        ;;
    *)
        echo "Invalid test suite ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        exit 1
        ;;
esac
