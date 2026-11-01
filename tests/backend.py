"""Exercise administration modules against disposable local fixtures."""
from http_client import *

state('clear-limits')
c=Client(); c.login()
suffix=uuid.uuid4().hex[:8]
def rows(module,**params):
    params.update(output='layui.table',limit=1000)
    status,ret,_=c.request(f'/admin/{module}/index?'+urllib.parse.urlencode(params))
    assert status==200 and isinstance(ret,dict) and isinstance(ret.get('data'),list),(module,status,str(ret)[:500])
    return ret['data']
def record(table,identifier):
    result=sql(f'SELECT * FROM {table} WHERE id=?',[identifier])
    return result[0] if result else None
def id_by(table,field,value): return sql(f'SELECT id FROM {table} WHERE `{field}`=?',[value])[0]['id']
def recycle(module,table,identifier,**params):
    c.post(f'/admin/{module}/remove',{'id':identifier})
    assert record(table,identifier)['deleted_at'] is not None
    assert identifier not in [r['id'] for r in rows(module,**params)]
    recycle_params={'scope':'recycle'} if module in ['base','file'] else {'type':'recycle'}
    assert identifier in [r['id'] for r in rows(module,**dict(params,**recycle_params))]
    c.post(f'/admin/{module}/restore',{'id':identifier})
    assert record(table,identifier)['deleted_at'] is None

pages=['index/index','index/theme','index/info?id=10000','index/pass?id=10000',
    'config/index','config/system','config/storage?type=local','config/storage?type=alioss','config/storage?type=qiniu','config/storage?type=txcos',
    'user/index','user/index?type=recycle','user/add','auth/index','auth/index?type=recycle','auth/add',
    'menu/index','menu/index?type=recycle','menu/add','base/index','base/index?scope=recycle','base/add',
    'file/index','file/index?scope=recycle','queue/index','oplog/index','category/index','category/index?type=recycle','category/add']
for path in pages:
    status,html,_=c.request('/admin/'+path)
    assert status==200 and isinstance(html,str) and ('layui' in html or 'csrf-token' in html),(path,status,str(html)[:450])
    assert not any(x in html for x in ['ParseError','SQLSTATE[','Undefined variable','Fatal error:']),(path,html[:500])
print(f'PASS: {len(pages)} administration pages/forms and four storage settings pages render')
for module in ['user','auth','menu','base','file','queue','oplog','category']:
    data=rows(module)
    assert all(not {'create_at','update_at','create_time','update_time'} & r.keys() for r in data),module
    if module=='user': assert all('password' not in r for r in data)
print('PASS: eight list APIs, standard audit fields, user hashes hidden')

# Role permissions survive recycling, and deletion hooks affect only recycled IDs.
role='review_role_'+suffix
c.post('/admin/auth/add',{'title':role,'status':1,'desc':'Review role','action':'save','nodes[0]':'admin/category/index','nodes[1]':'admin/file/index'})
rid=id_by('system_auth','title',role)
assert sql('SELECT COUNT(*) total FROM system_auth_node WHERE auth=?',[rid])[0]['total']==2
c.post('/admin/auth/edit',{'id':rid,'title':role,'status':1,'desc':'Edited role','action':'save','nodes[0]':'admin/category/index','nodes[1]':'admin/file/index'})
recycle('auth','system_auth',rid)
assert sql('SELECT COUNT(*) total FROM system_auth_node WHERE auth=?',[rid])[0]['total']==2
_,tree,_=c.request('/admin/auth/edit',{'id':rid,'action':'json'})
assert tree['code']==1 and isinstance(tree['data'],list),tree
def flatten(nodes):
    for node in nodes:
        yield node
        yield from flatten(node.get('_sub_',[]))
assert any(node.get('checked') and node['node']=='admin/category/index' for node in flatten(tree['data'])),tree
print('PASS: role create/edit/node assignment/recycle/restore retains permissions')

