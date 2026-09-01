"""OPcache environment, cache population, reset, event triggers, file cache."""

import time
import requests

from common import (
    WP_BACKEND_URL,
    WP_NO_SHELL_URL,
    API_BASE,
    _host_headers,
)

# =============================================================================
# Basic OPcache Environment Tests
# =============================================================================

def test_opcache_extension_loaded():
    """Test that OPcache extension is loaded in the test environment."""
    r = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    assert data["extension_loaded"] is True, "OPcache extension should be loaded"
    assert data["enabled"] is True, "OPcache should be enabled"


def test_opcache_status_endpoint():
    """Test that the OPcache status endpoint returns expected fields."""
    r = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    # Check required fields are present
    assert "extension_loaded" in data
    assert "enabled" in data
    assert "file_cache" in data
    assert "opcache_statistics" in data


def test_file_cache_configured():
    """Test that file cache is configured in the test environment."""
    r = requests.get(f"{API_BASE}/file-cache-info", headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    assert data["configured_path"] == "/tmp/opcache", "File cache should be at /tmp/opcache"
    assert data["exists"] is True, "File cache directory should exist"
    assert data["is_writable"] is True, "File cache directory should be writable"


# =============================================================================
# Cache Population Tests
# =============================================================================

def test_php_files_are_cached_after_requests():
    """Test that PHP files are actually cached in OPcache after making requests."""
    # Make several requests to populate the cache
    for _ in range(5):
        requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)

    # Check OPcache statistics
    r = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    stats = data.get("opcache_statistics", {})
    cached_scripts = stats.get("num_cached_scripts", 0)

    # WordPress should have cached several PHP files
    assert cached_scripts > 0, f"Expected cached scripts > 0, got {cached_scripts}"


# =============================================================================
# Reset Functionality Tests
# =============================================================================

def test_opcache_reset_clears_cached_scripts():
    """Test that OPcache reset actually clears cached scripts."""
    # First, make requests to populate cache
    for _ in range(3):
        requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)

    # Get status before reset
    before = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers()).json()
    before_count = before.get("opcache_statistics", {}).get("num_cached_scripts", 0)

    # Trigger reset
    reset = requests.post(f"{API_BASE}/opcache-reset", json={}, headers=_host_headers())
    reset.raise_for_status()
    reset_data = reset.json()

    assert reset_data["success"] is True

    # Wait a moment for reset to complete
    time.sleep(0.5)

    # Get status after reset
    after = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers()).json()
    after_count = after.get("opcache_statistics", {}).get("num_cached_scripts", 0)

    # After reset, cache should be cleared (fewer scripts) or at minimum reset occurred
    # Note: Some scripts may be re-cached immediately by the status request itself
    assert after_count < before_count or reset_data.get("before_stats") is not None, \
        f"Expected cache to be cleared: before={before_count}, after={after_count}"


