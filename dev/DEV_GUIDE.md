# PropertyPermissions Development Guide

This directory contains tools for running PropertyPermissions inside a full MediaWiki environment (using `labki-platform`) and executing the extension's PHPUnit test suite.

## Run MediaWiki + PropertyPermissions

The environment is defined in `docker-compose.yml` in the root of the extension.

### Using the Helper Script

To reset and start the environment fresh (recommended for first run):

```bash
# From the extension root
./tests/scripts/reinstall_test_env.sh
```

This script will:
- Stop any running containers
- Build and start the environment using `docker-compose.yml`
- Wait for the database to initialize
- Rebuild the localisation cache

### Using Docker Compose Directly

You can also manage the environment manually:

```bash
docker compose up -d
```

## Accessing the Wiki

Once running, visit:

```
http://localhost:8888
```

## Run PHPUnit Tests

Inside the running MediaWiki container (service name `wiki`):

```bash
docker compose exec -T wiki php tests/phpunit/phpunit.php \
  --testsuite extensions \
  --filter PropertyPermissions
```

Or run the full extension suite:

```bash
docker compose exec wiki bash -lc 'composer phpunit:entrypoint -- extensions/PropertyPermissions/tests/phpunit'
```

Tests live in:

```
extensions/PropertyPermissions/tests/phpunit/
```

## Clean and Reset Environment

To remove the environment:

```bash
docker compose down -v
```
