import { check, group } from "k6";
import http from "k6/http";
import { Rate } from "k6/metrics";

export let errorRate = new Rate("errors");

export let options = {
    hosts: {
        "wyrihaximus.net": `${__ENV.IMAGE_IP}`,
        "www.wyrihaximus.net": `${__ENV.IMAGE_IP}`,
        "bbc.co.uk": `${__ENV.IMAGE_IP}`,
        "www.bbc.co.uk": `${__ENV.IMAGE_IP}`,
    },
    maxRedirects: 0,
    thresholds: {
        "errors": ["rate==0"],
    }
};

export default function() {
    group("wyrihaximus.net", function() {
        let res = http.get("http://wyrihaximus.net:7132/");
        let result = check(res, {
            "is status 301": (r) => r.status === 301,
            "location header is set": (r) => r.headers.Location !== undefined,
            "location header is set with correct value": (r) => r.headers.Location === "https://www.wyrihaximus.net:7132/",
        });
        errorRate.add(!result);
    });
    group("bbc.co.uk", function() {
        let res = http.get("http://bbc.co.uk:7132/");
        let result = check(res, {
            "is status 301": (r) => r.status === 301,
            "location header is set": (r) => r.headers.Location !== undefined,
            "location header is set with correct value": (r) => r.headers.Location === "https://www.bbc.co.uk:7132/",
        });
        errorRate.add(!result);
    });
    group("localhost", function() {
        let res = http.get(`http://${__ENV.IMAGE_IP}:7132/`);
        let result = check(res, {
            "is status 301": (r) => r.status === 301,
            "location header is set": (r) => r.headers.Location !== undefined,
            "location header is set with correct value": (r) => r.headers.Location === "https://blog.wyrihaximus.net/",
        });
        errorRate.add(!result);
    });
};
