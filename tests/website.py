"""Verify public homepage localization without administration authentication."""
from http_client import Client,BASE
import urllib.request

c=Client()
def page(path,language,title):
    status,html,headers=c.request(path)
    assert status==200 and isinstance(html,str),(path,status,str(html)[:300])
    assert headers.get('Content-Language')==language,(path,headers)
    assert f'lang="{"en-US" if language=="en-us" else "zh-CN"}"' in html
    assert title in html and 'website_heading' not in html,html[:500]
    assert '?lang=zh-cn' in html and '?lang=en-us' in html
    assert 'private' in headers.get('Cache-Control','')
    return html
page('/','zh-cn','网站已就绪。')
page('/?lang=en-us','en-us','Your website is ready.')
page('/','en-us','Your website is ready.')
page('/index/index/index.html','en-us','Your website is ready.')
page('/?lang=zh-cn','zh-cn','网站已就绪。')
page('/','zh-cn','网站已就绪。')
for invalid in ['fr-fr','../../en-us','en-us%22%3E%3Cscript%3E','en-us-extra']:
    page('/?lang='+invalid,'zh-cn','网站已就绪。')
page('/?lang%5B%5D=en-us','zh-cn','网站已就绪。')
page('/?lang=EN-US','en-us','Your website is ready.')
_,login,_=c.request('/admin/login/index')
assert '系统登录' in login and 'Your website is ready.' not in login
assert any(cookie.name=='site_lang' and cookie.value=='en-us' for cookie in c.jar)
with urllib.request.urlopen(BASE+'/static/site/index.css') as response:
    assert response.status==200 and 'text/css' in response.headers['Content-Type']
    assert len(response.read())>1000
print('PASS: public index, Chinese/English title/content/html lang, language links, persistent cookie, invalid/array input fallback, administration language isolation and local CSS')
