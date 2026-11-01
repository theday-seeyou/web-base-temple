"""Create an isolated test project; use published locked dependencies by default."""
import json,os,shutil
from pathlib import Path
root=Path(__file__).resolve().parents[1]
target=Path(os.environ['WEBBASE_TEST_FIXTURE']).resolve()
assert not target.exists(), 'Use a fresh fixture directory.'
shutil.copytree(root,target,ignore=shutil.ignore_patterns('.git','.codex-tmp','.env','vendor','runtime','node_modules','upload','safefile','companion-core'))
if os.environ.get('WEBBASE_CORE_SOURCE'):
    core=Path(os.environ['WEBBASE_CORE_SOURCE']).resolve()
    assert (core/'src/service/PasswordService.php').is_file(), 'Use the companion security-fix Core source.'
    manifest=json.loads((target/'composer.json').read_text(encoding='utf-8'))
    manifest['require']['theday-seeyou/web-base-core']='^0.5'
    manifest['repositories']=[{'type':'path','url':core.as_posix(),'options':{'symlink':True,'versions':{'theday-seeyou/web-base-core':'0.5.0'}}}]
    (target/'composer.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
config=(target/'.env.example').read_text(encoding='utf-8')
values={
    'DB_MYSQL_HOST':os.environ.get('WEBBASE_TEST_DB_HOST','127.0.0.1'),
    'DB_MYSQL_PORT':os.environ.get('WEBBASE_TEST_DB_PORT','3306'),
    'DB_MYSQL_DATABASE':os.environ.get('WEBBASE_TEST_DB_NAME','webbase_test_main'),
    'DB_MYSQL_USERNAME':os.environ.get('WEBBASE_TEST_DB_USER','root'),
    'DB_MYSQL_PASSWORD':os.environ.get('WEBBASE_TEST_DB_PASSWORD',''),
    'DB_MYSQL_PREFIX':'',
}
import re
for key,value in values.items(): config=re.sub(r'(?m)^'+key+r'=.*$',lambda _: key+'='+value,config)
(target/'.env').write_text(config,encoding='utf-8')
mode='local companion source (run composer update)' if os.environ.get('WEBBASE_CORE_SOURCE') else 'published locked dependencies (run composer install)'
print('Isolated fixture created: '+mode+'. Original project files were not changed.')
