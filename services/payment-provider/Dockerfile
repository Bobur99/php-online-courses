FROM php:8.5-cli-alpine

WORKDIR /var/www

COPY public ./public
COPY storage ./storage

RUN chmod -R 777 storage

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]