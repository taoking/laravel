#!/usr/bin/env bash

set -euo pipefail

COMPOSE="${COMPOSE:-docker compose}"
BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
SKIP_UP="${SKIP_UP:-0}"
SKIP_HTTP="${SKIP_HTTP:-0}"

wait_for_http() {
  local url="$1"

  for _ in $(seq 1 30); do
    if curl -fsS "${url}" >/dev/null; then
      return 0
    fi
    sleep 2
  done

  curl -fsS "${url}" >/dev/null
}

wait_for_container_health() {
  local service="$1"
  local container_id
  local status

  container_id="$(${COMPOSE} ps -q "${service}")"
  if [ -z "${container_id}" ]; then
    echo "Container for service [${service}] was not found."
    return 1
  fi

  for _ in $(seq 1 30); do
    status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "${container_id}")"
    if [ "${status}" = "healthy" ] || [ "${status}" = "none" ]; then
      return 0
    fi
    sleep 2
  done

  docker inspect --format '{{json .State.Health}}' "${container_id}"
  return 1
}

if [ ! -f .env ]; then
  cp .env.example .env
fi

${COMPOSE} config >/tmp/laravel-compose-config.txt

if [ "${SKIP_UP}" != "1" ]; then
  ${COMPOSE} up -d --build mysql redis kafka app nginx queue scheduler
fi

${COMPOSE} exec -T app git config --global --add safe.directory /var/www/html
${COMPOSE} exec -T app composer install
${COMPOSE} exec -T app npm ci
${COMPOSE} exec -T app npm run build
${COMPOSE} exec -T app php artisan key:generate --force
${COMPOSE} exec -T app php artisan migrate --seed --force
${COMPOSE} exec -T app php artisan config:clear
${COMPOSE} exec -T app php artisan route:clear
${COMPOSE} exec -T app php artisan queue:restart
${COMPOSE} up -d --no-deps --force-recreate nginx

if command -v php >/dev/null 2>&1 && [ -d vendor ]; then
  KAFKA_DRIVER=docker php artisan kafka:topics --create || {
    echo "Host artisan Kafka check failed; falling back to Kafka container CLI."
    ${COMPOSE} exec -T kafka bash -lc '
      for topic in metrics.import.completed metrics.import.completed.dlq metrics.data.changed metrics.data.changed.dlq audit.events audit.events.dlq; do
        partitions=3
        case "$topic" in metrics.data.changed|metrics.data.changed.dlq) partitions=6 ;; esac
        kafka-topics.sh --bootstrap-server kafka:9092 --create --if-not-exists --topic "$topic" --partitions "$partitions" --replication-factor 1 >/dev/null
      done
      kafka-topics.sh --bootstrap-server kafka:9092 --list
    '
  }
else
  ${COMPOSE} exec -T kafka bash -lc '
    for topic in metrics.import.completed metrics.import.completed.dlq metrics.data.changed metrics.data.changed.dlq audit.events audit.events.dlq; do
      partitions=3
      case "$topic" in metrics.data.changed|metrics.data.changed.dlq) partitions=6 ;; esac
      kafka-topics.sh --bootstrap-server kafka:9092 --create --if-not-exists --topic "$topic" --partitions "$partitions" --replication-factor 1 >/dev/null
    done
    kafka-topics.sh --bootstrap-server kafka:9092 --list
  '
fi

if [ "${SKIP_HTTP}" != "1" ]; then
  wait_for_http "${BASE_URL}/up"
  wait_for_http "${BASE_URL}/login"
  wait_for_http "${BASE_URL}/docs/api"
  wait_for_http "${BASE_URL}/api/v1/health"
  wait_for_container_health app
  wait_for_container_health nginx
  wait_for_container_health mysql
  wait_for_container_health redis
fi

${COMPOSE} ps
