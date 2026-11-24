"""Check installed originals, lossless pixel invariants, HTTP assets and galleries."""
import json, hashlib, concurrent.futures, urllib.request, urllib.parse
from pathlib import Path
import cv2
import numpy as np
ROOT=Path('D:/boutique-migration-work')
OUT=ROOT/'watermark-audit/background-cleanup'
manifest=json.loads((OUT/'manifest.json').read_text())
installed=json.loads((OUT/'installed.json').read_text())
state=json.loads((OUT/'wordpress-state.json').read_text())
by_url={r['url']:r for r in manifest}
def fetch(url):
    with urllib.request.urlopen(url,timeout=90) as response:
        return response.read()
def check(item):
    row=by_url[item['source_url']]
    original=cv2.imread(row['file']);clean=cv2.imread(item['file']);mask=cv2.imread(row['mask_file'],0)
    assert original.shape==clean.shape
    assert np.array_equal(original[mask==0],clean[mask==0])
    backup=Path(state['attachments'][str(item['id'])]['file'])
    if not backup.exists(): backup=OUT/'retired-public-files'/backup.name
    assert hashlib.sha256(backup.read_bytes()).hexdigest()==row['source_sha256']
    assert hashlib.sha256(fetch(item['url'])).hexdigest()==row['clean_sha256']
    for size in item['sizes'].values():
        url=item['url'].rsplit('/',1)[0]+'/'+size['file']
        data=fetch(url)
        assert cv2.imdecode(np.frombuffer(data,np.uint8),cv2.IMREAD_COLOR) is not None
    return {'attachment_id':item['id'],'outside_mask_changed_pixels':0,'original_preserved':True,'http_verified':True,'thumbnails':len(item['sizes'])}
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    checks=list(pool.map(check,installed['attachments']))
affected=[]
changed={r['source_url']:r for r in installed['attachments']}
for p in json.loads((ROOT/'products.json').read_text(encoding='utf-8')):
    expected=[changed[x['src']] for x in p['images'] if x['src'] in changed]
    if not expected: continue
    url='http://localhost/boutique/wp-json/wc/store/v1/products?slug='+urllib.parse.quote(p['slug'])
    rows=[r for r in json.loads(fetch(url)) if r['slug']==p['slug'] and r['type']!='variation'];assert len(rows)==1,(p['slug'],len(rows))
    live=rows[0];images={im['id']:im['src'] for im in live['images']}
    for item in expected: assert images[item['id']]==item['url'],(p['slug'],item['id'])
    html=fetch(live['permalink']).decode('utf-8')
    for item in expected: assert item['url'] in html or item['url'].replace('/','\\/') in html
    affected.append({'id':live['id'],'name':live['name'],'url':live['permalink'],'updated_images':len(expected)})
result={'passed':True,'installed_images':len(checks),'deferred_white_watermarks':len(manifest)-len(checks),'catalog_unchanged':installed['catalog_unchanged'],'checks':checks,'products':affected}
(OUT/'verification.json').write_text(json.dumps(result,indent=2))
print(json.dumps({k:v for k,v in result.items() if k!='checks'},indent=2))
