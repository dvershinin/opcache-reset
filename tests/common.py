"""Shared constants and helpers for the OPcache Reset test suite."""

import os
import time
import requests
from urllib.parse import urlparse

WP_URL = os.environ.get("WP_URL", "http://localhost:8080")
WP_BACKEND_URL = os.environ.get("WP_BACKEND_URL", WP_URL)
WP_NO_SHELL_URL = os.environ.get("WP_NO_SHELL_URL")
API_BASE = f"{WP_BACKEND_URL}/wp-json/test/v1"
DUMMY_API = f"{WP_BACKEND_URL}/wp-json/dummy/v1"  # Dummy plugin's REST API
_parsed = urlparse(WP_URL)
# When running in Docker, use localhost:8080 as Host header for WordPress
HOST_HEADER_VALUE = "localhost:8080" if _parsed.hostname == "wordpress" else _parsed.netloc


def _host_headers():
    return {"Host": HOST_HEADER_VALUE}
