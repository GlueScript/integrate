FROM ubuntu:latest

MAINTAINER Tim Rodger

# Expose the port
EXPOSE 80

# Install dependencies
RUN apt-get update -qq && apt-get -y install \
    curl \
    php-apc \
    php5-cli \
    php5-common

# Make the directories
RUN mkdir /home/app 

# Install composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Move files into place
COPY src/ /home/app/src
COPY composer.json /home/app/
COPY composer.lock /home/app/

# Install dependencies
WORKDIR /home/app
RUN composer install --prefer-dist
