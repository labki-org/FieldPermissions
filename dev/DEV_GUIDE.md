# FieldPermissions Development Guide

This directory contains tools for running FieldPermissions inside a full MediaWiki environment and executing the extension's PHPUnit test suite.

## Run MediaWiki + FieldPermissions

From the `/dev/` directory:

First, make the script executable:

```bash
chmod +x setup_mw_test_env.sh
```

Then run it:

```bash
./setup_mw_test_env.sh
```

This script will:

- Clone MediaWiki (if needed)
- Start the MediaWiki Docker environment
- Mount the FieldPermissions extension into `/w/extensions/FieldPermissions`
- Update MediaWiki and enable the extension

Once complete, visit:

```
http://localhost:8080/w
```

## Run PHPUnit Tests

Inside the running MediaWiki container:

```bash
docker compose exec -T mediawiki php tests/phpunit/phpunit.php \
  --testsuite extensions \
  --filter FieldPermissions
```

Or run the full extension suite:

```bash
docker compose exec -T mediawiki php tests/phpunit/phpunit.php \
  --testsuite extensions
```

Tests live in:

```
extensions/FieldPermissions/tests/phpunit/
```

## Clean and Reset Environment

To remove the environment:

```bash
docker compose down -v
```

To fully reset the MediaWiki checkout, delete the `mediawiki-test` directory and re-run `setup_mw_test_env.sh`.
