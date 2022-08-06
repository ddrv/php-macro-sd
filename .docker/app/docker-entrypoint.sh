#!/bin/sh

set -e

echo "run composer install -q ..."
composer install
echo "it is ok"

# create roadrunner config
if [ -f ./rr-worker.php ]; then
  echo "# generated automatically. do not edit" > ./.rr.yaml
  echo "" >> ./.rr.yaml
  echo "http:" >> ./.rr.yaml
  echo "  address: \"0.0.0.0:80\"" >> ./.rr.yaml
  echo "  access_logs: false" >> ./.rr.yaml
  echo "  middleware:" >> ./.rr.yaml
  echo "  - headers" >> ./.rr.yaml

  if [ -d ./public ]; then
    echo "  - static" >> ./.rr.yaml
    echo "  static:" >> ./.rr.yaml
    echo "    dir: ./public" >> ./.rr.yaml
  fi

  echo "pool:" >> ./.rr.yaml
  echo "  num_workers: 32" >> ./.rr.yaml
  echo "  max_jobs: 1000" >> ./.rr.yaml
  if [ ${APP_DEBUG} ]; then
    echo "reload:" >> ./.rr.yaml
    echo "  interval: 1s" >> ./.rr.yaml
    echo "  patterns: [ \".php\" ]" >> ./.rr.yaml
    echo "  services:" >> ./.rr.yaml
    echo "    http:" >> ./.rr.yaml
    echo "      recursive: true" >> ./.rr.yaml
    echo "      ignore: [ \"vendor\" ]" >> ./.rr.yaml
    echo "      patterns: [ \".php\" ]" >> ./.rr.yaml
    echo "      dirs: [ \".\" ]" >> ./.rr.yaml
  fi

  echo "logs:" >> ./.rr.yaml
  echo "  level: error" >> ./.rr.yaml
  echo "server:" >> ./.rr.yaml
  echo "  command: \"php rr-worker.php\"" >> ./.rr.yaml
  echo "" >> ./.rr.yaml
fi

# create supervisor config
echo "[supervisord]" > /etc/supervisord.conf
echo "nodaemon=true" >> /etc/supervisord.conf
echo "logfile=/var/log/supervisor/supervisord.log" >> /etc/supervisord.conf
echo "pidfile=/var/run/supervisord.pid" >> /etc/supervisord.conf
echo "" >> /etc/supervisord.conf
echo "[program:roadrunner]" >> /etc/supervisord.conf
echo "command=/usr/local/bin/roadrunner serve -c /opt/app/.rr.yaml" >> /etc/supervisord.conf
echo "" >> /etc/supervisord.conf

/usr/bin/supervisord -c /etc/supervisord.conf