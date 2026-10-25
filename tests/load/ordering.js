import http from 'k6/http';
import execution from 'k6/execution';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Trend, Counter } from 'k6/metrics';
const fixtures = new SharedArray('restaurants', () => JSON.parse(open(__ENV.FIXTURES || '../../storage/app/private/load-fixtures.json')));
const base = __ENV.BASE_URL || 'http://127.0.0.1:8002';
const restaurants = Number(__ENV.RESTAURANTS || 100);
if (fixtures.length < restaurants) throw new Error(`Need ${restaurants} isolated restaurants; found ${fixtures.length}.`);
const menuLatency = new Trend('menu_latency', true); const checkoutLatency = new Trend('checkout_latency', true); const duplicates = new Counter('duplicate_orders');
export const options = {
    scenarios: {
        ordering: __ENV.ORDER_RATE ? { executor: 'constant-arrival-rate', rate: Number(__ENV.ORDER_RATE), timeUnit: '1s', duration: __ENV.DURATION || '20s', preAllocatedVUs: Number(__ENV.ORDER_VUS || 5), maxVUs: Number(__ENV.MAX_ORDER_VUS || 20), exec: 'order' } : { executor: 'shared-iterations', vus: Number(__ENV.ORDER_VUS || 5), iterations: Number(__ENV.ORDER_ITERATIONS || 100), maxDuration: '3m', exec: 'order' },
        kitchens: { executor: 'constant-vus', vus: Number(__ENV.KITCHEN_SESSIONS || 2), duration: __ENV.DURATION || '20s', exec: 'kitchen' },
    },
    thresholds: { checks: ['rate==1'], duplicate_orders: ['count==0'], menu_latency: ['p(95)<400'], checkout_latency: ['p(95)<800'] },
};
export function setup() { if (__ENV.WARM_CACHE === '1') for (const fixture of fixtures.slice(0, restaurants)) http.get(`${base}/api/v1/public/locations/${fixture.location_id}/menu`, { tags: { name: 'warm_menu' } }); }
export function order() {
    const fixture = fixtures[execution.scenario.iterationInTest % restaurants];
    const menu = http.get(`${base}/api/v1/public/locations/${fixture.location_id}/menu`, { tags: { name: 'public_menu' } }); menuLatency.add(menu.timings.duration); check(menu, { 'menu available': (r) => r.status === 200 });
    const payload = JSON.stringify({ customer_name: 'k6 Guest', customer_phone: '0700000000', fulfillment: 'pickup', items: [{ product_id: fixture.product_id, quantity: 1 }] });
    const params = { headers: { 'Content-Type': 'application/json', 'Idempotency-Key': `k6-${__ENV.RUN_ID || 'local'}-${__VU}-${__ITER}-${Date.now()}` }, tags: { name: 'checkout' } };
    const first = http.post(`${base}/api/v1/public/locations/${fixture.location_id}/checkout`, payload, params); checkoutLatency.add(first.timings.duration);
    const second = http.post(`${base}/api/v1/public/locations/${fixture.location_id}/checkout`, payload, params);
    const correct = first.status === 201 && second.status === 200 && first.json('order_id') === second.json('order_id');
    check(first, { 'checkout created': (r) => r.status === 201, 'idempotency replay matches': () => correct }); duplicates.add(correct ? 0 : 1);
}
export function kitchen() {
    const fixture = fixtures[(__VU - 1) % restaurants];
    const response = http.get(`${base}/api/v1/locations/${fixture.location_id}/orders?active=1`, { headers: { Authorization: `Bearer ${fixture.kitchen_token}` }, tags: { name: 'kitchen_sync' } });
    check(response, { 'kitchen sync available': (r) => r.status === 200 }); sleep(1);
}
