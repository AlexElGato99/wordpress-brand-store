"""Resumable OCR inventory of every source product image; no media writes."""
import json, re, concurrent.futures
from pathlib import Path
import cv2
ROOT=Path('D:/boutique-migration-work')
OUT=ROOT/'watermark-audit/full-library'
OUT.mkdir(exist_ok=True)
ENGINE=None
def initialize():
    global ENGINE
    import onnxruntime as ort
    original=ort.InferenceSession
    def session(*args,**kwargs):
        options=kwargs.pop('sess_options',None) or ort.SessionOptions()
        options.intra_op_num_threads=1;options.inter_op_num_threads=1
        kwargs['sess_options']=options
        return original(*args,**kwargs)
    ort.InferenceSession=session
    from rapidocr_onnxruntime import RapidOCR
    cv2.setNumThreads(1)
    ENGINE=RapidOCR()
def scan(row):
    try:
        im=cv2.imread(row['file']);h,w=im.shape[:2]
        scale=min(1.0,1400/max(h,w))
        if scale<1: im=cv2.resize(im,None,fx=scale,fy=scale,interpolation=cv2.INTER_AREA)
        result,_=ENGINE(im)
        texts=[{'box':[[round(x/scale,2),round(y/scale,2)] for x,y in box],'text':text,'confidence':float(score)} for box,text,score in (result or [])]
        suspect=[t for t in texts if re.search(r'dri[pb]|dr1p|lmx|lmxb|boutique|www\.',t['text'],re.I)]
        return {'file':row['file'],'url':row['src'],'width':w,'height':h,'texts':texts,'suspect':suspect}
    except Exception as e:return {'file':row['file'],'url':row['src'],'error':str(e)}
if __name__=='__main__':
    rows=[r for r in json.loads((ROOT/'images.json').read_text(encoding='utf-8')) if not r['file'].endswith('.mp4')]
    path=OUT/'ocr.jsonl'
    prior=[json.loads(line) for line in path.read_text(encoding='utf-8').splitlines()] if path.exists() else []
    done={r['url'] for r in prior if 'error' not in r}
    todo=[r for r in reversed(rows) if r['src'] not in done]
    with path.open('a',encoding='utf-8') as f,concurrent.futures.ProcessPoolExecutor(max_workers=2,initializer=initialize) as pool:
        for i,r in enumerate(pool.map(scan,todo)):
            f.write(json.dumps(r)+'\n');f.flush()
            if (i+1)%50==0:print('OCR',len(done)+i+1,'/',len(rows),flush=True)
    print('Complete',len(rows),flush=True)
