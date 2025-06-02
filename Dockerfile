# 1. Use an official PHP image with Apache.
# You can choose a specific PHP version, e.g., php:8.2-apache, php:8.1-apache, php:8.0-apache
# Using a more generic tag like php:apache will get the latest stable 8.x version.
FROM php:8.2-apache

# 2. Install necessary PHP extensions for MySQL/MariaDB.
# mysqli is used in your provided code. pdo_mysql is also very common.
# docker-php-ext-install is a helper script in the official PHP images.
RUN docker-php-ext-install mysqli pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# 3. Enable Apache's mod_rewrite for things like .htaccess URL rewriting (common in many PHP apps/frameworks).
RUN a2enmod rewrite

# 4. Set the working directory in the container.
# Apache's default document root is /var/www/html.
WORKDIR /var/www/html

# 5. Copy your application files from your project's current directory (.)
# into the working directory inside the container (/var/www/html).
# Ensure this Dockerfile is in the root of your PHP application,
# or adjust the source path accordingly.
COPY . /var/www/html/

# 6. (Optional) If you have specific file ownership/permission needs after copying,
# you can add them here. For many simple apps, Apache (www-data user)
# will have sufficient read access.
# For example, if you have an 'uploads' directory that Apache needs to write to:
# RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads && chmod -R 775 /var/www/html/uploads

# Port 80 is exposed by default by the base php:apache image.
# If your application needs to listen on a different port internally,
# you would EXPOSE it here, but for standard web traffic, 80 is correct.
# EXPOSE 80

# The base php:apache image already has a CMD that starts Apache.
# So, you usually don't need to add your own CMD or ENTRYPOINT unless
# you have specific startup scripts.

# End of Dockerfile