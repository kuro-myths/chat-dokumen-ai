<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Kuro AI')</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230d1226'/%3E%3Cpath d='M14 20c0-4 3-7 7-7h22c4 0 7 3 7 7v13c0 4-3 7-7 7H30l-8 8v-8h-1c-4 0-7-3-7-7z' fill='%2322d3ee'/%3E%3Ctext x='32' y='34' font-family='Arial' font-size='17' font-weight='bold' fill='%230d1226' text-anchor='middle'%3EK%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#070b18; --card:rgba(19,26,49,.72); --card2:rgba(10,15,32,.8);
  --line:rgba(88,106,170,.22); --text:#eef1fb; --muted:#8d97b8;
  --accent:#6d5efc; --accent2:#22d3ee; --ok:#34d399; --warn:#fbbf24; --err:#f87171;
  --radius:18px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;background:
  radial-gradient(1100px 600px at 85% -10%,rgba(109,94,252,.30),transparent),
  radial-gradient(900px 520px at -10% 110%,rgba(34,211,238,.13),transparent),
  var(--bg);color:var(--text);min-height:100vh}
::-webkit-scrollbar{width:9px;height:9px}
::-webkit-scrollbar-thumb{background:#2b3a6b;border-radius:8px}
::-webkit-scrollbar-track{background:transparent}
.wrap{max-width:1180px;margin:0 auto;padding:28px 24px 120px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
  padding:26px;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
h1,h2,h3{margin:0 0 8px;line-height:1.25;letter-spacing:-.02em}
h2{font-size:1.18rem}
.muted{color:var(--muted);font-size:.9rem;margin:0 0 12px}
code{background:var(--card2);border:1px solid var(--line);border-radius:7px;padding:1px 8px;font-size:.85em}
a{color:var(--accent2);text-decoration:none}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:28px}
.brand{display:flex;align-items:center;gap:12px;font-weight:800;font-size:1.3rem;letter-spacing:-.03em}
.brand .sub{font-weight:500;font-size:.72rem;color:var(--muted);display:block;letter-spacing:.08em;text-transform:uppercase}
.logo{width:44px;height:44px;border-radius:13px;flex:none;
  background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;
  box-shadow:0 6px 24px rgba(109,94,252,.45)}
.logo svg{display:block}
.navbtns{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;font-weight:600;font-family:inherit;
  border-radius:13px;padding:11px 20px;color:#fff;background:linear-gradient(135deg,var(--accent),#8b5cf6);
  transition:transform .12s,opacity .15s,box-shadow .15s;font-size:.92rem}
.btn:hover{opacity:.9;transform:translateY(-1px)}
.btn:active{transform:translateY(0)}
.btn.ghost{background:var(--card2);border:1px solid var(--line);color:var(--text)}
.btn.small{padding:7px 14px;font-size:.78rem;border-radius:10px}
.btn.icon{padding:10px;border-radius:12px}
.btn:disabled{opacity:.5;cursor:wait;transform:none}
.btn svg{flex:none}
input,select,textarea{width:100%;background:var(--card2);border:1px solid var(--line);color:var(--text);
  border-radius:13px;padding:12px 14px;font-size:.93rem;outline:none;font-family:inherit;transition:border-color .15s}
input:focus,select:focus,textarea:focus{border-color:var(--accent)}
label{display:block;margin:12px 0 6px;font-size:.82rem;color:var(--muted);font-weight:600}
.chip{display:inline-block;background:var(--card2);border:1px solid var(--line);border-radius:999px;
  padding:4px 13px;font-size:.75rem;margin:3px 3px 3px 0}
.chip.err{border-color:rgba(248,113,113,.5);color:var(--err)}
.badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:3px 11px;font-size:.7rem;font-weight:600}
.badge.uploaded{background:rgba(141,151,184,.15);color:var(--muted)}
.badge.reviewing{background:rgba(251,191,36,.15);color:var(--warn);animation:pulse 1.4s infinite}
.badge.reviewed{background:rgba(52,211,153,.15);color:var(--ok)}
.badge.review_failed,.badge.drafting{background:rgba(248,113,113,.15);color:var(--err)}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.45}}
.row{display:flex;gap:16px;flex-wrap:wrap}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:22px}
.stat{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:18px 20px;backdrop-filter:blur(14px)}
.stat b{display:block;font-size:1.85rem;margin-top:4px;
  background:linear-gradient(135deg,#a5b4fc,var(--accent2));-webkit-background-clip:text;background-clip:text;color:transparent}
.features{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:22px}
.feature{position:relative;overflow:hidden;background:var(--card);border:1px solid var(--line);
  border-radius:var(--radius);padding:22px;backdrop-filter:blur(14px);transition:transform .15s,border-color .15s;cursor:pointer}
.feature:hover{transform:translateY(-3px);border-color:rgba(109,94,252,.5)}
.feature .ficon{font-size:1.7rem;margin-bottom:10px;display:inline-block}
.feature b{display:block;font-size:1rem;margin-bottom:4px}
.feature p{margin:0;color:var(--muted);font-size:.84rem;line-height:1.5}
.feature .glow{position:absolute;width:130px;height:130px;border-radius:50%;filter:blur(50px);opacity:.35;top:-40px;right:-40px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:14px;margin-top:14px}
.prov{background:var(--card2);border:1px solid var(--line);border-radius:15px;padding:17px}
.prov-head{display:flex;align-items:center;gap:9px;font-size:1rem;margin-bottom:4px;flex-wrap:wrap}
.prov-dot{width:9px;height:9px;border-radius:50%;flex:none;background:var(--err)}
.prov-dot.on{background:var(--ok);box-shadow:0 0 9px rgba(52,211,153,.8)}
.model-list{min-height:22px;margin:10px 0}
.prov-actions{display:flex;gap:8px;flex-wrap:wrap}
.chat-log{background:var(--card2);border:1px solid var(--line);border-radius:15px;padding:16px;
  min-height:190px;max-height:340px;overflow-y:auto;margin:10px 0;display:flex;flex-direction:column;gap:10px}
.msg{max-width:82%;padding:10px 15px;border-radius:15px;font-size:.89rem;white-space:pre-wrap;word-wrap:break-word;line-height:1.5}
.msg.user{align-self:flex-end;background:linear-gradient(135deg,var(--accent),#8b5cf6);border-bottom-right-radius:5px}
.msg.ai{align-self:flex-start;background:#171f3d;border:1px solid var(--line);border-bottom-left-radius:5px}
.chat-tools{display:flex;gap:8px;margin-top:6px;align-items:center;flex-wrap:wrap}
.hint{font-size:.78rem;color:var(--muted);margin-top:10px}
.alert{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.4);color:var(--err);
  border-radius:11px;padding:10px 14px;font-size:.85rem;margin:12px 0}
.dropzone{border:2px dashed var(--line);border-radius:15px;padding:26px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s}
.dropzone:hover,.dropzone.drag{border-color:var(--accent);background:rgba(109,94,252,.08)}
.dropzone p{margin:4px 0;color:var(--muted);font-size:.85rem}
.doc-item{display:flex;align-items:center;gap:12px;background:var(--card2);border:1px solid var(--line);
  border-radius:13px;padding:13px 16px;margin-top:10px;flex-wrap:wrap}
.doc-item b{font-size:.93rem}
.doc-item .grow{flex:1;min-width:200px}
.doc-actions{display:flex;gap:8px;flex-wrap:wrap}
.hl-item{background:var(--card2);border:1px solid var(--line);border-left-width:4px;border-radius:11px;
  padding:11px 14px;margin-top:8px;font-size:.84rem}
.hl-item .q{font-weight:600}
.hl-item .c{color:var(--muted);margin-top:3px}
.c-red{border-left-color:#f87171}.c-yellow{border-left-color:#fbbf24}.c-blue{border-left-color:#38bdf8}
.c-purple{border-left-color:#a78bfa}.c-green{border-left-color:#34d399}
.login-wrap{min-height:94vh;display:flex;align-items:center;justify-content:center;padding:24px}
.login-card{width:100%;max-width:410px;text-align:center}
.login-card .logo{margin:0 auto 16px;width:62px;height:62px;font-size:1.5rem;border-radius:17px}
.login-card input{text-align:center;letter-spacing:.12em;font-size:1.02rem}
/* ===== Maskot Kuro ===== */
.kuro-wrap{position:fixed;right:26px;bottom:26px;z-index:50;width:110px;text-align:center;pointer-events:none}
.kuro-wrap.static{position:static;margin:0 auto 10px}
.kuro-bubble{background:#171f3d;border:1px solid var(--line);border-radius:13px 13px 13px 3px;
  padding:8px 12px;font-size:.74rem;color:var(--text);margin-bottom:9px;display:inline-block;
  box-shadow:0 8px 26px rgba(3,7,18,.6);max-width:190px;line-height:1.4}
.kuro-svg{display:block;margin:0 auto;animation:kuroFloat 3.2s ease-in-out infinite;
  filter:drop-shadow(0 14px 26px rgba(34,211,238,.18))}
@keyframes kuroFloat{0%,100%{transform:translateY(0) rotate(-1.5deg)}50%{transform:translateY(-12px) rotate(1.5deg)}}
.kuro-scan{width:104px;height:104px;margin:-108px auto 0;border-radius:50%;
  border:2px dashed rgba(34,211,238,.55);opacity:0;transition:opacity .3s;pointer-events:none}
.kuro-wrap.thinking .kuro-scan{opacity:1;animation:kuroScan 2.2s linear infinite}
@keyframes kuroScan{to{transform:rotate(360deg)}}
.kuro-eye{animation:kuroBlink 4.5s infinite}
@keyframes kuroBlink{0%,94%,100%{transform:scaleY(1)}96%{transform:scaleY(.12)}}
.kuro-wrap.thinking .kuro-eye{animation:kuroBlink 1.1s infinite}
/* ===== Modal history ===== */
.modal{position:fixed;inset:0;background:rgba(4,7,16,.7);backdrop-filter:blur(6px);z-index:100;
  display:none;align-items:center;justify-content:center;padding:24px}
.modal.open{display:flex}
.modal-panel{background:#10173a;border:1px solid var(--line);border-radius:20px;width:100%;max-width:560px;
  max-height:80vh;display:flex;flex-direction:column;box-shadow:0 30px 80px rgba(0,0,0,.55)}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--line)}
.modal-head h3{margin:0;font-size:1.05rem}
.modal-body{padding:18px 22px;overflow-y:auto}
.hist-item{display:flex;align-items:center;gap:12px;background:var(--card2);border:1px solid var(--line);
  border-radius:13px;padding:12px 15px;margin-bottom:10px}
.hist-item .grow{flex:1;min-width:0}
.hist-item b{display:block;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hist-item small{color:var(--muted);font-size:.73rem}
.empty{color:var(--muted);font-size:.85rem;text-align:center;padding:20px}
</style>
</head>
<body>
@yield('content')
</body>
</html>
