"""Core HTTP integration using a disposable database and local server."""
from http_client import *

verify_static_assets()
state('clear-limits')
if sql('SELECT COUNT(*) AS total FROM system_user')[0]['total']==0:
    result=subprocess.run([PHP,'-d','error_reporting=24575','think','admin:init','--no-interaction'],cwd=ROOT,env=dict(os.environ,WEBBASE_ADMIN_PASSWORD=PASSWORD),capture_output=True,text=True,encoding='utf-8')
    assert result.returncode==0,result.stdout+result.stderr
c=Client(); c.page()
status,ret,_=c.request('/admin/category/add',{'title':'blocked','pid':0},csrf=False)
assert status==403 and ret['code']==0,ret
status,ret,headers=c.request('/admin/login/captcha',{'type':'test','token':'test'},headers={'Origin':'https://untrusted.example'})
assert 'Access-Control-Allow-Origin' not in headers
assert ret['code']==1 and 'code' not in ret['data']
c.login()
assert 'password' not in state('session',session=c.sid())['user']
status,ret,_=c.request('/admin/login/out')
assert ret['code']==0 and 'POST' in ret['info'],ret

name='test_'+uuid.uuid4().hex[:10]
c.post('/admin/category/add',{'pid':0,'title':name,'sort':0})
parent=sql('SELECT id FROM category WHERE title=?',[name])[0]['id']
created=sql('SELECT created_at,updated_at,deleted_at FROM category WHERE id=?',[parent])[0]
assert created['created_at'] and created['updated_at'] and created['deleted_at'] is None,created
sql('UPDATE category SET updated_at=? WHERE id=?',['2000-01-01 00:00:00',parent],True)
c.post('/admin/category/add',{'pid':parent,'title':name+'_child','sort':0})
child=sql('SELECT id FROM category WHERE title=?',[name+'_child'])[0]['id']
c.post('/admin/category/edit',{'id':parent,'pid':child,'title':name,'sort':0},0)
c.post('/admin/category/remove',{'id':parent},0)
c.post('/admin/category/edit',{'id':parent,'pid':0,'title':name+'_edited','sort':5})
edited=sql('SELECT created_at,updated_at FROM category WHERE id=?',[parent])[0]
assert edited['created_at']==created['created_at'] and edited['updated_at']>'2000-01-01 00:00:00',edited
c.post('/admin/category/state',{'id':parent,'status':0})
c.post('/admin/category/state',{'status':1},0)
c.post('/admin/category/add',{'id':parent,'pid':0,'title':'must-not-edit'},0)
c.post('/admin/category/edit',{'id':999999999,'pid':0,'title':'must-not-create'},0)
c.post('/admin/category/remove',{'id':child})
c.post('/admin/category/remove',{'id':parent})
assert sql('SELECT deleted_at FROM category WHERE id=?',[parent])[0]['deleted_at'] is not None
_,live,_=c.request('/admin/category/index?output=layui.table&type=index')
_,recycled,_=c.request('/admin/category/index?output=layui.table&type=recycle')
assert all(row['id']!=parent for row in live['data']),live
assert any(row['id']==parent and row['deleted_at'] for row in recycled['data']),recycled
c.post('/admin/category/restore',{'id':child},0)
c.post('/admin/category/restore',{'id':parent})
c.post('/admin/category/restore',{'id':child})
assert sql('SELECT deleted_at FROM category WHERE id=?',[child])[0]['deleted_at'] is None
print('PASS: login, session rotation, CSRF/CORS, secret-free session, category CRUD/recycle/cycle rules')

# PDF upload -> save attachment link -> reload -> public download.
pdf=b'%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n'
key='test/'+uuid.uuid4().hex+'.pdf'
digest=hashlib.md5(pdf).hexdigest()
meta=c.post('/admin/api.upload/state',{'key':key,'size':len(pdf),'name':'test.pdf','hash':digest,'xext':'pdf','mime':'application/pdf','uptype':'local'},404)
boundary='wb'+uuid.uuid4().hex
def multipart(content,filename,key):
    fields={'key':key,'safe':'0','uptype':'local'}
    parts=[]
    for field,value in fields.items(): parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"\r\n\r\n{value}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+content+b'\r\n')
    return b''.join(parts)+f'--{boundary}--\r\n'.encode()
