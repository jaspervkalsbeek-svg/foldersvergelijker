<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Boodschappenlijstje — Folders Vergelijker</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
.shopping-list-page .container{max-width:700px}
.shopping-list-page .hero{padding-bottom:32px}
.sl-section{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:20px}
.sl-section h2{font-size:1.1rem;font-weight:700;margin-bottom:16px}
.sl-section p.sub{color:var(--text-muted);font-size:.85rem;margin-bottom:16px}
.product-input-row{display:flex;gap:10px;margin-bottom:12px}
.product-input-row input{flex:1;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:11px 14px;color:var(--text);font-size:.92rem;font-family:inherit;outline:none;transition:border-color var(--transition)}
.product-input-row input:focus{border-color:var(--yellow)}
.product-input-row button{background:var(--yellow);color:#000;border:none;border-radius:var(--radius-sm);padding:11px 18px;font-weight:700;cursor:pointer;transition:opacity var(--transition);white-space:nowrap;font-size:.88rem}
.product-input-row button:hover{opacity:.9}
.product-list{list-style:none;margin:0 0 16px}
.product-list li{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:6px;margin:4px 0;background:var(--surface);font-size:.9rem}
.product-list li span{flex:1}
.remove-btn{background:none;border:none;color:#ef5350;cursor:pointer;font-size:1.1rem;padding:2px 6px;border-radius:4px;transition:all var(--transition)}
.remove-btn:hover{background:rgba(239,83,80,.15)}
.sl-email{width:100%;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:11px 14px;color:var(--text);font-size:.92rem;font-family:inherit;outline:none;transition:border-color var(--transition);margin-bottom:16px}
.sl-email:focus{border-color:var(--yellow)}
.send-btn{width:100%;background:var(--yellow);color:#000;border:none;border-radius:var(--radius);padding:14px;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity var(--transition)}
.send-btn:hover{opacity:.9}
.send-btn:disabled{opacity:.4;cursor:not-allowed}
.autocomplete-wrap{position:relative}
.autocomplete-dropdown{position:absolute;top:100%;left:0;right:0;background:var(--surface);border:1px solid var(--border);border-top:none;border-radius:0 0 var(--radius-sm) var(--radius-sm);z-index:10;max-height:200px;overflow-y:auto;display:none}
.autocomplete-dropdown div{padding:10px 14px;cursor:pointer;font-size:.88rem;transition:background var(--transition)}
.autocomplete-dropdown div:hover{background:var(--card-hover);color:var(--yellow)}
.result-box{margin-top:20px;padding:16px;border-radius:var(--radius);display:none}
.result-box.success{background:rgba(129,199,132,.1);border:1px solid rgba(129,199,132,.3);display:block}
.result-box.error{background:rgba(239,83,80,.1);border:1px solid rgba(239,83,80,.3);display:block}
.result-box h3{font-size:1rem;margin-bottom:6px}
.result-box.success h3{color:#81c784}
.result-box.error h3{color:#ef5350}
.result-box p{color:var(--text-dim);font-size:.88rem}
.spinner{display:none;text-align:center;padding:20px}
.spinner::after{content:'';display:inline-block;width:28px;height:28px;border:3px solid var(--border);border-top-color:var(--yellow);border-radius:50%;animation:spin .6s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<header class="header">
    <div class="container">
        <a href="index.php" class="logo">Folders<span>Vergelijker</span></a>
        <nav class="nav">
            <a href="index.php">Home</a>
            <a href="stores.php">Winkels</a>
            <a href="shopping-list.php" class="active">Boodschappenlijstje</a>
        </nav>
    </div>
</header>

<div class="container shopping-list-page">
  <div class="hero">
    <h1>Boodschappenlijstje</h1>
    <p>Voeg producten toe, vul je email in en ontvang een overzicht van de beste aanbiedingen</p>
  </div>

  <div class="sl-section">
    <h2>Producten toevoegen</h2>
    <p class="sub">Typ productnamen één voor één of plak een lijst. Voeg zoveel toe als je wilt.</p>

    <div class="product-input-row autocomplete-wrap">
      <input type="text" id="productInput" placeholder="Bijv. Halfvolle melk" autocomplete="off">
      <button id="addBtn">Toevoegen</button>
    </div>

    <ul id="productList" class="product-list"></ul>
  </div>

  <div class="sl-section">
    <h2>Email adres</h2>
    <p class="sub">Vul je email in om het overzicht te ontvangen</p>
    <input type="email" id="emailInput" class="sl-email" placeholder="jouw@email.nl">
    <button id="sendBtn" class="send-btn" disabled>📧 Verstuur overzicht</button>
    <div class="spinner" id="spinner"></div>
    <div id="resultBox" class="result-box"></div>
  </div>
</div>

<footer class="footer">
    <div class="container">
        <p>Folders Vergelijker – Vergelijk prijzen uit Nederlandse en Duitse supermarkten</p>
    </div>
</footer>

<script>
const productInput = document.getElementById('productInput');
const addBtn = document.getElementById('addBtn');
const productList = document.getElementById('productList');
const emailInput = document.getElementById('emailInput');
const sendBtn = document.getElementById('sendBtn');
const spinner = document.getElementById('spinner');
const resultBox = document.getElementById('resultBox');

let products = [];
let autocompleteTimer = null;

function addProduct(name) {
  name = name.trim();
  if (!name || products.includes(name)) return;
  products.push(name);
  renderList();
  productInput.value = '';
  updateSendBtn();
}

function removeProduct(name) {
  products = products.filter(p => p !== name);
  renderList();
  updateSendBtn();
}

function renderList() {
  if (products.length === 0) {
    productList.innerHTML = '<li style="color:var(--text-muted);font-size:.85rem;text-align:center;padding:12px;background:none">Nog geen producten toegevoegd</li>';
    return;
  }
  productList.innerHTML = products.map(p =>
    `<li><span>${escapeHtml(p)}</span><button class="remove-btn" onclick="removeProduct('${escapeHtml(p)}')">✕</button></li>`
  ).join('');
}

function updateSendBtn() {
  sendBtn.disabled = products.length === 0 || !emailInput.value.trim();
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

addBtn.addEventListener('click', () => addProduct(productInput.value));
productInput.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); addProduct(productInput.value); }
});

emailInput.addEventListener('input', updateSendBtn);

// Autocomplete
productInput.addEventListener('input', () => {
  clearTimeout(autocompleteTimer);
  const val = productInput.value.trim();
  if (val.length < 2) {
    document.getElementById('autocompleteDropdown')?.remove();
    return;
  }
  autocompleteTimer = setTimeout(async () => {
    try {
      const res = await fetch('shopping-list-search.php?q=' + encodeURIComponent(val));
      const data = await res.json();
      showAutocomplete(data);
    } catch (e) {}
  }, 250);
});

productInput.addEventListener('blur', () => {
  setTimeout(() => document.getElementById('autocompleteDropdown')?.remove(), 200);
});

productInput.addEventListener('focus', () => {
  if (productInput.value.trim().length >= 2) {
    productInput.dispatchEvent(new Event('input'));
  }
});

function showAutocomplete(suggestions) {
  let dd = document.getElementById('autocompleteDropdown');
  if (!dd) {
    dd = document.createElement('div');
    dd.id = 'autocompleteDropdown';
    dd.className = 'autocomplete-dropdown';
    productInput.parentElement.appendChild(dd);
  }
  if (suggestions.length === 0) { dd.style.display = 'none'; return; }
  dd.style.display = 'block';
  dd.innerHTML = suggestions.map(s =>
    `<div onmousedown="event.preventDefault(); addProduct('${escapeHtml(s)}'); this.parentElement.style.display='none'">${escapeHtml(s)}</div>`
  ).join('');
}

// Send
sendBtn.addEventListener('click', async () => {
  sendBtn.disabled = true;
  spinner.style.display = 'block';
  resultBox.style.display = 'none';
  resultBox.className = 'result-box';

  try {
    const body = new URLSearchParams();
    body.set('items', products.join('\n'));
    body.set('email', emailInput.value.trim());

    const res = await fetch('shopping-list-send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    });
    const json = await res.json();

    if (json.success) {
      resultBox.className = 'result-box success';
      resultBox.innerHTML = `<h3>✅ Verzonden!</h3><p>${json.found} van de ${json.total} producten gevonden. Check je email!</p>`;
      products = [];
      renderList();
      emailInput.value = '';
      updateSendBtn();
    } else {
      resultBox.className = 'result-box error';
      resultBox.innerHTML = `<h3>❌ Fout</h3><p>${escapeHtml(json.error)}</p>`;
      sendBtn.disabled = false;
    }
  } catch (e) {
    resultBox.className = 'result-box error';
    resultBox.innerHTML = `<h3>❌ Fout</h3><p>Kon verbinding niet maken</p>`;
    sendBtn.disabled = false;
  }

  spinner.style.display = 'none';
});
</script>

</body>
</html>