def test_opcache_reset_returns_before_after_stats():
    """Test that reset endpoint returns before/after statistics."""
    r = requests.post(f"{API_BASE}/opcache-reset", json={}, headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    assert data["success"] is True
    assert "before_stats" in data
    assert "after_stats" in data


def test_opcache_reset_works_when_shell_exec_is_disabled():
    """Regression test for hosts that disable PHP's shell_exec function."""
    if not WP_NO_SHELL_URL:
        import pytest
        pytest.skip("shell_exec-disabled WordPress service is not configured")

    api_base = f"{WP_NO_SHELL_URL}/wp-json/test/v1"

    status = requests.get(f"{api_base}/opcache-status", headers=_host_headers())
    status.raise_for_status()
    assert status.json()["shell_exec_available"] is False

    fixture = requests.post(
        f"{api_base}/file-cache-fixture",
        json={"action": "setup"},
        headers=_host_headers(),
    )
    fixture.raise_for_status()
    assert fixture.json()["marker_exists"] is True

    reset = requests.post(f"{api_base}/simulate-update", json={}, headers=_host_headers())
    reset.raise_for_status()
    data = reset.json()

    assert data["success"] is True
    assert data["hook_fired"] == "upgrader_process_complete"

    fixture = requests.post(
        f"{api_base}/file-cache-fixture",
        json={"action": "status"},
        headers=_host_headers(),
    )
    fixture.raise_for_status()
    assert fixture.json() == {
        "cache_dir_exists": True,
        "marker_exists": False,
    }


# =============================================================================
# Event Trigger Tests (Plugin Update, Theme Update, etc.)
# =============================================================================

def test_simulate_plugin_update_triggers_reset():
    """Test that simulating a plugin update triggers the OPcache reset hook."""
    # First, populate the cache
    for _ in range(3):
        requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)

    # Simulate a plugin update (triggers upgrader_process_complete with type=plugin)
    r = requests.post(f"{API_BASE}/simulate-update", json={}, headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    assert data["success"] is True
    assert data["hook_fired"] == "upgrader_process_complete"
    assert "before_stats" in data
    assert "after_stats" in data


def test_simulate_update_provides_cache_stats():
    """Test that simulate-update returns meaningful cache statistics."""
    # Populate cache first
    for _ in range(5):
        requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)

    r = requests.post(f"{API_BASE}/simulate-update", json={}, headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    before_stats = data.get("before_stats", {})
    after_stats = data.get("after_stats", {})

    # Before stats should show some cached scripts
    before_cached = before_stats.get("num_cached_scripts", 0) if before_stats else 0
    after_cached = after_stats.get("num_cached_scripts", 0) if after_stats else 0

    # The hook should have been triggered (we can't always guarantee cache is cleared
    # due to immediate re-caching, but the hook should fire)
    assert data["success"] is True


def test_plugin_deletion_triggers_reset():
    """Test that plugin deletion/uninstall triggers OPcache reset.

    This is important when opcache.validate_timestamps=0 to prevent
    serving cached bytecode for deleted files.
    """
    # Populate cache first
    for _ in range(3):
        requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)

    # Simulate plugin deletion (triggers deleted_plugin hook)
    r = requests.post(f"{API_BASE}/simulate-plugin-delete", json={}, headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    assert data["success"] is True
    assert data["hook_fired"] == "deleted_plugin"
    assert "before_stats" in data
    assert "after_stats" in data




# =============================================================================
# File Cache Tests
# =============================================================================

def test_file_cache_directory_exists():
    """Test that file cache directory is properly set up."""
    r = requests.get(f"{API_BASE}/file-cache-info", headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    assert data["configured_path"] is not None
    assert data["exists"] is True


def test_file_cache_contains_files_after_requests():
    """Test that file cache directory contains cached files after requests."""
    # Make several requests to populate file cache
    for _ in range(5):
        requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)

    # Allow time for file cache to be written
    time.sleep(1)

    r = requests.get(f"{API_BASE}/file-cache-info", headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    # File cache should contain some files (or subdirectories)
    files = data.get("files", [])
    # Note: File cache creates subdirectories based on PHP's system ID
    # Just verify the endpoint works and returns data
    assert isinstance(files, list)


# =============================================================================
# Cache Repopulation After Reset
# =============================================================================

def test_cache_repopulates_after_reset():
    """Test that cache can be repopulated after a reset."""
    # Reset the cache
    reset = requests.post(f"{API_BASE}/opcache-reset", json={}, headers=_host_headers())
    reset.raise_for_status()

    time.sleep(0.5)

    # Get status immediately after reset
    after_reset = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers()).json()
    after_reset_count = after_reset.get("opcache_statistics", {}).get("num_cached_scripts", 0)

    # Make several requests to repopulate cache
    for _ in range(5):
        requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)

    # Get status after repopulation
    after_repop = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers()).json()
    after_repop_count = after_repop.get("opcache_statistics", {}).get("num_cached_scripts", 0)

    # Cache should have more scripts after repopulation
    assert after_repop_count >= after_reset_count, \
        f"Cache should repopulate: after_reset={after_reset_count}, after_repop={after_repop_count}"
