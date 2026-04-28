# check directory
pwd

# composer setup
composer8.4 install --no-dev



# artisan starter
php8.4 artisan storage:link

# migrattion
php8.4 artisan migrate --force

# clear chace
php8.4 artisan config:clear
php8.4 artisan cache:clear