"""Snapshot public WooCommerce catalog and all referenced original media; resumable."""
import concurrent.futures, hashlib, html, json, pathlib, re, time, urllib.request, urllib.parse, xml.etree.ElementTree as ET

ROOT = pathlib.Path('C:/xampp/boutique-migration')
ROOT.mkdir(exist_ok=True)
BASE = 'https://lmxboutique.nl'

def fetch(url):
    for attempt in range(5):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent':'BoutiqueCatalogMigration/1.0'}), timeout=90) as r:
                return r.read(), dict(r.headers)
        except Exception:
            if attempt == 4: raise
            time.sleep(2 * (attempt+1))

def collection(name, endpoint):
    path = ROOT / (name+'.json')
    if path.exists(): return json.loads(path.read_text(encoding='utf-8'))
    data, headers = fetch(BASE+endpoint+'&page=1')
    rows = json.loads(data)
    pages = int(next((v for k,v in headers.items() if k.lower()=='x-wp-totalpages'),1))
    total = int(next((v for k,v in headers.items() if k.lower()=='x-wp-total'),len(rows)))
    for page in range(2,pages+1):
        rows.extend(json.loads(fetch(BASE+endpoint+'&page='+str(page))[0]))
        print(name, page, '/', pages, flush=True)
    assert len(rows)==total and len({p['id'] for p in rows})==total
    path.write_text(json.dumps(rows,ensure_ascii=False),encoding='utf-8')
    return rows

products = collection('products','/wp-json/wc/store/v1/products?per_page=100&orderby=id&order=asc')
variations = collection('variations','/wp-json/wc/store/v1/products?type=variation&per_page=100&orderby=id&order=asc')
categories = collection('categories','/wp-json/wc/store/v1/products/categories?per_page=100&hide_empty=false')
expected = {v['id'] for p in products for v in p['variations']}
found = {v['id'] for v in variations}
for vid in sorted(expected-found):
    variations.append(json.loads(fetch(BASE+'/wp-json/wc/store/v1/products/'+str(vid))[0]))
(ROOT/'variations.json').write_text(json.dumps(variations,ensure_ascii=False),encoding='utf-8')
assert expected <= {v['id'] for v in variations}

ns = {'s':'http://www.sitemaps.org/schemas/sitemap/0.9'}
index = ET.fromstring(fetch(BASE+'/wp-sitemap.xml')[0])
sitemap_urls = []
for loc in index.findall('s:sitemap/s:loc', ns):
    if re.search(r'/product-sitemap\d*\.xml',loc.text):
        raw = fetch(loc.text)[0]
        (ROOT/loc.text.rsplit('/',1)[1]).write_bytes(raw)
        sitemap_urls.extend(n.text.rstrip('/') for n in ET.fromstring(raw).findall('s:url/s:loc',ns) if '/product/' in n.text)
missing = sorted(set(sitemap_urls)-{p['permalink'].rstrip('/') for p in products})
(ROOT/'coverage.json').write_text(json.dumps({'products':len(products),'variations':len(variations),'sitemap_products':len(sitemap_urls),'missing_sitemap_urls':missing},indent=2))
if missing: raise RuntimeError('Sitemap has missing products: '+str(missing))

images = {}
for p in products+variations:
    for im in p['images']: images.setdefault(im['src'],im)
    for url in re.findall(r'https?://[^\s\"\'<>]+',p['description']+' '+p['short_description']):
        url=html.unescape(url)
        if '/wp-content/uploads/' in url:
            images.setdefault(url,{'src':url,'name':urllib.parse.unquote(url.rsplit('/',1)[-1]),'alt':''})
for c in categories:
    if c.get('image'): images.setdefault(c['image']['src'],c['image'])
media = ROOT/'media'
media.mkdir(exist_ok=True)
def download(item):
    url, im = item
    suffix=pathlib.Path(urllib.parse.urlparse(url).path).suffix.lower()
    filename=hashlib.sha256(url.encode()).hexdigest()[:20]+suffix
    path=media/filename
    if not path.exists():
        data, headers=fetch(url)
        if not next((v for k,v in headers.items() if k.lower()=='content-type'),'').startswith(('image/','video/')):
            raise RuntimeError('Not image/video media: '+url)
        path.write_bytes(data)
    return {**im,'file':str(path),'sha256':hashlib.sha256(path.read_bytes()).hexdigest()}
results=[]
errors=[]
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
    futures={pool.submit(download,item):item[0] for item in images.items()}
    for f in concurrent.futures.as_completed(futures):
        try: results.append(f.result())
        except Exception as e: errors.append({'url':futures[f],'error':str(e)})
        if (len(results)+len(errors))%100==0: print('Images',len(results),'/',len(images),'errors',len(errors),flush=True)
(ROOT/'images.json').write_text(json.dumps(results,ensure_ascii=False),encoding='utf-8')
(ROOT/'download-errors.json').write_text(json.dumps(errors,indent=2),encoding='utf-8')
print('Snapshot complete:',len(products),'products,',len(variations),'variations,',len(results),'images;',len(errors),'errors',flush=True)
if errors: raise RuntimeError('Image downloads incomplete')
