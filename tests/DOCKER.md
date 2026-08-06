# Local WordPress + OPcache testbed

This docker setup spins up:
- MariaDB
- WordPress (Apache, PHP 8.2 with OPcache enabled)
- WP-CLI
- Python pytest tester

The plugin in this repo is mounted into the WordPress container at `wp-content/plugins/opcache-reset`.

## Prereqs
- Docker and Docker Compose

## Usage

```bash
make up         # start services
make setup      # install WP, enable permalinks, activate plugin
make tests      # run pytest suite
make pytest     # run pytest directly
make logs       # follow logs
make down       # stop and remove containers
make clean      # also remove volumes
```

If port 8080 is in use, specify a different port:

```bash
TEST_PORT=8888 make up
TEST_PORT=8888 make setup
TEST_PORT=8888 make tests
```

## Notes
- OPcache is configured with file cache at `/tmp/opcache` for testing file cache clearing.
- A dedicated PHP-FPM service verifies the plugin's direct FastCGI reset path.
- The plugin hooks into `upgrader_process_complete` to reset OPcache after WordPress updates.

## Tests

The `tester` service runs Python `pytest` inside Docker and relies on an MU plugin providing REST control endpoints.

Run tests:

```bash
make tests
```

Notes:
- Tests live under `tests/`.
- `tests/mu-plugins/test-control.php` exposes REST endpoints:
  - `GET /wp-json/test/v1/opcache-status` - Get OPcache status
  - `POST /wp-json/test/v1/opcache-reset` - Trigger OPcache reset
  - `POST /wp-json/test/v1/simulate-update` - Simulate plugin update (fires `upgrader_process_complete`)
  - `GET /wp-json/test/v1/file-cache-info` - Get file cache directory info
  - `POST /wp-json/test/v1/post` - Create a test post

For isolated local runs, you can use `tests/docker-compose.yml` and `tests/setup.sh`:

```bash
cd tests
export TEST_PORT=8888  # if 8080 is in use
docker compose up -d
bash setup.sh
docker compose run --rm tester -q
docker compose down -v
```
