import { check, group } from "k6";
import http from "k6/http";
import { Rate } from "k6/metrics";

export let errorRate = new Rate("errors");

export let options = {
    maxRedirects: 0,
    thresholds: {
        "errors": ["rate==0"],
    }
};

export default function() {
    group("metrics", function() {
        let res = http.get(`http://${__ENV.IMAGE_IP}:7133/`);
        let result = check(res, {
            "is status 200": (r) => r.status === 200,
            "location header is not set": (r) => r.headers.Location === undefined,
            "body contains from www.wyrihaximus.net => GET => wyrimaps.net": (r) => r.body.includes('http_requests_total{') && r.body.includes('fromHost="www.wyrihaximus.net"') && r.body.includes('method="GET"') && r.body.includes('toHost="wyrihaximus.net"'),
            "body contains from www.bbc.co.uk => GET => bbc.co.uk": (r) => r.body.includes('http_requests_total{') && r.body.includes('fromHost="www.bbc.co.uk"') && r.body.includes('method="GET"') && r.body.includes('toHost="bbc.co.uk"'),
            "body contains from DockerImageIp => GET => blog.wyrihaximus.net": (r) => r.body.includes('http_requests_total{') && r.body.includes('fromHost="' + __ENV.IMAGE_IP + '"') && r.body.includes('method="GET"') && r.body.includes('toHost="blog.wyrihaximus.net"'),
        });
        errorRate.add(!result);
    });
};
