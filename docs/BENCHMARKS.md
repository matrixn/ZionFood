# Local verification measurements — 0.0.1

Client date: October 25, 2026. These are development benchmarks, not production capacity certification.

## Reproducible conditions

100 isolated organizations/restaurants/locations in `zionfood_load_test`, each with one menu/category/product, current trial entitlement and a four-hour read-only kitchen token. This is a deliberately small menu fixture, distinct from the three richer demo restaurants. MariaDB 10.11 and Redis 7 run in the local project Compose stack.

The successful runs use PHP 8.4.23 in WSL Ubuntu 22.04, a private temporary copy of the same application source on the Linux filesystem, four CLI-server workers plus their parent, OPcache enabled, timestamp validation disabled, and Xdebug disabled. The test server uses separate Redis DB/cache DB/prefix, database queues, disabled outbound broadcasting and a per-IP checkout limit of 20,000 to accommodate a single load-generator IP. Production defaults remain ten per minute per IP. This is not Nginx/PHP-FPM, Forge or a remote production network.

k6 version: 2.3.0. Image digest: `grafana/k6@sha256:3e4186aea62e11a45560bfc51e19eeafe82fc07ce069c78aff44b7ff893d9a5e`.

## Results

| Scenario | Menu p95 | Checkout p95 | Correctness | Outcome |
| --- | --- | --- | --- | --- |
| Initial Windows-mounted source, development PHP runtime, five order VUs, two kitchen sessions | 2.20 s | 1.65 s | 313/313 checks, no duplicate orders | Latency targets failed |
| Linux runtime snapshot, OPcache, warm menu cache, 100 orders across five VUs, two kitchen sessions for 20 s | 46.72 ms | 153.00 ms | 339/339 checks; 100 created orders and 100 matching idempotent replays | All configured thresholds passed |
| Same Linux runtime, fixed five orders/second for 20 s, ten kitchen polling sessions | 34.78 ms | 102.84 ms | 493/493 checks; 100 created orders and 100 matching replays; zero failed HTTP requests | All configured thresholds passed |

The final arrival-rate run made 593 HTTP requests including 100 menu warmup requests and 193 kitchen reconciliation polls. Total elapsed time including setup was about 25 seconds. No duplicate orders were observed. Raw metric summaries and conditions are in `docs/benchmarks/`. An intermediate warmup attempt on the Windows-mounted source exceeded the 60-second setup timeout and did not execute the load scenario; its zero-sample metrics are not treated as a result.

The initial and final runs changed both runtime conditions and menu caching. They are not an isolated A/B experiment attributing gains to one change.

## Commands

After provisioning and migrating the dedicated load database:

```sh
DB_DATABASE=zionfood_load_test QUEUE_CONNECTION=database php artisan zionfood:load-fixtures --restaurants=100
bash scripts/benchmark-server.sh
```

The server prints the private temporary runtime directory and listens on loopback 8004. In another WSL terminal run k6 with the pinned image, host networking, the repository mounted read-only at `/work`, and a writable results directory. For the arrival scenario:

```sh
k6 run -e BASE_URL=http://127.0.0.1:8004 -e WARM_CACHE=1 \
  -e ORDER_RATE=5 -e ORDER_VUS=5 -e KITCHEN_SESSIONS=10 \
  -e DURATION=20s tests/load/ordering.js
```

Set BASE_URL and fixture path appropriately for another staging deployment; never reuse fixture tokens in production. Stop the benchmark server after use and remove only its printed `/tmp/zionfood-benchmark.*` directory after verifying the path. It contains a private environment copy. Keep application and benchmark queues/broadcasts isolated.

## What remains unmeasured

Real Reverb event receipt was verified in Playwright, but WebSocket latency distributions and the proposed two-second target have not been benchmarked. The k6 kitchen scenario measures HTTP state reconciliation. There is no sustained soak, 100 simultaneous kitchen clients, large menus, multi-server Reverb, production TLS/network, resource saturation study, disk-full test or end-to-end crash/recovery benchmark. Local threshold success does not establish production capacity for 100 busy restaurants.