username='review_user_'+suffix
c.post('/admin/user/add',{'username':username,'nickname':'Review user','password':PASSWORD,'authorize':str(rid)})
uid=id_by('system_user','username',username)
limited=Client(); limited.login(username)
_,allowed,_=limited.request('/admin/category/index')
assert isinstance(allowed,str) and 'CategoryTable' in allowed
c.post('/admin/auth/remove',{'id':rid})
_,denied,_=limited.request('/admin/category/index')
assert isinstance(denied,dict) and denied['code']==0,denied
c.post('/admin/auth/restore',{'id':rid})
_,allowed,_=limited.request('/admin/category/index')
assert isinstance(allowed,str) and 'CategoryTable' in allowed
_,denied,_=limited.request('/admin/config/index')
assert isinstance(denied,dict) and denied['code']==0,denied
c.post('/admin/user/edit',{'id':uid,'username':username,'nickname':'Edited nickname','authorize':str(rid)})
c.post('/admin/user/state',{'id':uid,'status':0});assert record('system_user',uid)['status']==0
c.post('/admin/user/state',{'id':uid,'status':1})
recycle('user','system_user',uid)
c.post('/admin/user/remove',{'id':10000},0)
c.post('/admin/user/destroy',{'id':10000},0)
print('PASS: user create/edit/state/recycle/restore, role recycle/restore refreshes existing-session permissions, permission denial and superuser protection')

kind='review_dict_'+suffix; code='code_'+suffix
c.post('/admin/base/add',{'type':kind,'code':code,'name':'Dictionary review','status':1})
bid=id_by('system_base','code',code)
c.post('/admin/base/edit',{'id':bid,'type':kind,'code':code,'name':'Dictionary edited','status':1})
c.post('/admin/base/remove',{'id':bid})
c.post('/admin/base/add',{'type':kind,'code':code,'name':'Duplicate recycled code'},0)
assert any(r['id']==bid for r in rows('base',scope='recycle',type=kind))
c.post('/admin/base/restore',{'id':bid});assert record('system_base',bid)['deleted_at'] is None
print('PASS: dictionary create/edit/recycle/restore and recycled-code uniqueness')

parent_title='review_menu_'+suffix
menu=dict(pid=0,title=parent_title,url='#',node='',params='',target='_self',sort=0,status=1)
c.post('/admin/menu/add',menu)
mid=id_by('system_menu','title',parent_title)
child=dict(menu,pid=mid,title=parent_title+'_child',url='admin/category/index',node='admin/category/index',status=0)
c.post('/admin/menu/add',child)
cid=id_by('system_menu','title',child['title'])
assert any(r['id']==cid and r['status']==0 for r in rows('menu'))
assert not any(r['id']==cid for r in rows('menu',type='recycle'))
c.post('/admin/menu/edit',dict(menu,id=mid,pid=cid),0)
c.post('/admin/menu/remove',{'id':mid},0)
c.post('/admin/menu/remove',{'id':f'{mid},{cid}'})
c.post('/admin/menu/restore',{'id':cid},0)
c.post('/admin/menu/restore',{'id':f'{mid},{cid}'})
assert record('system_menu',mid)['deleted_at'] is None and record('system_menu',cid)['deleted_at'] is None
c.post('/admin/menu/remove',{'id':f'{mid},{cid}'})
c.post('/admin/menu/destroy',{'id':mid},0)
c.post('/admin/menu/destroy',{'id':f'{mid},{cid}'})
assert record('system_menu',mid) is None and record('system_menu',cid) is None
print('PASS: disabled menus stay in normal list, cycle/subtree guards and real menu recycle/restore/purge')

files=sql("SELECT id,xurl,deleted_at FROM system_file WHERE uuid=10000 AND status=2 AND issafe=0 AND xkey LIKE 'test/%' ORDER BY id DESC LIMIT 1")
assert files,'Run integration.py first to upload an actual PDF.'
fid=files[0]['id']; fileurl=files[0]['xurl']
if files[0]['deleted_at'] is not None: c.post('/admin/file/restore',{'id':fid})
c.post('/admin/file/edit',{'id':fid,'name':'Reviewed PDF.pdf','uuid':uid})
assert record('system_file',fid)['uuid']==10000
recycle('file','system_file',fid)
assert urllib.request.urlopen(fileurl).read().startswith(b'%PDF-')
# Give the limited account file actions, then verify every action remains owner-scoped.
for node in ['edit','remove','restore','destroy']:
    sql('INSERT INTO system_auth_node (auth,node) VALUES (?,?)',[rid,'admin/file/'+node],True)
