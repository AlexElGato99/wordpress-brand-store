"""Read-only watermark scan; writes inspection sheets, never changes catalog media."""
import json, pathlib, concurrent.futures
import cv2
import numpy as np
from PIL import Image, ImageDraw
ROOT=pathlib.Path('C:/xampp/boutique-migration')
OUT=ROOT/'watermark-audit'
OUT.mkdir(exist_ok=True)
items=[x for x in json.loads((ROOT/'images.json').read_text(encoding='utf-8')) if not x['file'].endswith('.mp4')]
reference=cv2.imread(str(ROOT/'media/1f4a30656ba4a9764e0f.jpg'))
template=(np.min(reference[1240:1310,795:1040],axis=2)>230).astype(np.uint8)*255
templates=[cv2.resize(template,None,fx=s,fy=s,interpolation=cv2.INTER_AREA) for s in [.30,.38,.45,.50,.55,.62,.72,.85,1.0]]
cv2.setNumThreads(1)
def scan(im):
    data=cv2.imread(im['file'])
    h,w=data.shape[:2]
    scaled=cv2.resize(data,(540,round(h*540/w)))
    white=(np.min(scaled,axis=2)>230).astype(np.uint8)*255
    start=int(len(white)*.55)
    region=white[start:]
    best=0; bbox=None
    for t in templates:
        if t.shape[0]>=region.shape[0] or t.shape[1]>=region.shape[1]: continue
        result=cv2.matchTemplate(region,t,cv2.TM_CCOEFF_NORMED)
        _,score,_,loc=cv2.minMaxLoc(result)
        if score>best:
            best=score; bbox=[round(loc[0]*w/540),round((loc[1]+start)*w/540),round(t.shape[1]*w/540),round(t.shape[0]*w/540)]
    return {'file':im['file'],'url':im['src'],'score':round(best,4),'bbox':bbox,'width':w,'height':h}
results=[]
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    for i,r in enumerate(pool.map(scan,items)):
        results.append(r)
        if (i+1)%500==0: print('Scanned',i+1,'/',len(items),flush=True)
results.sort(key=lambda x:x['score'],reverse=True)
(OUT/'scan.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
candidates=[x for x in results if x['score']>=.38]
for start in range(0,len(candidates),48):
    canvas=Image.new('RGB',(1600,1500),'#eeeeee'); d=ImageDraw.Draw(canvas)
    for k,x in enumerate(candidates[start:start+48]):
        im=Image.open(x['file']); im.thumbnail((196,220))
        xx=(k%8)*200; yy=(k//8)*250
        canvas.paste(im,(xx+(200-im.width)//2,yy))
        d.text((xx+4,yy+222),f'{start+k}: {x["score"]:.3f}',fill='black')
    canvas.save(OUT/f'candidates-{start}.jpg')
(OUT/'candidates.json').write_text(json.dumps(candidates,indent=2),encoding='utf-8')
print('Candidates',len(candidates),'strong',sum(x['score']>=.65 for x in results),flush=True)
