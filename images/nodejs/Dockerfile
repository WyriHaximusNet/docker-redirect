FROM node:22-alpine3.20

RUN set -x \
    && addgroup -g 666 app \
    && adduser -u 666 -D -G app app

EXPOSE 7132
EXPOSE 7133

RUN mkdir -p /opt/app
WORKDIR /opt/app

COPY ./ /opt/app/

USER root
RUN chown app:app /opt/app/ -Rfv
USER app

RUN npm install

ENTRYPOINT ["node", "/opt/app/app.js"]
