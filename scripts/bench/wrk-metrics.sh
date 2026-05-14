#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
COOKIE_HEADER="${COOKIE_HEADER:-}"

wrk -t4 -c50 -d30s \
  -H "Accept: application/json" \
  ${COOKIE_HEADER:+-H "Cookie: ${COOKIE_HEADER}"} \
  "${BASE_URL}/api/v1/metrics?per_page=20&sort=id&direction=desc"
