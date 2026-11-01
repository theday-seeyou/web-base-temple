"""Verify the fresh-install schema and model lifecycle with and without a prefix."""
from pathlib import Path
import subprocess,os,re,json

names=[os.environ.get('WEBBASE_TEST_PREFIX_DB','webbase_test_prefix'),
       os.environ.get('WEBBASE_TEST_EMPTY_DB','webbase_test_empty')]
if os.environ.get('WEBBASE_TEST_ISOLATED')!='1' or not all(re.fullmatch(r'webbase_(test|verify)_[a-z0-9_]+',name) for name in names) or len(set(names))!=2:
    raise RuntimeError('Use WEBBASE_TEST_ISOLATED=1 and two distinct webbase_test_* / webbase_verify_* databases.')
root=Path(__file__).resolve().parents[1]
php=os.environ.get('WEBBASE_PHP','php')
envfile=root/'.env'; original=envfile.read_bytes()

def run(*args,success=True):
    r=subprocess.run([php,'-d','error_reporting=24575','think',*args],cwd=root,capture_output=True,text=True,encoding='utf-8')
    if success and r.returncode: raise RuntimeError(r.stdout+r.stderr)
    if not success:
        assert r.returncode!=0 and 'Fresh installation requires unused table names' in r.stdout+r.stderr,r.stdout+r.stderr
    return r.stdout

def state(action='query',**data):
    r=subprocess.run([php,'-d','error_reporting=24575','tests/support/state.php'],cwd=root,input=json.dumps(dict(action=action,**data)),capture_output=True,text=True,encoding='utf-8',check=True)
    return json.loads(r.stdout)

def query(sql,write=False):
    return state('execute' if write else 'query',sql=sql)

def database(name,prefix):
    s,count=re.subn(r'(?m)^DB_MYSQL_DATABASE=[^\r\n]*',f'DB_MYSQL_DATABASE={name}',original.decode('utf-8'))
    assert count==1
    s,count=re.subn(r'(?m)^DB_MYSQL_PREFIX=[^\r\n]*',f'DB_MYSQL_PREFIX={prefix}',s)
    assert count==1
    envfile.write_bytes(s.encode('utf-8'))

for name in names:
    query('CREATE DATABASE IF NOT EXISTS `'+name+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',True)
    if int(query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema='"+name+"'")[0]['total'])!=0:
        raise RuntimeError('Refusing to use a nonempty database: '+name)

generated=None
try:
    before=set((root/'database/migrations').glob('*.php'))
    run('migrate:create','CreateAuditProbe')
    added=set((root/'database/migrations').glob('*.php'))-before
    assert len(added)==1
    generated=added.pop()
    # Make ordering deterministic even if the machine clock precedes the baseline.
    version=max(int(path.name[:14]) for path in before)+1
    ordered=generated.with_name(f'{version:014d}_create_audit_probe.php')
    generated.rename(ordered)
    generated=ordered
    template=generated.read_text(encoding='utf-8')
    assert 'use WebBase\\Core\\migration\\Migrator;' in template
    generated.write_text(template.replace("        // createTable adds id, created_at, updated_at and nullable deleted_at.",
        "        $this->createTable('audit_probe')->addColumn('title', 'string', ['default' => ''])->create();"),encoding='utf-8')
    for name,prefix in zip(names,['wb_','']):
        database(name,prefix)
        run('migrate:run')
        tables=[row['TABLE_NAME'] for row in query('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE()')]
        expected=['system_auth','system_auth_node','system_base','system_config','system_data','system_file','system_menu','system_oplog','system_queue','system_user','category','audit_probe']
        assert set(tables)=={prefix+t for t in expected}|{prefix+'migrations'},tables
        for table in [prefix+t for t in expected]:
            columns={row['Field']:row for row in query(f'SHOW COLUMNS FROM `{table}`')}
            assert columns['id']['Key']=='PRI' and 'auto_increment' in columns['id']['Extra'],table
            assert not {'create_at','update_at','create_time','update_time','datetime','deleted','is_deleted','deleted_time','delete_time'} & columns.keys(),table
            for field in ['created_at','updated_at','deleted_at']:
                assert columns[field]['Type']=='datetime',(table,field,columns[field])
            assert columns['deleted_at']['Null']=='YES' and columns['deleted_at']['Default'] is None,table
            assert columns['created_at']['Null']=='NO' and columns['updated_at']['Null']=='NO',table
            assert 'on update CURRENT_TIMESTAMP' in columns['updated_at']['Extra'],table
        assert query(f'SELECT COUNT(*) AS total FROM {prefix}system_user')[0]['total']==0
        assert query(f"SELECT value FROM {prefix}system_config WHERE type='base' AND name='editor'")[0]['value']=='wangEditor'
        assert query(f"SHOW COLUMNS FROM {prefix}system_user LIKE 'password'")[0]['Type']=='varchar(255)'
        query(f"INSERT INTO {prefix}audit_probe (title) VALUES ('probe')",True)
        row=query(f'SELECT * FROM {prefix}audit_probe')[0]
        assert row['id']>0 and row['created_at'] and row['updated_at'] and row['deleted_at'] is None,row
        assert state('model-lifecycle') is True
        run('migrate:run')
        # A generated change() migration must still support rollback and recreation.
        run('migrate:rollback','--target=20261101010003','--force')
        assert not query(f"SHOW TABLES LIKE '{prefix}audit_probe'")
        run('migrate:run')
        query(f"INSERT INTO {prefix}audit_probe (title) VALUES ('after rollback')",True)
        query(f"DELETE FROM {prefix}migrations WHERE version=20261101010000",True)
        run('migrate:run',success=False)
        assert query(f'SELECT COUNT(*) AS total FROM {prefix}audit_probe')[0]['total']==1
        print(f'PASS: {prefix or "empty-prefix"} fresh schema, generated migration, model audit/soft-delete lifecycle, idempotence and occupied-table refusal')
finally:
    envfile.write_bytes(original)
    if generated is not None: generated.unlink()
