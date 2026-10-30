# Pinned for CVE-2026-109618 / CVE-2026-109619 (fixed in PHP 8.4.27). Bump on the next PHP security release.
FROM php:8.4.27-apache

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

RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) gd && \
    libdir=$(find /usr -name "libldap.so*" -type f | head -1 | xargs dirname | sed 's|/usr/||') && \
    docker-php-ext-configure ldap --with-libdir=$libdir && \
    docker-php-ext-install -j$(nproc) ldap

ADD https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v7.0.0.tar.gz /tmp

RUN a2enmod rewrite ssl && a2dissite 000-default default-ssl

# Apply latest security patches and remove build-time dev packages
# Mark runtime libs as manual first so --auto-remove doesn't pull them out
RUN apt-get update && \
    apt-get upgrade -y && \
    apt-mark manual libldap-common libfreetype6 libjpeg62-turbo libpng16-16t64 && \
    apt-get purge -y --auto-remove libldb-dev libldap2-dev libfreetype6-dev libjpeg-dev libpng-dev linux-libc-dev && \
    rm -rf /var/lib/apt/lists/*

EXPOSE 80
EXPOSE 443

COPY www/ /opt/luminary
RUN ln -s /opt/luminary /opt/ldap-user-manager
RUN tar -xzf /tmp/v7.0.0.tar.gz -C /opt && mv /opt/PHPMailer-7.0.0 /opt/PHPMailer && rm /tmp/v7.0.0.tar.gz

COPY entrypoint /usr/local/bin/entrypoint
RUN chmod a+x /usr/local/bin/entrypoint && touch /etc/ldap/ldap.conf

CMD ["apache2-foreground"]
ENTRYPOINT ["/usr/local/bin/entrypoint"]
