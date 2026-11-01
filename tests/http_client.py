"""Run against a disposable migrated Starter and its local HTTP server.

Set WEBBASE_TEST_ISOLATED=1, WEBBASE_PHP and WEBBASE_TEST_URL.
The CLI helper reads our own test captcha cache; no public captcha bypass exists.
"""
import hashlib,http.cookiejar,json,os,re,subprocess,urllib.request,urllib.parse,urllib.error,uuid
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('WEBBASE_PHP','php')
BASE=os.environ.get('WEBBASE_TEST_URL','http://127.0.0.1:18381')
assert os.environ.get('WEBBASE_TEST_ISOLATED')=='1'
PASSWORD='Local integration password 2026!'

def verify_static_assets():
    """Check that a clean Composer install serves the packaged CSS and JS."""
    assets={
        '/static/theme/css/login.css':'text/css',
        '/static/theme/css/console.css':'text/css',
        '/static/plugs/layui/css/layui.css':'text/css',
        '/static/admin.js':'javascript',
        '/static/login.js':'javascript',
    }
    for path,content_type in assets.items():
        with urllib.request.urlopen(BASE+path) as response:
            body=response.read()
            assert response.status==200 and content_type in response.headers.get('Content-Type',''),path
            assert len(body)>1000 and b'<!DOCTYPE html' not in body[:100],path
    print('PASS: packaged login/admin CSS and JavaScript return correct content types')
def state(action,**data):
    result=subprocess.run([PHP,'-d','error_reporting=24575',str(ROOT/'tests/support/state.php')],input=json.dumps(dict(action=action,**data)),text=True,encoding='utf-8',capture_output=True,cwd=ROOT,check=True)
    return json.loads(result.stdout)
def sql(query,params=None,write=False):
    return state('execute' if write else 'query',sql=query,params=params or [])
class Client:
    def __init__(self):
        self.jar=http.cookiejar.CookieJar()
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.token=''
    def request(self,path,data=None,csrf=True,method=None,headers=None,body=None):
        hdr={'X-Requested-With':'XMLHttpRequest'}
        if headers: hdr.update(headers)
        if data is not None:
            body=urllib.parse.urlencode(data).encode()
        if csrf and self.token and (data is not None or body is not None): hdr['X-CSRF-Token']=self.token
        req=urllib.request.Request(BASE+path,data=body,headers=hdr,method=method)
        try: response=self.opener.open(req)
        except urllib.error.HTTPError as error: response=error
        raw=response.read()
        text=raw.decode('utf-8',errors='replace')
        try: content=json.loads(text)
        except ValueError: content=text
        return response.status,content,response.headers
    def page(self):
        status,html,_=self.request('/admin/login/index')
        match=re.search('name="csrf-token" content="([a-f0-9]{64})"',html)
        assert status==200 and match,repr(html[:300])
        self.token=match[1]
    def sid(self):
        return next(c.value for c in self.jar if c.name=='ssid')
    def login(self,username='admin',password=PASSWORD):
        self.page()
        before=self.sid()
        status,cap,_=self.request('/admin/login/captcha',{'type':'LoginCaptcha','token':'test'})
        assert status==200 and cap['code']==1 and 'code' not in cap['data'],cap
        answer=state('captcha',session=self.sid())
        status,result,_=self.request('/admin/login/index',{'username':username,'password':password,'verify':answer,'uniqid':cap['data']['uniqid']})
        assert status==200 and result['code']==1,result
        assert self.sid()!=before,'Session id did not rotate'
        status,html,_=self.request('/admin/index/index')
        assert status==200 and isinstance(html,str) and 'csrf-token' in html,repr(html)[:300]
        self.token=re.search('name="csrf-token" content="([a-f0-9]{64})"',html)[1]
        return result
    def post(self,path,data,expected=1):
        status,result,_=self.request(path,data)
        assert status==200 and isinstance(result,dict) and result['code']==expected,(path,status,str(result)[:500])
        return result

