FROM php:8.2-apache

# Ekstensi yang dibutuhkan (mysqli, pdo)
RUN docker-php-ext-install mysqli pdo pdo_mysql && \
    a2enmod rewrite headers expires && \
    mkdir -p /var/www/html/uploads/bel && \
    chmod -R 777 /var/www/html/uploads

# Konfigurasi PHP untuk upload audio (max 20MB)
RUN printf 'upload_max_filesize = 20M\npost_max_size = 21M\nmax_execution_time = 60\nmemory_limit = 256M\n' > /usr/local/etc/php/conf.d/upload.ini

# Python3 + edge-tts untuk generate MP3 pengumuman (saat admin simpan jadwal berketerangan)
RUN apt-get update && \
    apt-get install -y --no-install-recommends python3 python3-pip ca-certificates && \
    pip3 install --break-system-packages --no-cache-dir edge-tts && \
    rm -rf /var/lib/apt/lists/*

EXPOSE 80