limited.login(username)
limited.post('/admin/file/edit',{'id':fid,'name':'Foreign rename'},0)
limited.post('/admin/file/remove',{'id':fid})
assert record('system_file',fid)['deleted_at'] is None
c.post('/admin/file/remove',{'id':fid})
limited.post('/admin/file/restore',{'id':fid})
limited.post('/admin/file/destroy',{'id':fid})
assert record('system_file',fid)['deleted_at'] is not None
c.post('/admin/file/restore',{'id':fid})

duplicate_key='review/'+suffix+'.pdf'
def duplicate(owner,safe=0):
    sql('INSERT INTO system_file (type,xkey,xurl,hash,name,xext,mime,uuid,issafe,status) SELECT type,?,xurl,hash,?,xext,mime,?,?,2 FROM system_file WHERE id=?',
        [duplicate_key,'Duplicate '+suffix,owner,safe,fid],True)
    return sql('SELECT MAX(id) id FROM system_file')[0]['id']
older=duplicate(10000); latest=duplicate(10000); foreign=duplicate(uid); safe=duplicate(10000,1)
c.post('/admin/file/distinct',{})
assert record('system_file',older)['deleted_at'] is not None
assert all(record('system_file',identifier)['deleted_at'] is None for identifier in [latest,foreign,safe])
assert any(r['id']==latest for r in rows('file',type=record('system_file',latest)['type']))
limited.post('/admin/file/edit',{'id':foreign,'name':'Own renamed file'})
assert record('system_file',foreign)['name']=='Own renamed file'
limited.post('/admin/file/remove',{'id':foreign})
limited.post('/admin/file/restore',{'id':foreign})
assert record('system_file',foreign)['deleted_at'] is None
limited.post('/admin/file/remove',{'id':foreign})
limited.post('/admin/file/destroy',{'id':foreign})
assert record('system_file',foreign) is None
assert urllib.request.urlopen(fileurl).read().startswith(b'%PDF-')
print('PASS: file edit/recycle/restore/download/purge, non-super owner isolation, duplicate cleanup soft-deletes only older own public records and storage type filter works')

previous=state('query',sql="SELECT value FROM system_config WHERE type='base' AND name='site_name'")[0]['value']
c.post('/admin/config/system',{'site_name':'Review site '+suffix})
assert state('query',sql="SELECT value FROM system_config WHERE type='base' AND name='site_name'")[0]['value']=='Review site '+suffix
c.post('/admin/config/system',{'site_name':previous})
assert rows('oplog')
sql('INSERT INTO system_queue (code,title,command,status) VALUES (?,?,?,3)',[suffix,'Review completed task','help'],True)
qid=id_by('system_queue','code',suffix)
assert any(r['id']==qid for r in rows('queue'))
c.post('/admin/queue/redo',{'code':suffix})
assert record('system_queue',qid)['status']==1
c.post('/admin/queue/remove',{'id':qid})
assert record('system_queue',qid) is None
sql('INSERT INTO system_oplog (action,content,username) VALUES (?,?,?)',['Review test',suffix,'admin'],True)
oid=id_by('system_oplog','content',suffix)
c.post('/admin/oplog/remove',{'id':oid})
assert record('system_oplog',oid) is None
for path in ['/admin/file/distinct','/admin/queue/redo','/admin/queue/clean','/admin/oplog/clear']:
    status,result,_=c.request(path)
    assert status==200 and isinstance(result,dict) and result['code']==0,(path,result)
print('PASS: queued task list/reset/delete, operation log delete and maintenance endpoints reject GET')
# Mixed normal/recycled IDs must preserve a live role's grants.
live='review_live_'+suffix
c.post('/admin/auth/add',{'title':live,'action':'save','nodes[0]':'admin/category/index'})
liveid=id_by('system_auth','title',live)
c.post('/admin/auth/remove',{'id':rid})
c.post('/admin/auth/destroy',{'id':f'{rid},{liveid}'})
assert record('system_auth',rid) is None and record('system_auth',liveid)['deleted_at'] is None
assert sql('SELECT COUNT(*) total FROM system_auth_node WHERE auth=?',[rid])[0]['total']==0
assert sql('SELECT COUNT(*) total FROM system_auth_node WHERE auth=?',[liveid])[0]['total']==1
print('PASS: system setting save/reload/restore, operation logging, mixed-ID role purge preserves live grants')
print('Administration review completed successfully.')
