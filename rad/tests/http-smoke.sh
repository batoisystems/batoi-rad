#!/usr/bin/env bash
set -euo pipefail

base_url="${RAD_TEST_BASE_URL:-http://127.0.0.1:8080}"
username="${RAD_TEST_ADMIN_USERNAME:-admin}"
password="${RAD_TEST_ADMIN_PASSWORD:?RAD_TEST_ADMIN_PASSWORD is required}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

curl -fsSL -c "$work_dir/cookies" "$base_url/rad-admin" -o "$work_dir/login.html"
grep -q 'Sign in to RAD Admin' "$work_dir/login.html"
csrf="$(sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' "$work_dir/login.html" | head -n 1)"
test -n "$csrf"

curl -fsSL -b "$work_dir/cookies" -c "$work_dir/cookies" \
    --data-urlencode "csrf_token=$csrf" \
    --data-urlencode "redirect_url_post_login=$base_url/rad-admin/home/view" \
    --data-urlencode "s_username=$username" \
    --data-urlencode "s_password=$password" \
    "$base_url/login/localsession" -o "$work_dir/home.html"
grep -q '<title>RAD Admin' "$work_dir/home.html"
grep -q 'name="rad-csrf"' "$work_dir/home.html"

logout_get_status="$(curl -sS -o "$work_dir/logout-get.html" -w '%{http_code}' -b "$work_dir/cookies" "$base_url/login/logout")"
test "$logout_get_status" = '419'

csrf="$(sed -n 's/.*name="rad-csrf" content="\([^"]*\)".*/\1/p' "$work_dir/home.html" | head -n 1)"
test -n "$csrf"
curl -fsSL -b "$work_dir/cookies" -c "$work_dir/cookies" \
    --data-urlencode "csrf_token=$csrf" \
    "$base_url/login/logout" -o "$work_dir/logged-out.html"
grep -Eq 'Sign in|Login' "$work_dir/logged-out.html"

echo 'HTTP authentication smoke test passed.'
