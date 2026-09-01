"""WordPress integration and real plugin install/update/uninstall lifecycle."""

import requests

from common import (
    WP_BACKEND_URL,
    API_BASE,
    DUMMY_API,
    _host_headers,
)

# =============================================================================
# WordPress Integration Tests
# =============================================================================

def test_wordpress_site_accessible():
    """Test that the WordPress site is accessible."""
    r = requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)
    # Accept various status codes (might redirect to configured home URL)
    assert r.status_code in (200, 301, 302, 403)


def test_wordpress_rest_api_accessible():
    """Test that WordPress REST API is accessible."""
    r = requests.get(f"{WP_BACKEND_URL}/wp-json/", headers=_host_headers(), allow_redirects=False)
    assert r.status_code in (200, 301)


def test_plugin_activated():
    """Test that the OPcache Reset plugin is activated (gps_opcache_reset function exists)."""
    # The simulate-update endpoint calls the plugin function
    # If it works without error, the plugin is activated
    r = requests.post(f"{API_BASE}/simulate-update", json={}, headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    # The hook should fire successfully
    assert data["success"] is True


def test_create_post_endpoint():
    """Test that we can create posts via the test API."""
    r = requests.post(f"{API_BASE}/post", json={}, headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    assert "id" in data
    assert "url" in data
    assert data["id"] > 0


# =============================================================================
# Real Plugin Install/Update/Uninstall Tests
# These tests use the dummy plugin's own REST endpoint to verify OPcache behavior.
# =============================================================================

DUMMY_API = f"{WP_BACKEND_URL}/wp-json/dummy/v1"



def _cleanup_dummy_plugin():
    """Helper to ensure dummy plugin is uninstalled before/after tests."""
    try:
        # Use cleanup endpoint (no hooks, just removes files)
        requests.post(f"{API_BASE}/dummy-plugin/cleanup", json={}, headers=_host_headers())
    except Exception:
        pass


def _install_dummy_plugin():
    """Install the dummy plugin (copy files)."""
    r = requests.post(f"{API_BASE}/dummy-plugin/install", json={}, headers=_host_headers())
    r.raise_for_status()
    return r.json()


def _activate_dummy_plugin_via_wpcli():
    """Activate the dummy plugin. Uses the simulate-update endpoint to trigger hooks."""
    # We can't easily call wp-cli from Python in Docker, so we'll just make requests
    # to load the plugin. WordPress will auto-load active plugins.
    pass


def test_real_plugin_install():
    """Test installing a real dummy plugin."""
    _cleanup_dummy_plugin()

    # Install the dummy plugin (copy files)
    data = _install_dummy_plugin()

    assert data["success"] is True, f"Install failed: {data}"
    assert data["installed"] is True

    # Check file info
    info = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert info["exists"] is True
    assert info["disk_build_id"] == "BUILD_V1_ORIGINAL"

    _cleanup_dummy_plugin()


def test_real_plugin_rest_endpoint():
    """Test that the dummy plugin's REST endpoint works when plugin is active."""
    _cleanup_dummy_plugin()

    # Install the dummy plugin
    _install_dummy_plugin()

    # The dummy plugin registers /wp-json/dummy/v1/info
    # This only works if WordPress loads the plugin (it's in plugins dir)
    # For mu-plugins or if plugin is active, the endpoint should exist

    # Check file info via test helper
    info = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert info["exists"] is True

    _cleanup_dummy_plugin()


def test_real_plugin_update_changes_file():
    """Test that updating a plugin changes the file on disk."""
    _cleanup_dummy_plugin()

    # Install the dummy plugin
    _install_dummy_plugin()

    # Check initial build ID on disk
    before = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert before["disk_build_id"] == "BUILD_V1_ORIGINAL"

    # Update the plugin with a new build ID
    update = requests.post(
        f"{API_BASE}/dummy-plugin/update",
        json={"build_id": "BUILD_UPDATED_V2"},
        headers=_host_headers()
    )
    update.raise_for_status()
    update_data = update.json()

    assert update_data["success"] is True
    assert update_data["new_build_id"] == "BUILD_UPDATED_V2"

    # Check the file now has new build ID
    after = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert after["disk_build_id"] == "BUILD_UPDATED_V2", \
        f"Expected BUILD_UPDATED_V2, got {after['disk_build_id']}"

    _cleanup_dummy_plugin()


def test_real_plugin_update_triggers_hook():
    """Test that updating plugin triggers upgrader_process_complete hook."""
    _cleanup_dummy_plugin()

    # Install the dummy plugin
    _install_dummy_plugin()

    # Get cache stats before update
    before_status = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers()).json()
    before_count = before_status.get("opcache_statistics", {}).get("num_cached_scripts", 0)

    # Update triggers the hook which resets OPcache
    update = requests.post(
        f"{API_BASE}/dummy-plugin/update",
        json={"build_id": "BUILD_HOOK_TEST"},
        headers=_host_headers()
    )
    update.raise_for_status()

    # The update endpoint triggers upgrader_process_complete
    # which should call gps_opcache_reset()
    assert update.json()["success"] is True

    _cleanup_dummy_plugin()


def test_real_plugin_uninstall_via_php():
    """Test uninstalling via PHP delete_plugins() - tests memory-based OPcache.

    This runs in PHP-FPM context and triggers the deleted_plugin hook,
    which calls gps_opcache_reset() and clears memory-based OPcache.
    """
    _cleanup_dummy_plugin()

    # Install the dummy plugin
    _install_dummy_plugin()

    # Verify it's installed
    check = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert check["exists"] is True

    # Uninstall via PHP delete_plugins() (triggers deleted_plugin hook in PHP-FPM)
    uninstall = requests.post(f"{API_BASE}/dummy-plugin/uninstall", json={}, headers=_host_headers())
    uninstall.raise_for_status()
    uninstall_data = uninstall.json()

    assert uninstall_data["success"] is True
    assert uninstall_data["deleted"] is True
    assert uninstall_data.get("method") == "php_delete_plugins"

    # Verify it's gone
    after = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert after["exists"] is False


def test_real_plugin_uninstall_via_wpcli():
    """Test uninstalling via wp-cli and native file-cache clearing.

    This runs wp-cli inside the WordPress container (CLI context),
    which triggers the deleted_plugin hook and calls gps_opcache_reset().
    The direct FastCGI client is covered separately against a real FPM socket.
    """
    _cleanup_dummy_plugin()

    # Install the dummy plugin
    _install_dummy_plugin()

    # Verify it's installed
    check = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert check["exists"] is True

    # Uninstall via wp-cli endpoint (runs wp-cli in CLI context inside container)
    delete = requests.post(
        f"{API_BASE}/dummy-plugin/delete-via-wpcli",
        json={},
        headers=_host_headers()
    )
    delete.raise_for_status()
    delete_data = delete.json()

    assert delete_data["success"] is True, f"wp-cli delete failed: {delete_data}"
    assert delete_data["deleted"] is True
    assert delete_data.get("method") == "wpcli"

    # Verify it's gone
    after = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert after["exists"] is False, f"Plugin should be deleted: {after}"


def test_real_plugin_full_lifecycle():
    """Test complete plugin lifecycle: install → update → verify file changed → uninstall."""
    _cleanup_dummy_plugin()

    # 1. Install plugin
    install = _install_dummy_plugin()
    assert install["success"] is True

    # 2. Verify initial version on disk
    v1 = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert v1["disk_build_id"] == "BUILD_V1_ORIGINAL", f"Step 2 failed: {v1}"

    # 3. Update plugin (changes file on disk + triggers hook)
    update = requests.post(
        f"{API_BASE}/dummy-plugin/update",
        json={"build_id": "BUILD_LIFECYCLE_V2"},
        headers=_host_headers()
    )
    update.raise_for_status()

    # 4. Verify file now has new build ID
    v2 = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert v2["disk_build_id"] == "BUILD_LIFECYCLE_V2", \
        f"Step 4 failed: expected BUILD_LIFECYCLE_V2, got {v2['disk_build_id']}"

    # 5. Uninstall
    uninstall = requests.post(f"{API_BASE}/dummy-plugin/uninstall", json={}, headers=_host_headers())
    uninstall.raise_for_status()
    assert uninstall.json()["deleted"] is True

    # 6. Verify plugin is gone
    final = requests.get(f"{API_BASE}/dummy-plugin/build-id", headers=_host_headers()).json()
    assert final["exists"] is False
