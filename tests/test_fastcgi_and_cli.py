"""Direct FastCGI reset client and WP-CLI command tests."""

import requests

from common import (
    API_BASE,
    _host_headers,
)

# =============================================================================
# FastCGI Direct Reset Tests
# These tests verify the direct FastCGI client for OPcache reset.
# =============================================================================

def test_opcache_reset_function_works():
    """Test that opcache_reset() works in the PHP-FPM environment."""
    # Verify opcache_reset() can be called successfully
    r = requests.post(
        f"{API_BASE}/fastcgi-test",
        json={"action": "test_opcache_reset_works"},
        headers=_host_headers()
    )
    r.raise_for_status()
    data = r.json()

    # opcache_reset() should succeed
    assert data["success"] is True, f"opcache_reset() failed: {data}"
    assert data["response"] == "OK", f"Expected 'OK' response, got: {data['response']}"


def test_cachetool_yml_setup_and_parsing():
    """Test that cachetool.yml can be created and parsed correctly."""
    # Cleanup first
    requests.post(
        f"{API_BASE}/fastcgi-test",
        json={"action": "cleanup_cachetool_yml"},
        headers=_host_headers()
    )

    # Create cachetool.yml with a test socket path
    setup = requests.post(
        f"{API_BASE}/fastcgi-test",
        json={"action": "setup_cachetool_yml", "socket": "/run/php-fpm.sock"},
        headers=_host_headers()
    )
    setup.raise_for_status()
    setup_data = setup.json()

    assert setup_data["success"] is True, f"Failed to create cachetool.yml: {setup_data}"

    # Test that the YAML is parsed correctly
    parse = requests.post(
        f"{API_BASE}/fastcgi-test",
        json={"action": "test_yml_parsing"},
        headers=_host_headers()
    )
    parse.raise_for_status()
    parse_data = parse.json()

    assert parse_data["success"] is True, f"YAML parsing failed: {parse_data}"
    assert parse_data["config_path"] is not None, "Config path should not be None"
    assert parse_data["socket"] == "/run/php-fpm.sock", \
        f"Expected socket '/run/php-fpm.sock', got: {parse_data['socket']}"

    # Cleanup
    requests.post(
        f"{API_BASE}/fastcgi-test",
        json={"action": "cleanup_cachetool_yml"},
        headers=_host_headers()
    )


def test_cachetool_yml_not_found_returns_null():
    """Test that missing cachetool.yml returns null socket."""
    # Cleanup to ensure no cachetool.yml exists
    requests.post(
        f"{API_BASE}/fastcgi-test",
        json={"action": "cleanup_cachetool_yml"},
        headers=_host_headers()
    )

    # Test parsing without a config file
    parse = requests.post(
        f"{API_BASE}/fastcgi-test",
        json={"action": "test_yml_parsing"},
        headers=_host_headers()
    )
    parse.raise_for_status()
    parse_data = parse.json()

    assert parse_data["success"] is True
    assert parse_data["config_path"] is None, \
        f"Config path should be None when no cachetool.yml exists: {parse_data}"
    assert parse_data["socket"] is None, \
        f"Socket should be None when no cachetool.yml exists: {parse_data}"


def test_fastcgi_client_end_to_end():
    """End-to-end test: FastCGI client connects to PHP-FPM and resets OPcache.

    This test proves the complete FastCGI flow works:
    1. FastCGI client connects to PHP-FPM socket
    2. Sends request with GPS_OPCACHE_RESET_INTERNAL=1
    3. PHP-FPM executes opcache-reset.php
    4. Handler detects the param and calls opcache_reset()
    5. Returns success
    """
    r = requests.post(
        f"{API_BASE}/fastcgi-test",
        json={"action": "test_fastcgi_client_e2e", "socket": "/run/php/php-fpm.sock"},
        headers=_host_headers()
    )
    r.raise_for_status()
    data = r.json()

    # If socket doesn't exist, skip the test (PHP-FPM service not running)
    if not data["success"] and "Socket not found" in data.get("error", ""):
        import pytest
        pytest.skip("PHP-FPM socket not available - php-fpm service may not be running")

    assert data["success"] is True, f"FastCGI client E2E test failed: {data}"
    assert data["socket"] == "/run/php/php-fpm.sock", f"Wrong socket path: {data}"


# =============================================================================
# WP-CLI Command Tests
# =============================================================================

def test_wpcli_opcache_reset():
    """Test 'wp opcache reset' command."""
    r = requests.post(
        f"{API_BASE}/wpcli",
        json={"command": "opcache reset"},
        headers=_host_headers()
    )
    r.raise_for_status()
    data = r.json()

    assert data["success"] is True, f"WP-CLI opcache reset failed: {data}"
    assert "Success" in data["output"], f"Expected success message in output: {data['output']}"


def test_wpcli_opcache_status():
    """Test 'wp opcache status' command."""
    r = requests.post(
        f"{API_BASE}/wpcli",
        json={"command": "opcache status"},
        headers=_host_headers()
    )
    r.raise_for_status()
    data = r.json()

    assert data["success"] is True, f"WP-CLI opcache status failed: {data}"
    # Should contain status table with expected properties
    assert "Cached Scripts" in data["output"], f"Expected 'Cached Scripts' in output: {data['output']}"
    assert "Memory" in data["output"], f"Expected 'Memory' in output: {data['output']}"


def test_wpcli_opcache_status_json():
    """Test 'wp opcache status --format=json' command."""
    r = requests.post(
        f"{API_BASE}/wpcli",
        json={"command": "opcache status --format=json"},
        headers=_host_headers()
    )
    r.raise_for_status()
    data = r.json()

    assert data["success"] is True, f"WP-CLI opcache status --format=json failed: {data}"

    # Parse the JSON output
    import json
    try:
        status = json.loads(data["output"])
        assert "enabled" in status, f"Expected 'enabled' key in JSON output: {status}"
        assert "cached_scripts" in status, f"Expected 'cached_scripts' key in JSON output: {status}"
        assert "memory_used" in status, f"Expected 'memory_used' key in JSON output: {status}"
    except json.JSONDecodeError as e:
        assert False, f"Failed to parse JSON output: {data['output']}, error: {e}"