status,uploaded,_=c.request('/admin/api.upload/file',body=multipart(pdf,'test.pdf',key),headers={'Content-Type':'multipart/form-data; boundary='+boundary})
assert status==200 and uploaded['code']==1,uploaded
url=uploaded['data']['url']
c.post('/admin/api.upload/done',{'id':meta['data']['id'],'hash':digest})
html=f'<p><a href="{url}">产品规格书 PDF</a></p>'
c.post('/admin/category/edit',{'id':parent,'pid':0,'title':name+'_edited','sort':5,'desc':html})
assert url in sql('SELECT `desc` FROM category WHERE id=?',[parent])[0]['desc']
download=urllib.request.urlopen(url).read()
assert download==pdf
status,bad,_=c.request('/admin/api.upload/file',body=multipart(b'<?php echo 1;','spoof.pdf','test/spoof.pdf'),headers={'Content-Type':'multipart/form-data; boundary='+boundary})
assert bad['code']==0,bad
c.post('/admin/api.upload/state',{'key':'test/too-big.pdf','size':52428801,'name':'large.pdf','hash':'test','xext':'pdf','uptype':'local'},0)
print('PASS: real PDF upload/save/reload/download, disguised file and oversized upload rejected')

# Historical hashes are rejected; modern password changes invalidate old sessions.
legacy='legacy_'+uuid.uuid4().hex[:8]
legacyHash=hashlib.md5(b'Legacy password rejected!').hexdigest()
sql('INSERT INTO system_user (username,nickname,password,status) VALUES (?,?,?,1)',[legacy,'Rejected hash test',legacyHash],True)
rejected=Client(); rejected.page()
_,challenge,_=rejected.request('/admin/login/captcha',{'type':'LoginCaptcha','token':'test'})
answer=state('captcha',session=rejected.sid())
rejected.post('/admin/login/index',{'username':legacy,'password':'Legacy password rejected!','verify':answer,'uniqid':challenge['data']['uniqid']},0)
assert not state('session',session=rejected.sid()).get('user')
assert sql('SELECT password FROM system_user WHERE username=?',[legacy])[0]['password']==legacyHash
role='role_'+uuid.uuid4().hex[:8]
sql('INSERT INTO system_auth (title,status) VALUES (?,1)',[role],True)
roleId=sql('SELECT id FROM system_auth WHERE title=?',[role])[0]['id']
newUser='new_'+uuid.uuid4().hex[:8]
c.post('/admin/user/add',{'username':newUser,'nickname':'New user test','password':PASSWORD,'authorize':str(roleId)})
assert sql('SELECT password FROM system_user WHERE username=?',[newUser])[0]['password'].startswith('$2y$')
roleClient=Client(); roleClient.login(newUser)
sql('INSERT INTO system_auth_node (auth,node) VALUES (?,?)',[roleId,'admin/category/index'],True)
_,allowed,_=roleClient.request('/admin/category/index')
assert isinstance(allowed,str) and 'CategoryTable' in allowed
sql('DELETE FROM system_auth_node WHERE auth=?',[roleId],True)
_,denied,_=roleClient.request('/admin/category/index')
assert isinstance(denied,dict) and denied['code']==0,denied
uid=sql('SELECT id FROM system_user WHERE username=?',[newUser])[0]['id']
c.post('/admin/user/pass',{'id':uid,'password':'Reset integration password!','repassword':'Reset integration password!'})
Client().login(newUser,'Reset integration password!')
sql('UPDATE system_user SET status=0 WHERE username=?',[newUser],True)
_,ret,_=roleClient.request('/admin/index/pass?id=10000')
assert isinstance(ret,dict) and ret['code']==0,ret
admin2=Client();admin2.login()
c.post('/admin/index/pass',{'id':10000,'oldpassword':PASSWORD,'password':'New integration password 2026!','repassword':'New integration password 2026!'})
_,ret,_=admin2.request('/admin/category/index')
assert isinstance(ret,dict) and ret['code']==0,ret
c.login(password='New integration password 2026!')
c.post('/admin/index/pass',{'id':10000,'oldpassword':'New integration password 2026!','password':PASSWORD,'repassword':PASSWORD})
print('PASS: account creation/reset, MD5 rejection, permission refresh, disabled-account and changed-password session invalidation')
print('HTTP integration completed successfully.')
