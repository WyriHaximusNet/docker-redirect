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
* `reactphp` - Using [ReactPHP](https://reactphp.org/)

## Configuration

These images comes with 3 mandatory configuration options:
* `defaultFallbackTarget` - Requests not matching other rules will be redirected here
* `buildin.wwwToNonWww` - Redirects `www.DOMAIN.TLD` to `DOMAIN.TLD`; mutually exclusive with `buildin.nonWwwToWww` 
* `buildin.nonWwwToWww` - Redirects `DOMAIN.TLD` to `www.DOMAIN.TLD`; mutually exclusive with `buildin.wwwToNonWww`

There is also one optional configuration option for custom hostname based redirection:
* `hosts` - from -> to based key value mapping

Full configuration example:

```yaml
defaultFallbackTarget: https://blog.wyrihaximus.net/
buildin:
  wwwToNonWww: false
  nonWwwToWww: true
hosts:
  ceesjankiewiet.nl: wyrihaximus.net
  wyrimaps.com: wyrimaps.net
```

## Usage

```bash
docker run -d --rm -v /path/to/config.yaml:/etc/redirect/config.yaml wyrihaximusnet/redirect:random
```

## License

You're free to use this docker image as it's provided under the MIT license, but if it makes it to your production 
environment I would highly appreciate you buying the world a tree. It’s now common knowledge that one of the best tools 
to tackle the climate crisis and keep our temperatures from rising above 1.5C is to 
<a href="https://www.bbc.co.uk/news/science-environment-48870920">plant trees</a>. If you contribute to my forest 
you’ll be creating employment for local families and restoring wildlife habitats. As I don't have my own offset earth 
forrest you can buy trees at for Phil's forest here [offset.earth/philsturgeon](https://offset.earth/philsturgeon).
