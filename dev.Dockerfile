FROM hyperf/hyperf:8.2-alpine-v3.18-swoole-dev

ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

WORKDIR /opt/www

RUN apk add --no-cache git curl zip unzip

EXPOSE 9501

CMD ["php", "bin/hyperf.php", "start"]
