"""Search existing source images for matching clean originals; never edits images."""
import json,pathlib,concurrent.futures
import cv2
import numpy as np
ROOT=pathlib.Path('C:/xampp/boutique-migration/watermark-audit')
rows=json.loads((ROOT/'scan.json').read_text())
cv2.setNumThreads(1)
def features(row):
    im=cv2.imread(row['file'])
    # Ignore bottom watermark area only for candidate similarity analysis.
    im=cv2.resize(im[:int(im.shape[0]*.82)],(40,32),interpolation=cv2.INTER_AREA)
    return im.astype(np.float32).ravel()
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    values=np.stack(list(pool.map(features,rows)))
clean=[i for i,x in enumerate(rows) if x['score']<.6]
targets=[i for i,x in enumerate(rows) if x['score']>=.65]
v=values[clean]; norms=(v*v).sum(axis=1)
results=[]
for idx in targets:
    a=values[idx]
    scores=np.maximum(0,(norms+(a*a).sum()-2*v@a)/len(a))
    for j,ci in enumerate(clean):
        if abs(rows[ci]['width']/rows[ci]['height']-rows[idx]['width']/rows[idx]['height'])>.025: scores[j]=1e9
    best=np.argsort(scores)[:3]
    results.append({'watermarked':rows[idx]['file'],'url':rows[idx]['url'],'nearest':[{'file':rows[clean[j]]['file'],'url':rows[clean[j]]['url'],'rmse':float(np.sqrt(scores[j]))} for j in best]})
(ROOT/'clean-match-search.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print('Images checked:',len(rows),'watermarked:',len(targets),'near-exact candidates:',sum(x['nearest'][0]['rmse']<3 for x in results),'best RMSE:',min(x['nearest'][0]['rmse'] for x in results),flush=True)
