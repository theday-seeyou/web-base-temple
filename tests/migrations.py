"""Verify fresh installs and legacy upgrades using three empty isolated databases."""
from pathlib import Path
import subprocess,os,re,json

PREFIX_DB=os.environ.get('WEBBASE_TEST_PREFIX_DB','webbase_test_prefix')
UPGRADE_DB=os.environ.get('WEBBASE_TEST_UPGRADE_DB','webbase_test_upgrade')
EMPTY_UPGRADE_DB=os.environ.get('WEBBASE_TEST_EMPTY_UPGRADE_DB','webbase_test_upgrade_empty')
names=[PREFIX_DB,UPGRADE_DB,EMPTY_UPGRADE_DB]
if os.environ.get('WEBBASE_TEST_ISOLATED')!='1' or not all(re.fullmatch(r'webbase_(test|verify)_[a-z0-9_]+',name) for name in names) or len(set(names))!=3:
    raise RuntimeError('Use WEBBASE_TEST_ISOLATED=1 and three distinct webbase_test_* / webbase_verify_* databases.')
root=Path(__file__).resolve().parents[1]
php=os.environ.get('WEBBASE_PHP','php')
envfile=root/'.env'; original=envfile.read_bytes()
initial=root/'database/migrations/20241010000001_install_admin20241010.php'; source=initial.read_bytes()

def run(*args):
    r=subprocess.run([php,'-d','error_reporting=24575','think',*args],cwd=root,capture_output=True,text=True,encoding='utf-8')
    if r.returncode: raise RuntimeError(r.stdout+r.stderr)
    print(r.stdout)

def state(sql,write=False):
    r=subprocess.run([php,'-d','error_reporting=24575','tests/support/state.php'],cwd=root,env=dict(os.environ,WEBBASE_TEST_ISOLATED='1'),input=json.dumps({'action':'execute' if write else 'query','sql':sql}),capture_output=True,text=True,encoding='utf-8',check=True)
    return json.loads(r.stdout)

def database(name,prefix):
    s,count=re.subn(r'(?m)^DB_MYSQL_DATABASE=[^\r\n]*',f'DB_MYSQL_DATABASE={name}',original.decode('utf-8'))
    if count!=1: raise RuntimeError('Fixture must have exactly one DB_MYSQL_DATABASE entry.')
    s,count=re.subn(r'(?m)^DB_MYSQL_PREFIX=[^\r\n]*',f'DB_MYSQL_PREFIX={prefix}',s)
    if count!=1: raise RuntimeError('Fixture must have exactly one DB_MYSQL_PREFIX entry.')
    envfile.write_bytes(s.encode('utf-8'))

def legacy_upgrade(name,prefix):
    database(name,prefix)
    legacy=source.replace(b"['password', 'string', ['limit' => 255",b"['password', 'string', ['limit' => 32")
    if legacy==source: raise RuntimeError('Legacy password-column fixture could not be prepared.')
    initial.write_bytes(legacy)
    run('migrate:run','--target=20260605012109')
    table=prefix+'system_user'; config=prefix+'system_config'
    if not state(f"SHOW COLUMNS FROM {table} LIKE 'is_deleted'"):
        state(f'ALTER TABLE {table} ADD COLUMN is_deleted TINYINT NOT NULL DEFAULT 0',True)
    state(f"INSERT INTO {table} (id,username,nickname,password,status,is_deleted) VALUES (10000,'admin','Preserved legacy','21232f297a57a5a743894a0e4a801fc3',1,0),(10001,'removed','Old recycled user','21232f297a57a5a743894a0e4a801fc3',1,1)",True)
    state(f"UPDATE {config} SET value='ckeditor5' WHERE type='base' AND name='editor'",True)
    state(f"UPDATE {config} SET value='pdf,png' WHERE type='storage' AND name='allow_exts'",True)
    initial.write_bytes(source)
    run('migrate:run')
    assert state(f"SHOW COLUMNS FROM {table} LIKE 'password'")[0]['Type']=='varchar(255)'
    assert state(f'SELECT COUNT(*) AS total FROM {table}')[0]['total']==2
    assert state(f'SELECT password FROM {table} WHERE id=10000')[0]['password']=='21232f297a57a5a743894a0e4a801fc3'
    assert state(f'SELECT deleted_at FROM {table} WHERE id=10001')[0]['deleted_at'] is not None
    assert state(f"SELECT value FROM {config} WHERE type='storage' AND name='allow_exts'")[0]['value']=='pdf,png'
    assert state(f"SELECT value FROM {config} WHERE type='base' AND name='editor'")[0]['value']=='wangEditor'
    run('migrate:run')
    print(f'PASS: {prefix or "empty-prefix"} legacy upgrade preserves users/passwords/custom settings and converts soft deletion')

for name in names:
    state('CREATE DATABASE IF NOT EXISTS `'+name+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',True)
    if int(state("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema='"+name+"'")[0]['total'])!=0:
        raise RuntimeError('Refusing to use a nonempty database: '+name)
try:
    database(PREFIX_DB,'wb_')
    run('migrate:run')
    assert state('SELECT COUNT(*) AS total FROM wb_system_user')[0]['total']==0
    assert state("SHOW COLUMNS FROM wb_system_user LIKE 'password'")[0]['Type']=='varchar(255)'
    assert state("SELECT value FROM wb_system_config WHERE type='base' AND name='editor'")[0]['value']=='wangEditor'
    run('migrate:run')
    print('PASS: fresh wb_ migration and idempotent rerun; no predictable administrator')
    legacy_upgrade(UPGRADE_DB,'wb_')
    legacy_upgrade(EMPTY_UPGRADE_DB,'')
finally:
    envfile.write_bytes(original)
    initial.write_bytes(source)
