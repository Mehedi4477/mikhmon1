FROM php:8.2-apache
COPY . /var/www/html/
RUN echo "Listen 80" >> /etc/apache2/ports.conf
EXPOSE 80
CMD ["sh", "-c", "php /var/www/html/index.php & apache2-foreground"]
