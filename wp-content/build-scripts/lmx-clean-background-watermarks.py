"""Explicitly authorized masked background cleanup. Originals remain untouched."""
import json, hashlib
from pathlib import Path
import cv2
import numpy as np
from PIL import Image, ImageDraw

ROOT = Path('C:/xampp/boutique-migration/watermark-audit')
OUT = ROOT / 'background-cleanup'
OUT.mkdir(exist_ok=True)
rows = json.loads((ROOT / 'scan.json').read_text())[:73]
# Individually reviewed full photos and full-resolution logo crops.
# Includes glass/backdrop only; excludes every overlap with garments, tags or shoes.
approved = {0,1,2,3,4,5,7,8,9,18,22,30,31,38,45,48,54,57,58,61,64,65,66}
reference = cv2.imread(rows[0]['file'])
mask = np.zeros(reference.shape[:2], np.uint8)
mask[1235:1319,789:1045] = 255
cv2.imwrite(str(OUT / 'mask.png'), mask)
results=[]
existing=json.loads((OUT/'manifest.json').read_text()) if (OUT/'manifest.json').exists() else []
for i, row in enumerate(rows):
    if i < len(existing) and existing[i].get('method','').startswith('Masked background patch'):
        record=existing[i]
        assert hashlib.sha256(Path(record['clean_file']).read_bytes()).hexdigest()==record['clean_sha256']
        results.append(record)
        continue
    record = dict(row, review_index=i, status='deferred_product_overlap_or_complex_background')
    if i in approved:
        original = cv2.imread(row['file'])
        assert original.shape == reference.shape
        # Copy only nearby background texture; Poisson blending matches its lighting.
        # No generated product details and no global recompression.
        donor_x = 150 if i in {22,58} else 500
        patch = original[1235:1319,donor_x:donor_x+256].copy()
        patch_mask = np.full(patch.shape[:2],255,np.uint8)
        blended = cv2.seamlessClone(patch,original,patch_mask,(917,1277),cv2.NORMAL_CLONE)
        cleaned = original.copy()
        cleaned[mask>0] = blended[mask>0]
        if i in {1,2,3,4,9,30,31,38,54,61}:
            # Smooth backgrounds: solve the harmonic interpolation from the
            # untouched surrounding border instead of duplicating objects.
            region = original[1234:1320,788:1046].astype(np.float32)
            t = np.linspace(0,1,len(region))[:,None,None]
            region[1:-1,1:-1] = ((1-t)*region[0:1]+t*region[-1:])[1:-1,1:-1]
            kernel=np.array([[0,.25,0],[.25,0,.25],[0,.25,0]],np.float32)
            for _ in range(2500):
                region[1:-1,1:-1]=cv2.filter2D(region,-1,kernel)[1:-1,1:-1]
            cleaned[1235:1319,789:1045] = np.clip(np.rint(region[1:-1,1:-1]),0,255).astype(np.uint8)
        assert np.array_equal(original[mask==0],cleaned[mask==0])
        dest = OUT / (Path(row['file']).stem + '-clean.png')
        cv2.imwrite(str(dest), cleaned)
        saved = cv2.imread(str(dest))
        assert np.array_equal(saved,cleaned)
        record.update(status='prepared',clean_file=str(dest),mask_file=str(OUT/'mask.png'),
            outside_mask_changed_pixels=0,source_sha256=hashlib.sha256(Path(row['file']).read_bytes()).hexdigest(),
            clean_sha256=hashlib.sha256(dest.read_bytes()).hexdigest())
    results.append(record)
(OUT/'manifest.json').write_text(json.dumps(results,indent=2))
chosen=[r for r in results if r['status']=='prepared']
for start in range(0,len(chosen),24):
    sheet=Image.new('RGB',(1600,1440),'#ddd'); draw=ImageDraw.Draw(sheet)
    for j,r in enumerate(chosen[start:start+24]):
        crop=Image.open(r['clean_file']).crop((700,1120,1080,1350))
        x=j%4*400;y=j//4*240
        sheet.paste(crop,(x,y));draw.text((x,y),str(r['review_index']),fill='red',stroke_width=1)
    sheet.save(OUT/f'preview-{start}.png')
print(json.dumps({'prepared':len(chosen),'deferred':len(rows)-len(chosen),'outside_mask_changes':0}))
