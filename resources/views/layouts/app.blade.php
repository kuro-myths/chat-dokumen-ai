<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'SkripsiAI')</title>
<style>
:root{
  --bg:#0b1020; --card:#141b31; --card2:#0e1528; --line:#24304f;
  --text:#e8ecf8; --muted:#8d97b8; --accent:#6d5efc; --accent2:#22d3ee;
  --ok:#34d399; --err:#f87171; --radius:16px;
}
*{box-sizing:border-box}
body{margin:0;font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:
  radial-gradient(1100px 600px at 85% -10%,rgba(109,94,252,.28),transparent),
  radial-gradient(900px 500px at -10% 110%,rgba(34,211,238,.12),transparent),
  var(--bg);color:var(--text);min-height:100vh}
.wrap{max-width:1100px;margin:0 auto;padding:28px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:24px}
h1,h2,h3{margin:0 0 8px;line-height:1.25}
h2{font-size:1.15rem}
.muted{color:var(--muted);font-size:.9rem;margin:0 0 12px}
code{background:var(--card2);border:1px solid var(--line);border-radius:6px;padding:1px 7px;font-size:.85em}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:26px}
.brand{display:flex;align-items:center;gap:12px;font-weight:700;font-size:1.25rem}
.logo{width:40px;height:40px;border-radius:11px;background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex:none}
.btn{display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;font-weight:600;
  border-radius:12px;padding:11px 20px;color:#fff;background:linear-gradient(135deg,var(--accent),#8b5cf6);
  transition:opacity .15s;font-size:.95rem}
.btn:hover{opacity:.87}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--text)}
.btn.small{padding:6px 13px;font-size:.8rem;border-radius:9px}
.btn:disabled{opacity:.5;cursor:wait}
input,select,textarea{width:100%;background:var(--card2);border:1px solid var(--line);color:var(--text);
  border-radius:12px;padding:12px 14px;font-size:.95rem;outline:none;font-family:inherit}
input:focus,select:focus,textarea:focus{border-color:var(--accent)}
label{display:block;margin:12px 0 6px;font-size:.85rem;color:var(--muted)}
.chip{display:inline-block;background:var(--card2);border:1px solid var(--line);border-radius:999px;
  padding:4px 13px;font-size:.75rem;margin:3px 3px 3px 0}
.chip.err{border-color:rgba(248,113,113,.5);color:var(--err)}
.row{display:flex;gap:16px;flex-wrap:wrap}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px}
.stat{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:18px 20px}
.stat b{display:block;font-size:1.9rem;margin-top:4px;background:linear-gradient(135deg,#a5b4fc,var(--accent2));
  -webkit-background-clip:text;background-clip:text;color:transparent}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-top:14px}
.prov{background:var(--card2);border:1px solid var(--line);border-radius:14px;padding:16px}
.prov-head{display:flex;align-items:center;gap:9px;font-size:1rem;margin-bottom:4px;flex-wrap:wrap}
.prov-dot{width:9px;height:9px;border-radius:50%;flex:none;background:var(--err)}
.prov-dot.on{background:var(--ok);box-shadow:0 0 8px rgba(52,211,153,.7)}
.model-list{min-height:22px;margin:10px 0}
.prov-actions{display:flex;gap:8px;flex-wrap:wrap}
.login-wrap{min-height:92vh;display:flex;align-items:center;justify-content:center;padding:24px}
.login-card{width:100%;max-width:400px;text-align:center}
.login-card .logo{margin:0 auto 14px;width:56px;height:56px;font-size:1.6rem;border-radius:14px}
.login-card input{text-align:center;font-size:1.05rem;letter-spacing:.12em}
.alert{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.4);color:var(--err);
  border-radius:10px;padding:10px 14px;font-size:.85rem;margin:12px 0}
.chat-log{background:var(--card2);border:1px solid var(--line);border-radius:12px;padding:14px;
  min-height:180px;max-height:320px;overflow-y:auto;margin:10px 0;display:flex;flex-direction:column;gap:10px}
.msg{max-width:82%;padding:10px 14px;border-radius:13px;font-size:.9rem;white-space:pre-wrap;word-wrap:break-word}
.msg.user{align-self:flex-end;background:linear-gradient(135deg,var(--accent),#8b5cf6);border-bottom-right-radius:4px}
.msg.ai{align-self:flex-start;background:#1a2340;border:1px solid var(--line);border-bottom-left-radius:4px}
.hint{font-size:.78rem;color:var(--muted);margin-top:10px}
</style>
</head>
<body>
@yield('content')
</body>
</html>
