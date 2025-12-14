import os
import time
import requests
import pytest
from urllib.parse import urlparse

WP_URL = os.environ.get("WP_URL", "http://localhost:8080")
WP_BACKEND_URL = os.environ.get("WP_BACKEND_URL", WP_URL)
API_BASE = f"{WP_BACKEND_URL}/wp-json/test/v1"
_parsed = urlparse(WP_URL)
HOST_HEADER_VALUE = "localhost:8080" if _parsed.hostname == "wordpress" else _parsed.netloc


def _host_headers():
    return {"Host": HOST_HEADER_VALUE}


def wait_http_ok(url: str, timeout: float = 60.0, headers=None, accept_codes=None):
    """Wait until an HTTP endpoint responds with one of acceptable status codes.
    Defaults to (200, 301, 302, 403, 503) to be tolerant during warmup.
    """
    if accept_codes is None:
        accept_codes = {200, 301, 302, 403, 503}
    start = time.time()
    while time.time() - start < timeout:
        try:
            r = requests.head(url, timeout=3, allow_redirects=False, headers=headers or {})
            if r.status_code in accept_codes:
                return
        except Exception:
            pass
        time.sleep(1)
    raise RuntimeError(f"Timeout waiting for {url}")


@pytest.fixture(scope="session", autouse=True)
def ensure_up():
    """Ensure WordPress is up before running tests."""
    if os.environ.get("OPCACHE_SKIP_ENSURE_UP") == "1":
        return

    try:
        wait_http_ok(WP_BACKEND_URL, timeout=120.0)
    except Exception:
        pass
    try:
        wait_http_ok(WP_URL, timeout=120.0, headers=_host_headers())
    except Exception:
        pass


@pytest.fixture()
def fresh_post():
    """Create a fresh post for testing."""
    r = requests.post(f"{API_BASE}/post", json={}, headers=_host_headers())
    r.raise_for_status()
    data = r.json()
    return data["id"], data["url"]


def get_headers(url: str):
    """Get response headers from a URL."""
    r = requests.head(url, allow_redirects=False, headers=_host_headers())
    r.raise_for_status()
    return {k.title(): v for k, v in r.headers.items()}


def get_opcache_status():
    """Get OPcache status via test helper endpoint."""
    r = requests.get(f"{API_BASE}/opcache-status", headers=_host_headers())
    r.raise_for_status()
    return r.json()


def trigger_opcache_reset():
    """Trigger OPcache reset via test helper endpoint."""
    r = requests.post(f"{API_BASE}/opcache-reset", json={}, headers=_host_headers())
    r.raise_for_status()
    return r.json()

