FROM golang:1.14-alpine3.11 AS build

RUN mkdir /opt/app
WORKDIR /opt/app

COPY app.go /opt/app

# hadolint ignore=DL3018,DL3019
RUN apk add git gcc musl-dev godep

ENV GOBIN /go/bin
ENV GO111MODULE on

WORKDIR /go

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -a -ldflags '-w' -o /app /opt/app/app.go

### App
FROM scratch AS app

COPY --from=build /app /app

ENTRYPOINT ["/app"]
