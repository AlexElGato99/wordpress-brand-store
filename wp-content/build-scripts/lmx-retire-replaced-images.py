"""Archive replaced watermarked files outside the website; redirect old URLs."""
from pathlib import Path
import json, hashlib, shutil, re, urllib.request
ROOT=Path('D:/boutique-migration-work/watermark-audit/background-cleanup')
PUBLIC=Path('C:/xampp/htdocs/boutique/wp-content/uploads/lmx-catalog').resolve()
ARCHIVE=(ROOT/'retired-public-files').resolve()
ARCHIVE.mkdir(exist_ok=True)
state=json.loads((ROOT/'wordpress-state.json').read_text())
installed=json.loads((ROOT/'installed.json').read_text())
entries=[]
for image in installed['attachments']:
    old=state['attachments'][str(image['id'])]
    pairs=[(Path(old['file']).name,Path(image['file']).name)]
    for key,size in old['metadata'].get('sizes',{}).items():
        pairs.append((size['file'],image['sizes'].get(key,{}).get('file',Path(image['file']).name)))
    for before,after in pairs:
        source=(PUBLIC/before).resolve();dest=(ARCHIVE/before).resolve();clean=(PUBLIC/after).resolve()
        assert source.parent==PUBLIC and clean.parent==PUBLIC and dest.parent==ARCHIVE
        assert clean.is_file() and source!=clean
        if source.is_file():
            digest=hashlib.sha256(source.read_bytes()).hexdigest()
            if dest.exists(): assert hashlib.sha256(dest.read_bytes()).hexdigest()==digest
            else: shutil.copy2(source,dest)
        else:
            assert dest.is_file();digest=hashlib.sha256(dest.read_bytes()).hexdigest()
        entries.append({'public_file':str(source),'archive_file':str(dest),'replacement':str(clean),'sha256':digest,'url':old['url'].rsplit('/',1)[0]+'/'+before,'new_url':image['url'].rsplit('/',1)[0]+'/'+after})
(ROOT/'retired-files.json').write_text(json.dumps(entries,indent=2))
htaccess=PUBLIC/'.htaccess'
marker='# BEGIN LMX cleaned image redirects'
original=htaccess.read_text() if htaccess.exists() else ''
original=re.sub(r'# BEGIN LMX cleaned image redirects.*?# END LMX cleaned image redirects\s*','',original,flags=re.S)
lines=[marker,'<IfModule mod_rewrite.c>','RewriteEngine On']
for e in entries:
    lines.append('RewriteRule ^'+re.escape(Path(e['public_file']).name)+'$ /boutique/wp-content/uploads/lmx-catalog/'+Path(e['replacement']).name+' [R=302,L,NE]')
lines+=['</IfModule>','# END LMX cleaned image redirects','']
htaccess.write_text('\n'.join(lines)+original)
# Verify redirects before retiring any public originals.
for e in entries:
    with urllib.request.urlopen(e['url'],timeout=30) as response:
        assert response.geturl()==e['new_url'],response.geturl()
        assert hashlib.sha256(response.read()).hexdigest()==hashlib.sha256(Path(e['replacement']).read_bytes()).hexdigest()
for e in entries:
    source=Path(e['public_file']).resolve();dest=Path(e['archive_file']).resolve()
    assert source.parent==PUBLIC and dest.parent==ARCHIVE
    assert hashlib.sha256(dest.read_bytes()).hexdigest()==e['sha256']
    if source.exists(): source.unlink()
print(json.dumps({'archived_public_files':len(entries),'redirects_verified':len(entries)}))
