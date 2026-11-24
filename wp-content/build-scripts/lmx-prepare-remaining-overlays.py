"""Prepare constrained texture patches for remaining repetitive fabric overlays."""
import json,cv2,numpy as np
from pathlib import Path
from PIL import Image,ImageDraw
ROOT=Path('D:/boutique-migration-work/watermark-audit')
OUT=ROOT/'remaining-overlays';OUT.mkdir(exist_ok=True)
rows=json.loads((ROOT/'scan.json').read_text())[:73]
indices=[10,11,13,14,15,16,21,23,24,25,29,32,34,35,44,46,52,55,59,62,68,70]
for i in indices:
    im=cv2.imread(rows[i]['file'])
    patch=im[1135:1219,789:1045].copy()
    mask=np.full(patch.shape[:2],255,np.uint8)
    blended=cv2.seamlessClone(patch,im,mask,(917,1277),cv2.NORMAL_CLONE)
    cleaned=im.copy();cleaned[1235:1319,789:1045]=blended[1235:1319,789:1045]
    cv2.imwrite(str(OUT/f'{i}.png'),cleaned)
for start in range(0,len(indices),24):
    sheet=Image.new('RGB',(1600,1440),'#ddd');d=ImageDraw.Draw(sheet)
    for k,i in enumerate(indices[start:start+24]):
        x=k%4*400;y=k//4*240
        sheet.paste(Image.open(OUT/f'{i}.png').crop((700,1120,1080,1350)),(x,y));d.text((x,y),str(i),fill='red',stroke_width=1)
    sheet.save(OUT/f'preview-{start}.png')
manifest=json.loads((ROOT/'background-cleanup/manifest.json').read_text())
for r in manifest:
    if r['status']=='prepared':continue
    i=r['review_index'];im=Image.open(r['file']);im.crop((600,1050,1080,1350)).save(OUT/f'target-{i}.png')
