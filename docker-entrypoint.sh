#!/bin/bash

# Attendre que la base de données soit prête
echo "Waiting for database to be ready..."
while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --silent; do
    sleep 1
done

echo "Database is ready!"

# Vérifier si les tables existent
if ! mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1 FROM users LIMIT 1" >/dev/null 2>&1; then
    echo "Importing database schema..."
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < /var/www/html/database/schema.sql
fi

# Optimiser l'autoloader en production
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing autoloader for production..."
    composer dump-autoload --optimize --no-dev --classmap-authoritative
fi

# Démarrer PHP-FPM
echo "Starting PHP-FPM..."
exec "$@"
