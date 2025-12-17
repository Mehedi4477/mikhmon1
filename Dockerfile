FROM php:8.2-apache
COPY . /var/www/html/
# পোর্ট কনফিগারেশন স্বয়ংক্রিয়ভাবে হ্যান্ডেল করার জন্য নিচের কমান্ডটি ব্যবহার করুন
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
CMD ["sh", "-c", "php /var/www/html/index.php & apache2-foreground"]
