FROM ubuntu:18.10
COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV DEBIAN_FRONTEND noninteractive

VOLUME ["/app"]
WORKDIR /app

RUN apt-get update \
&& apt-get install -y curl software-properties-common \
&& add-apt-repository ppa:ondrej/php \
&& apt-get update \
&& apt-get upgrade -y \
&& apt-get install -y \
    git \
    php7.2 \
    php7.2-cli \
    php7.2-curl \
    php7.2-json \
    php7.2-zip

ENTRYPOINT while true; do sleep 30; done
