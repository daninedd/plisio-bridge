FROM hyperf/hyperf:8.2-alpine-v3.18-swoole

ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

WORKDIR /opt/www

RUN apk add --no-cache git curl zip unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9501

ENTRYPOINT ["docker-entrypoint.sh"]
