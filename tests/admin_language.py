"""Real administration language requests against the disposable Starter fixture."""
from http_client import *

state('clear-limits')
c = Client()
c.login()
suffix = uuid.uuid4().hex[:8]


def locale(client, expected, path='/admin/category/index'):
    status, html, headers = client.request(path)
    assert status == 200 and isinstance(html, str), (path, status, str(html)[:400])
    assert headers.get('Content-Language') == expected, (path, headers)
    assert 'no-store' in headers.get('Cache-Control', '')
    assert not any(error in html for error in ['ParseError', 'SQLSTATE[', 'Undefined variable', 'Fatal error:'])
    return html


c.post('/admin/index/locale', {'locale': 'zh-cn'})
html = locale(c, 'zh-cn', '/admin/index/index')
assert html.count('id="admin-language"') == 1 and 'admin/index/languages' in html
assert re.search(r'<button[^>]*data-locale="zh-cn"[^>]*aria-pressed="true"', html)
assert re.search(r'<button[^>]*data-locale="en-us"[^>]*aria-pressed="false"', html)
assert '<select id="admin-language"' not in html
assert 'lang="zh-cn"' in html
assert c.request('/admin/index/locale?locale=en-us')[0] == 405
for bad in ['fr-fr', '../../en-us', 'EN-US', 'en-us-extra']:
    assert c.request('/admin/index/locale', {'locale': bad})[0] == 422
assert c.request('/admin/index/locale', {'locale[]': 'en-us'})[0] == 422
assert c.request('/admin/index/locale', {'locale': 'en-us'}, csrf=False)[0] == 403
locale(c, 'zh-cn')
anonymous = Client()
anonymous.page()
assert anonymous.request('/admin/index/locale', {'locale': 'en-us'})[1]['code'] == 0
print('PASS: one shell selector, POST/login/CSRF guards, invalid and structured locale rejection')

c.post('/admin/index/locale', {'locale': 'en-us'})
shell = locale(c, 'en-us', '/admin/index/index')
assert 'lang="en-us"' in shell and 'data-current="en-us"' in shell and 'Category example' in shell
assert 'Global language settings' in shell
for module in ['category', 'file', 'user', 'config', 'menu']:
    locale(c, 'en-us', '/admin/' + module + '/index')
category = locale(c, 'en-us')
assert 'Add category' in category and 'Categories' in category
for attrs, code in re.findall(r'<script([^>]*)>(.*?)</script>', category, re.S):
    if 'text/html' not in attrs and code.strip():
        result = subprocess.run(['node', '--check', '-'], input=code, encoding='utf-8', capture_output=True)
        assert result.returncode == 0, result.stderr
assert 'Category name' in locale(c, 'en-us', '/admin/category/add')
locale(c, 'en-us', '/admin/category/index?lang=zh-cn')
assert c.request('/admin/category/add', {'title': 'stale_' + suffix, 'pid': 0}, headers={'X-Admin-Language': 'zh-cn'})[0] == 409
assert not sql('SELECT id FROM category WHERE title=?', ['stale_' + suffix])
fresh = Client()
fresh.login()
locale(fresh, 'en-us')
print('PASS: language survives module changes, refresh and a new login; translated example and stale-language write guard')

role = c.post('/admin/auth/add', {'title': 'locale_role_' + suffix, 'status': 1, 'action': 'save', 'nodes[0]': 'admin/category/index'})
rid = sql('SELECT id FROM system_auth WHERE title=?', ['locale_role_' + suffix])[0]['id']
username = 'locale_user_' + suffix
c.post('/admin/user/add', {'username': username, 'nickname': 'Language tester', 'password': PASSWORD, 'authorize': str(rid)})
limited = Client()
limited.login(username)
locale(limited, 'zh-cn')
limited.post('/admin/index/locale', {'locale': 'zh-cn'})
locale(c, 'en-us')
assert 'admin/index/languages' not in locale(limited, 'zh-cn', '/admin/index/index')
assert limited.request('/admin/index/languages')[0] == 403
assert limited.request('/admin/index/languages', {'enabled[]': 'zh-cn', 'default': 'zh-cn'})[0] == 403
print('PASS: personal preferences are isolated and ordinary administrators cannot change global settings')

settings_path = '/admin/index/languages'
for data in [
    {'default': 'zh-cn'},
    {'enabled[]': 'zh-cn', 'default': 'en-us'},
    {'enabled[]': 'fr-fr', 'default': 'fr-fr'},
    {'enabled[0][]': 'zh-cn', 'default': 'zh-cn'},
    {'enabled[]': 'zh-cn', 'default[]': 'zh-cn'},
]:
    assert c.request(settings_path, data)[0] == 422, data
assert c.request(settings_path, {'enabled[]': 'zh-cn', 'default': 'zh-cn'}, csrf=False)[0] == 403
assert 'Enabled languages' in locale(c, 'en-us', settings_path)
c.post(settings_path, {'enabled[]': 'zh-cn', 'default': 'zh-cn'})
locale(c, 'zh-cn')
assert c.request('/admin/index/locale', {'locale': 'en-us'})[0] == 422
shell = locale(c, 'zh-cn', '/admin/index/index')
assert re.search(r'<button[^>]*data-locale="zh-cn"[^>]*disabled', shell)
assert 'data-locale="en-us"' not in shell
assert sql('SELECT value FROM system_data WHERE name=?', ['UserData_10000'])
c.post(settings_path, {'enabled[0]': 'zh-cn', 'enabled[1]': 'en-us', 'default': 'en-us'})
locale(c, 'en-us')  # The original preference was preserved while disabled.
locale(limited, 'zh-cn')  # A chosen language is not replaced by the global default.
new_username = 'locale_new_' + suffix
c.post('/admin/user/add', {'username': new_username, 'nickname': 'New language tester', 'password': PASSWORD, 'authorize': str(rid)})
new_user = Client()
new_user.login(new_username)
locale(new_user, 'en-us')
print('PASS: validated global language persistence, disabled-language fallback and new-user default')

assert c.request('/?lang=zh-cn')[2].get('Content-Language') == 'zh-cn'
locale(c, 'en-us')
assert c.request('/?lang=en-us')[2].get('Content-Language') == 'en-us'
locale(c, 'en-us')
c.post('/admin/index/locale', {'locale': 'zh-cn'})
assert c.request('/')[2].get('Content-Language') == 'en-us'
c.post(settings_path, {'enabled[0]': 'zh-cn', 'enabled[1]': 'en-us', 'default': 'zh-cn'})
locale(c, 'zh-cn')
print('PASS: website preferences remain separate from the administration context')
