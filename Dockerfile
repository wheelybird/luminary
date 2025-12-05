FROM php:8.4-apache

LABEL org.opencontainers.image.authors="wheelybird@wheelybird.com"
LABEL org.opencontainers.image.title="Luminary"
LABEL org.opencontainers.image.description="Web-based LDAP user management with self-service MFA"
LABEL org.opencontainers.image.source="https://github.com/wheelybird/luminary"

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        libldb-dev libldap2-dev libldap-common \
        libfreetype6-dev \
        libjpeg-dev \
        libpng-dev \
        cron && \
    rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype && \
    docker-php-ext-install -j$(nproc) gd && \
    libdir=$(find /usr -name "libldap.so*" -type f | head -1 | xargs dirname | sed 's|/usr/||') && \
    docker-php-ext-configure ldap --with-libdir=$libdir && \
    docker-php-ext-install -j$(nproc) ldap

ADD https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v7.0.0.tar.gz /tmp

RUN a2enmod rewrite ssl && a2dissite 000-default default-ssl

EXPOSE 80
EXPOSE 443

COPY www/ /opt/luminary
RUN ln -s /opt/luminary /opt/ldap-user-manager
RUN tar -xzf /tmp/v7.0.0.tar.gz -C /opt && mv /opt/PHPMailer-7.0.0 /opt/PHPMailer

COPY entrypoint /usr/local/bin/entrypoint
RUN chmod a+x /usr/local/bin/entrypoint && touch /etc/ldap/ldap.conf

CMD ["apache2-foreground"]
ENTRYPOINT ["/usr/local/bin/entrypoint"]
