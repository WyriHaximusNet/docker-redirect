# docker redirect image 

[![Github Actions](https://github.com/WyriHaximusNet/docker-redirect/workflows/Continuous%20Integration/badge.svg)](https://github.com/wyrihaximusnet/docker-redirect/actions)
[![Docker hub](https://img.shields.io/badge/Docker%20Hub-00a5c9.svg?logo=docker&style=flat&color=00a5c9&labelColor=00a5c9&logoColor=white)](https://hub.docker.com/r/wyrihaximusnet/redirect/)
[![Docker hub](https://img.shields.io/docker/pulls/wyrihaximusnet/redirect.svg?color=00a5c9&labelColor=03566a)](https://hub.docker.com/r/wyrihaximusnet/redirect/)
[![Docker hub](https://img.shields.io/microbadger/image-size/wyrihaximusnet/redirect/random.svg?color=00a5c9&labelColor=03566a)](https://hub.docker.com/r/wyrihaximusnet/redirect/)
[![Buy Phil a tree](https://img.shields.io/badge/Buy%20Phil%20a%20tree-%F0%9F%8C%B3-lightgreen)](https://offset.earth/philsturgeon)

Docker image running an HTTP server that redirects all incoming requests based on it's given configuration.

## Why

To improve my personal knowledge of programming languages I'm aiming to create the same HTTP server that will redirect 
incoming requests according to the given config in a different language each month of 2020.

As I'm moving my personal projects onto kubernetes I needed a way to redirect `DOMAIN.TLD` to `www.DOMAIN.TLD`. Now 
kubernetes already has support for this through [nginx.ingress.kubernetes.io/from-to-www-redirect](https://kubernetes.github.io/ingress-nginx/user-guide/nginx-configuration/annotations/#redirect-fromto-www). 
But since this is such a small isolated feature it's perfect to try out new languages in and maybe go beyond this in 
some of them. After all this is a learning exercise for me. One with strict tests set up using [k6](https://k6.io/) to 
ensure the same behavior across languages.

## Provided tags

* `random` - Each time when the images are build a random image is selected and build.
* `reactphp` - Using [ReactPHP](https://reactphp.org/) (January 2020)
* `nodejs` - Using [NodeJS](https://nodejs.org/en/) (February 2020)

## Configuration

These images comes with 3 mandatory configuration options:
* `defaultFallbackTarget` - Requests not matching other rules will be redirected here
* `buildin.wwwToNonWww` - Redirects `www.DOMAIN.TLD` to `DOMAIN.TLD`; mutually exclusive with `buildin.nonWwwToWww` 
* `buildin.nonWwwToWww` - Redirects `DOMAIN.TLD` to `www.DOMAIN.TLD`; mutually exclusive with `buildin.wwwToNonWww`

There are also two optional configuration options for custom hostname based redirection, and HTTPS enforcement:
* `hosts` - from -> to based key value mapping
* `enforceHttps` - boolean for enforcing redirecting to HTTPS or not, defaults to false 

Full configuration example:

```yaml
defaultFallbackTarget: https://blog.wyrihaximus.net/
enforceHttps: true
buildin:
  wwwToNonWww: false
  nonWwwToWww: true
hosts:
  ceesjankiewiet.nl: wyrihaximus.net
  wyrimaps.com: wyrimaps.net
```

Keep in mind that the configuration options are executed in the following order:
1. `hosts`
2. `buildin.wwwToNonWww` or `buildin.nonWwwToWww`
3. `defaultFallbackTarget`

## Usage

The image can be start from the command line with the following command:

```bash
docker run -d --rm -v /path/to/config.yaml:/etc/redirect/config.yaml wyrihaximusnet/redirect:random
```

Once started the HTTP server is available at port `7132` for requests.

## Ports

These docker images expose two ports:

* `7132` - The world facing HTTP server doing the redirects.
* `7133` - Internal metrics server.

Both servers don't check for routes routes and either serve what they are build to serve.

## Metrics

These images export the following metric in [`Prometheus`](https://prometheus.io/) format:

* `http_requests_total` - The total amount of requests made, this metric comes with the following tags: `fromHost` (the host the request is made to), `method` (the HTTP method used to make the request), and `toHost` (the host the client is redirected to).

Different images may export different additional metrics but the one above and it's tags are guaranteed to be in all images.

## License

You're free to use this docker image as it's provided under the MIT license, but if it makes it to your production 
environment I would highly appreciate you buying the world a tree. It’s now common knowledge that one of the best tools 
to tackle the climate crisis and keep our temperatures from rising above 1.5C is to 
<a href="https://www.bbc.co.uk/news/science-environment-48870920">plant trees</a>. If you contribute to my forest 
you’ll be creating employment for local families and restoring wildlife habitats. As I don't have my own offset earth 
forrest you can buy trees at for Phil's forest here [offset.earth/philsturgeon](https://offset.earth/philsturgeon).
