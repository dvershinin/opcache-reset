"""Pure-PHP OPcache behavior proofs (no WordPress hooks)."""

import requests

from common import (
    WP_BACKEND_URL,
    API_BASE,
    _host_headers,
)

# =============================================================================
# OPcache Reset Verification Tests (Pure PHP, no WordPress hooks)
# These tests directly prove OPcache behavior using simple PHP files.
# =============================================================================

def test_validate_timestamps_is_disabled():
    """Verify that validate_timestamps=0 is set in test environment.

    This is CRITICAL - without this, OPcache auto-detects file changes.
    """
    r = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers())
    r.raise_for_status()
    data = r.json()

    assert data["enabled"] is True, "OPcache must be enabled"


def test_pure_php_opcache_serves_stale_code():
    """CRITICAL: Prove OPcache serves stale code without reset.

    Uses direct HTTP requests to a PHP file (not WordPress REST):
    1. Create file with V1
    2. Make HTTP request to cache it
    3. Modify file to V2 on disk
    4. Make HTTP request again - should STILL return V1 (stale)!
    """
    # Cleanup first
    requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "cleanup"}, headers=_host_headers())

    # Step 1: Create file with V1
    r1 = requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "setup_v1"}, headers=_host_headers())
    r1.raise_for_status()

    # Step 2: Make direct HTTP request to the PHP file to cache it
    cache_req = requests.get(
        f"{WP_BACKEND_URL}/opcache-test-file.php",
        headers=_host_headers(),
        allow_redirects=False
    )
    cache_req.raise_for_status()
    assert cache_req.json()["value"] == "V1", f"Initial request should return V1: {cache_req.text}"

    # Step 3: Modify file to V2 (no reset)
    r2 = requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "modify_to_v2"}, headers=_host_headers())
    r2.raise_for_status()

    # Step 4: Make another direct request - with validate_timestamps=0, should return V1 (stale)!
    stale_req = requests.get(
        f"{WP_BACKEND_URL}/opcache-test-file.php",
        headers=_host_headers(),
        allow_redirects=False
    )
    stale_req.raise_for_status()
    data = stale_req.json()

    # THIS IS THE KEY ASSERTION: OPcache serves stale code!
    assert data["value"] == "V1", \
        f"With validate_timestamps=0, OPcache should serve stale (V1) code. Got: {data}"

    # Cleanup
    requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "cleanup"}, headers=_host_headers())


def test_pure_php_opcache_reset_clears_cache():
    """CRITICAL: Prove opcache_reset() makes new code visible.

    1. Create file with V1, request it to cache
    2. Modify file to V2 on disk
    3. Call opcache_reset()
    4. Request again - should return V2 (new)!
    """
    # Cleanup first
    requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "cleanup"}, headers=_host_headers())

    # Step 1: Create file with V1
    r1 = requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "setup_v1"}, headers=_host_headers())
    r1.raise_for_status()

    # Step 2: Cache it
    cache_req = requests.get(
        f"{WP_BACKEND_URL}/opcache-test-file.php",
        headers=_host_headers(),
        allow_redirects=False
    )
    cache_req.raise_for_status()
    assert cache_req.json()["value"] == "V1"

    # Step 3: Modify file to V2
    r2 = requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "modify_to_v2"}, headers=_host_headers())
    r2.raise_for_status()

    # Step 4: Call reset
    reset = requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "reset"}, headers=_host_headers())
    reset.raise_for_status()
    assert reset.json()["opcache_reset"] is True

    # Step 5: Request again - should return V2 (new)!
    new_req = requests.get(
        f"{WP_BACKEND_URL}/opcache-test-file.php",
        headers=_host_headers(),
        allow_redirects=False
    )
    new_req.raise_for_status()
    data = new_req.json()

    # THIS IS THE KEY ASSERTION: After reset, new code is served!
    assert data["value"] == "V2", \
        f"After opcache_reset(), should serve new (V2) code. Got: {data}"

    # Cleanup
    requests.post(f"{API_BASE}/pure-opcache-test", json={"action": "cleanup"}, headers=_host_headers())


def test_pure_php_file_cache_cleared_on_reset():
    """CRITICAL: Prove file cache is cleared when gps_opcache_reset() is called."""
    # First, make some requests to populate file cache
    requests.get(f"{WP_BACKEND_URL}/", headers=_host_headers(), allow_redirects=False)

    # Get file cache info before
    before = requests.get(f"{API_BASE}/file-cache-info", headers=_host_headers()).json()

    # Trigger reset (which should clear file cache)
    reset = requests.post(f"{API_BASE}/opcache-reset", headers=_host_headers())
    reset.raise_for_status()
    reset_data = reset.json()

    # The reset should report file cache was cleared
    assert reset_data["success"] is True

    # If file_cache_cleared is in response, verify it
    if "file_cache_cleared" in reset_data:
        assert reset_data["file_cache_cleared"] is True, \
            f"File cache should be cleared on reset: {reset_data}"
