import urllib.request, urllib.parse, re, http.cookiejar

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# 1. GET /login
req1 = urllib.request.Request('http://127.0.0.1:8004/login', headers={'User-Agent': 'PostmanRuntime/7.43.0'})
resp1 = opener.open(req1)
html = resp1.read().decode('utf-8')
csrf_match = re.search(r'<meta\s+name=[\"\']csrf-token[\"\']\s+content=[\"\']([^\"\']+)[\"\']', html)
csrf = csrf_match.group(1) if csrf_match else None
print('Status GET /login:', resp1.status)
print('CSRF token found:', csrf)

# 2. POST /login
data = urllib.parse.urlencode({'email': 'admin@cberp.local', 'password': 'Admin@1234', '_token': csrf}).encode('utf-8')
req2 = urllib.request.Request('http://127.0.0.1:8004/login', data=data, headers={
    'User-Agent': 'PostmanRuntime/7.43.0',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrf or ''
})
try:
    resp2 = opener.open(req2)
    print('Status POST /login:', resp2.status)
    print('Response POST /login:', resp2.read().decode('utf-8'))
except urllib.error.HTTPError as e:
    print('Error POST /login:', e.code, e.read().decode('utf-8'))
