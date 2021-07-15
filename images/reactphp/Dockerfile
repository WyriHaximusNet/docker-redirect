FROM wyrihaximusnet/php:8.0-nts-alpine-slim-dev AS install-dependencies

WORKDIR /opt/app
USER root
RUN mkdir /opt/app/vendor && chown app:app /opt/app -Rfv
USER app

COPY ./composer.* /opt/app/
RUN composer install --ansi --no-interaction --prefer-dist --no-dev -o

FROM wyrihaximusnet/php:8.0-nts-alpine-slim-dev

EXPOSE 7132
EXPOSE 7133
WORKDIR /opt/app

COPY ./ /opt/app/
COPY --from=install-dependencies /opt/app/vendor/ /opt/app/vendor/

USER root
RUN chown app:app /opt/app/ -Rfv
USER app

ENTRYPOINT ["php", "/opt/app/app.php"]
