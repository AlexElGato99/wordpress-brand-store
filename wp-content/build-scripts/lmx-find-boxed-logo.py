import json,cv2,numpy as np,concurrent.futures
from pathlib import Path
from PIL import Image,ImageDraw
root=Path('D:/boutique-migration-work'); out=root/'watermark-audit/boxed-logo';out.mkdir(exist_ok=True)
items=[x for x in json.loads((root/'images.json').read_text(encoding='utf8')) if not x['file'].endswith('.mp4')]
ref=cv2.imread('C:/xampp/boutique-migration/media/8dc644ccbee210dced94.jpg',0)
t=ref[1388:1508,550:792];t=cv2.resize(t,(80,40))
templates=[cv2.resize(t,None,fx=s,fy=s) for s in [.5,.625,.75,.875,1,1.125,1.25,1.5]]
cv2.setNumThreads(1)
def scan(x):
 im=cv2.imread(x['file'],0)
 if im is None:return None
 h,w=im.shape; im=cv2.resize(im,(500,round(h*500/w))); top=round(im.shape[0]*.3); im=im[top:]; best=(-1,None,None)
 for t in templates:
  if t.shape[0]>=im.shape[0] or t.shape[1]>=im.shape[1]:continue
  _,s,_,p=cv2.minMaxLoc(cv2.matchTemplate(im,t,cv2.TM_CCOEFF_NORMED))
  if s>best[0]:best=(s,p,t.shape)
 s,p,sh=best
 return {'file':x['file'],'url':x['src'],'score':round(s,4),'bbox':[round(p[0]*w/500),round((p[1]+top)*w/500),round(sh[1]*w/500),round(sh[0]*w/500)],'width':w,'height':h}
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
 arr=[]
 for i,x in enumerate(pool.map(scan,items)):
  if x:arr.append(x)
  if i%500==0: print(i,flush=True)
arr.sort(key=lambda x:x['score'],reverse=True);(out/'scan.json').write_text(json.dumps(arr,indent=2))
for start in range(0,min(len(arr),320),48):
 canvas=Image.new('RGB',(1600,1500),'#ddd'); d=ImageDraw.Draw(canvas)
 for k,x in enumerate(arr[start:start+48]):
  im=Image.open(x['file']); im.thumbnail((195,220)); xx=(k%8)*200;yy=(k//8)*250;canvas.paste(im,(xx+(200-im.width)//2,yy));d.text((xx+3,yy+222),f'{start+k} {x["score"]}',fill='black')
 canvas.save(out/f'candidates-{start}.jpg')
print('top',[(x['score'],x['url']) for x in arr[:8]],flush=True)
