"""Read-only storefront checks plus one disposable local cart (no order or checkout)."""
import json, pathlib, urllib.request, urllib.parse, re

ROOT=pathlib.Path('C:/xampp/boutique-migration')
BASE='http://localhost/boutique'

def get(path):
    with urllib.request.urlopen(BASE+path,timeout=90) as r:
        return r.read(),dict(r.headers)

data,headers=get('/wp-json/wc/store/v1/products?per_page=100')
rows=json.loads(data)
total=int(next(v for k,v in headers.items() if k.lower()=='x-wp-total'))
pages=int(next(v for k,v in headers.items() if k.lower()=='x-wp-totalpages'))
for page in range(2,pages+1):
    rows.extend(json.loads(get('/wp-json/wc/store/v1/products?per_page=100&page='+str(page))[0]))
source=json.loads((ROOT/'products.json').read_text(encoding='utf-8'))
assert total==len(source)==547,(total,len(source))
assert {p['slug'] for p in rows}=={p['slug'] for p in source}, 'Catalog slugs differ'
checks=[]
for path in ['/', '/shop/', '/sale/', '/product-category/men/', '/product-category/jackets/']:
    raw,_=get(path)
    page=raw.decode()
    assert 'Fatal error' not in page,path
    links=re.findall(r'href=["\x27]([^"\x27]*/product/[^"\x27]*)',page)
    assert links,'No products rendered on '+path
    checks.append({'path':path,'product_links':len(links),'bytes':len(raw)})
sample=next(p for p in rows if p['type']=='variable' and p['is_purchasable'] and p['variations'])
raw,_=get('/product/'+sample['slug']+'/')
page=raw.decode()
assert 'variations_form' in page and 'Fatal error' not in page
assert '/uploads/lmx-catalog/' in page
assert 'woocommerce-product-gallery' in page or 'woocommerce-product-images' in page
simple=next(p for p in rows if p['type']=='simple' and p['is_purchasable'])
raw,_=get('/product/'+simple['slug']+'/')
assert '/uploads/lmx-catalog/' in raw.decode()
_,cart_headers=get('/wp-json/wc/store/v1/cart')
token=next(v for k,v in cart_headers.items() if k.lower()=='cart-token')
variation=next(json.loads(get('/wp-json/wc/store/v1/products/'+str(v['id']))[0]) for v in sample['variations'])
payload=json.dumps({'id':variation['id'],'quantity':1}).encode()
req=urllib.request.Request(BASE+'/wp-json/wc/store/v1/cart/add-item',data=payload,headers={'Content-Type':'application/json','Cart-Token':token})
with urllib.request.urlopen(req,timeout=90) as r: cart=json.load(r)
assert cart['items_count']==1
assert cart['items'][0]['prices']['price']==variation['prices']['price']
# Clear the test session immediately.
req=urllib.request.Request(BASE+'/wp-json/wc/store/v1/cart/items',method='DELETE',headers={'Cart-Token':token})
with urllib.request.urlopen(req,timeout=90) as r: cleared=json.load(r)
req=urllib.request.Request(BASE+'/wp-json/wc/store/v1/cart',headers={'Cart-Token':token})
with urllib.request.urlopen(req,timeout=90) as r: cleared=json.load(r)
assert cleared['items_count']==0
report={'catalog_total':total,'pages':checks,'variable_product':sample['slug'],'simple_product':simple['slug'],'variation_cart_price':variation['prices']['price'],'test_cart_cleared':True}
(ROOT/'storefront-verification.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
