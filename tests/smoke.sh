#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost:8080}"
cookie_jar="$(mktemp)"
response_headers="$(mktemp)"
trap 'rm -f "$cookie_jar" "$response_headers"' EXIT

csrf_from() {
  sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' | head -n1
}

assert_status() {
  local expected="$1"
  local method="$2"
  local url="$3"
  shift 3
  local actual
  actual="$(curl -sS -o /dev/null -w '%{http_code}' -X "$method" "$@" "$url")"
  if [[ "$actual" != "$expected" ]]; then
    echo "ERRO - $method $url retornou HTTP $actual; esperado: $expected" >&2
    exit 1
  fi
}

assert_location() {
  local expected="$1"
  local actual
  actual="$(sed -n 's/^[Ll]ocation: *\([^[:space:]]*\).*/\1/p' "$response_headers" | tail -n1 | tr -d '\r')"
  if [[ "$actual" != "$expected" ]]; then
    echo "ERRO - redirecionamento recebido: ${actual:-ausente}; esperado: $expected" >&2
    sed -n '1,20p' "$response_headers" >&2
    exit 1
  fi
}

assert_status "200" GET "$base_url/health"
curl -sS -D "$response_headers" -o /dev/null "$base_url/admin"
assert_location "/entrar"

register_html="$(curl -sS -c "$cookie_jar" "$base_url/cadastro")"
csrf="$(printf '%s' "$register_html" | csrf_from)"
test -n "$csrf"
curl -sS -b "$cookie_jar" -c "$cookie_jar" -D "$response_headers" -o /dev/null \
  --data-urlencode "_csrf=$csrf" --data-urlencode 'name=Proprietario CI' \
  --data-urlencode 'phone=11999999999' --data-urlencode 'company=Loja CI' \
  --data-urlencode 'document=529.982.247-25' --data-urlencode 'business_type=Alimentação' \
  --data-urlencode 'food_preset=pizzaria-italiana' \
  --data-urlencode 'city=Sao Paulo' --data-urlencode 'email=owner-ci@example.test' \
  --data-urlencode 'password=CI-password-123!' --data-urlencode 'password_confirm=CI-password-123!' \
  --data-urlencode 'terms=1' "$base_url/cadastro"
assert_location "/admin"

session_cookie="$(awk '$6 == "mjdev_session" { print $4 }' "$cookie_jar" | tail -n1)"
if [[ -z "$session_cookie" ]]; then
  echo 'ERRO - o cadastro não gravou o cookie de sessão.' >&2
  exit 1
fi
if [[ "$session_cookie" == "TRUE" ]]; then
  echo 'ERRO - o cookie de sessão permaneceu Secure no ambiente HTTP do CI.' >&2
  exit 1
fi
admin_html="$(curl -sS -b "$cookie_jar" -c "$cookie_jar" -D "$response_headers" "$base_url/admin")"
admin_location="$(sed -n 's/^[Ll]ocation: *\([^[:space:]]*\).*/\1/p' "$response_headers" | tail -n1 | tr -d '\r')"
if [[ "$admin_location" == "/entrar" ]]; then
  echo 'ERRO - o servidor recusou a sessão persistida e redirecionou para /entrar.' >&2
  exit 1
fi
if [[ -n "$admin_location" ]]; then
  echo "ERRO - /admin redirecionou inesperadamente para $admin_location." >&2
  exit 1
fi
printf '%s' "$admin_html" | grep -q 'PAINEL DE GESTÃO'
printf '%s' "$admin_html" | grep -q 'Seu primeiro produto está pronto para personalizar'
printf '%s' "$admin_html" | grep -q 'Pizza Margherita'
curl -sS -b "$cookie_jar" "$base_url/admin?section=products" | grep -q 'Produto inativo'
csrf="$(printf '%s' "$admin_html" | csrf_from)"
assert_status "403" POST "$base_url/admin/category/save" -b "$cookie_jar" --data '_csrf=invalid'

curl -sS -b "$cookie_jar" -D "$response_headers" -o /dev/null \
  --data-urlencode "_csrf=$csrf" --data-urlencode 'name=Categoria Persistente' \
  "$base_url/admin/category/save"
assert_location "/admin?section=categories"
curl -sS -b "$cookie_jar" -D "$response_headers" -o /dev/null --data-urlencode "_csrf=$csrf" "$base_url/sair"
assert_location "/entrar"
curl -sS -b "$cookie_jar" -D "$response_headers" -o /dev/null "$base_url/admin"
assert_location "/entrar"

docker compose restart app >/dev/null
for _ in $(seq 1 30); do curl -fsS "$base_url/health" >/dev/null && break || sleep 1; done
login_html="$(curl -sS -c "$cookie_jar" "$base_url/entrar")"
csrf="$(printf '%s' "$login_html" | csrf_from)"
curl -sS -b "$cookie_jar" -c "$cookie_jar" -D "$response_headers" -o /dev/null \
  --data-urlencode "_csrf=$csrf" --data-urlencode 'email=owner-ci@example.test' \
  --data-urlencode 'password=CI-password-123!' "$base_url/entrar"
assert_location "/admin"
curl -sS -b "$cookie_jar" "$base_url/admin?section=categories" | grep -q 'Categoria Persistente'

echo 'OK - cadastro, autenticação, CSRF, logout, proteção e persistência'

