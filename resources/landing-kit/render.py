"""Renders the demo event's real print sheets to public/landing/kit/*.png for the landing page.
Needs a dev server with GATEZO_DEMO=true and the demo seeded. Run it with the public
APP_URL so the printed links and QRs show the real domain:
    APP_URL=https://gatezo.in APP_FORCE_URL=true php artisan serve --port=8001 &
    BASE=http://127.0.0.1:8001
    python3 resources/landing-kit/render.py
"""
import json, time, base64, subprocess, urllib.request, websocket
import os
BASE = os.environ.get('BASE', 'http://127.0.0.1:8000')
chrome = subprocess.Popen(['google-chrome', '--headless=new', '--no-sandbox', '--disable-gpu', '--hide-scrollbars',
                           '--window-size=1000,1400', '--remote-debugging-port=9335', '--remote-allow-origins=*',
                           '--user-data-dir=/tmp/cdp-kit', 'about:blank'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
for _ in range(50):
    try: targets = json.load(urllib.request.urlopen('http://127.0.0.1:9335/json')); break
    except Exception: time.sleep(.2)
ws = websocket.create_connection([t for t in targets if t['type'] == 'page'][0]['webSocketDebuggerUrl'])
mid = 0
def call(m, **p):
    global mid; mid += 1; ws.send(json.dumps({'id': mid, 'method': m, 'params': p}))
    while True:
        r = json.loads(ws.recv())
        if r.get('id') == mid: return r.get('result', {})
def js(e): return call('Runtime.evaluate', expression=e, returnByValue=True)['result'].get('value')
call('Page.enable'); call('Emulation.setDeviceMetricsOverride', width=1000, height=1400, deviceScaleFactor=2, mobile=False)
call('Page.navigate', url=BASE + '/demo'); time.sleep(4)          # signs in as the demo organizer
call('Page.navigate', url=BASE + '/print/sharad-utsav/kit'); time.sleep(3)
js("document.querySelector('.toolbar')?.remove(); document.body.style.background='#fff'")
# With APP_FORCE_URL the logo points at the public domain; pull it from the local server instead.
js(f"document.querySelectorAll('img').forEach(i => i.src = i.src.replace(/^https?:\\/\\/[^/]+/, '{BASE}'))"); time.sleep(1.5)
# poster = sheet 0, first gate sign = sheet 1, first stall grid = after the gates, exit cards = last
n = js("document.querySelectorAll('.sheet').length"); gates = js("document.querySelectorAll('.sheet .stripe.volunteer').length / 2")
picks = {'poster': 0, 'gate': 1, 'stalls': 1 + int(gates), 'exit': n - 1}
for name, idx in picks.items():
    x, y, w, h = js(f"(() => {{ const b = document.querySelectorAll('.sheet')[{idx}].getBoundingClientRect(); return [b.left + scrollX, b.top + scrollY, b.width, b.height]; }})()")
    data = call('Page.captureScreenshot', format='png', captureBeyondViewport=True, clip={'x': x, 'y': y, 'width': w, 'height': h, 'scale': 1})['data']
    open(f'public/landing/kit/{name}.png', 'wb').write(base64.b64decode(data)); print('saved', name, int(w), int(h))
chrome.terminate()
