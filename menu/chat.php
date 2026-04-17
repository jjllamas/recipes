<?php
require_once __DIR__ . '/../includes/session.php';
$pageTitle = 'Menu Assistant';
$extraHead = '
<style>
    .chat-wrapper {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 180px);
        min-height: 400px;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px 8px 0 0;
    }
    .msg { margin-bottom: 14px; display: flex; gap: 10px; }
    .msg.user  { flex-direction: row-reverse; }
    .msg-bubble {
        max-width: 75%;
        padding: 10px 14px;
        border-radius: 16px;
        font-size: .9rem;
        line-height: 1.5;
        white-space: pre-wrap;
    }
    .msg.user  .msg-bubble { background: #0d6efd; color: #fff; border-bottom-right-radius: 4px; }
    .msg.assistant .msg-bubble { background: #fff; border: 1px solid #dee2e6; border-bottom-left-radius: 4px; }
    .msg-avatar { width: 32px; height: 32px; border-radius: 50%; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; background:#e9ecef; }
    .chat-input-bar {
        display: flex;
        gap: 8px;
        padding: 10px;
        background: #fff;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 8px 8px;
    }
    .menu-proposal-card { background:#fff; border:1px solid #dee2e6; border-radius:12px; padding:16px; margin-top:8px; }
    .menu-proposal-card table { font-size: .8rem; }
    .menu-proposal-card th { white-space: nowrap; }
    .typing-indicator span {
        display:inline-block; width:7px; height:7px; margin:0 2px;
        background:#adb5bd; border-radius:50%;
        animation: blink 1.2s infinite;
    }
    .typing-indicator span:nth-child(2) { animation-delay:.2s; }
    .typing-indicator span:nth-child(3) { animation-delay:.4s; }
    @keyframes blink { 0%,80%,100%{opacity:.2} 40%{opacity:1} }
</style>';
include __DIR__ . '/../includes/header.php';

$current_week = (int)date('W');
$current_year = (int)date('Y');
?>

<div class="row justify-content-center">
<div class="col-xl-10">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">🤖 Menu Assistant</h2>
    <button class="btn btn-outline-secondary btn-sm" id="clearBtn">
        <i class="bi bi-trash"></i> New conversation
    </button>
</div>

<div class="chat-wrapper">
    <div class="chat-messages" id="chatMessages">
        <div class="msg assistant">
            <div class="msg-avatar">🤖</div>
            <div class="msg-bubble">
                ¡Hola! Soy tu asistente de planificación de menús. Tengo acceso a todas tus recetas.<br><br>
                Cuéntame qué tienes disponible en la despensa, nevera o congelador y te preparo el menú semanal. También puedes indicarme preferencias, restricciones o el número de personas.
            </div>
        </div>
    </div>

    <div class="chat-input-bar">
        <textarea id="userInput" class="form-control" rows="1"
                  placeholder="Tengo pollo, verduras y huevos en la nevera..."
                  style="resize:none; overflow:hidden;"></textarea>
        <button class="btn btn-primary px-3" id="sendBtn">
            <i class="bi bi-send"></i>
        </button>
    </div>
    <div class="px-2 pt-2 pb-1">
        <button class="btn btn-outline-secondary btn-sm w-100 text-start text-truncate" id="exampleBtn" title="Usar prompt de ejemplo y editarlo antes de enviar">
            <i class="bi bi-lightbulb"></i> Ejemplo: menú semanal familiar — haz clic para editar antes de enviar
        </button>
    </div>
</div>

<!-- Apply menu modal -->
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply menu to week</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Week</label>
                    <input type="number" id="applyWeek" class="form-control" min="1" max="53" value="<?= $current_week ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Year</label>
                    <input type="number" id="applyYear" class="form-control" value="<?= $current_year ?>">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="applyOverwrite" checked>
                    <label class="form-check-label" for="applyOverwrite">Replace existing entries for that week</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApplyBtn">
                    <i class="bi bi-calendar-check"></i> Apply
                </button>
            </div>
        </div>
    </div>
</div>

</div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
const userInput    = document.getElementById('userInput');
const sendBtn      = document.getElementById('sendBtn');
const clearBtn     = document.getElementById('clearBtn');
const exampleBtn   = document.getElementById('exampleBtn');
let pendingMenu    = null;

const EXAMPLE_PROMPT = `Somos una familia de 6: una pareja y dos niños de 7 y 9 años.

Esto es lo que tengo disponible esta semana:
- [escribe aquí lo que tienes en nevera, despensa y congelador]

Preferencias y restricciones:
- Sin pasta normal (integral o legumbres como sustituto sí)
- Poco arroz
- Priorizar proteínas (carne, pescado, huevos, legumbres) y verduras
- Snacks de media mañana aptos para llevar al cole (para los niños)
- Menús saludables y variados, sin repetir platos dentro de lo posible
- Los fines de semana y cuando encaje, preferir recetas de olla lenta

Por favor, propón el menú completo de la semana sin hacerme preguntas adicionales. Usa únicamente los nombres exactos de las recetas de mi biblioteca, sin inventar ni parafrasear ninguna.`;

exampleBtn.addEventListener('click', function() {
    userInput.value = EXAMPLE_PROMPT;
    userInput.style.height = 'auto';
    userInput.style.height = Math.min(userInput.scrollHeight, 120) + 'px';
    userInput.focus();
    userInput.setSelectionRange(0, 0);
});

// Auto-resize textarea
userInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Send on Enter (Shift+Enter for newline)
userInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

sendBtn.addEventListener('click', sendMessage);
clearBtn.addEventListener('click', clearConversation);

function sendMessage() {
    const text = userInput.value.trim();
    if (!text) return;

    appendMessage('user', text);
    userInput.value = '';
    userInput.style.height = 'auto';
    sendBtn.disabled = true;

    const typingId = appendTyping();

    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('message', text);

    fetch('<?= BASE_URL ?>/api/chat_message.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            removeTyping(typingId);
            sendBtn.disabled = false;

            if (data.error) {
                appendMessage('assistant', '⚠️ ' + data.error);
                return;
            }

            if (data.reply) appendMessage('assistant', data.reply);

            if (data.menu_proposal) {
                pendingMenu = data.menu_proposal;
                appendMenuProposal(data.menu_proposal);
            }
        })
        .catch(() => {
            removeTyping(typingId);
            sendBtn.disabled = false;
            appendMessage('assistant', '⚠️ Connection error. Please try again.');
        });
}

function appendMessage(role, text) {
    const div = document.createElement('div');
    div.className = `msg ${role}`;
    div.innerHTML = `
        <div class="msg-avatar">${role === 'user' ? '👤' : '🤖'}</div>
        <div class="msg-bubble">${escapeHtml(text)}</div>`;
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function appendMenuProposal(proposal) {
    const days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const meals = ['Breakfast','Second Breakfast','Lunch','Afternoon Snack','Dinner'];
    const dayLabels = {'Monday':'Mon','Tuesday':'Tue','Wednesday':'Wed','Thursday':'Thu','Friday':'Fri','Saturday':'Sat','Sunday':'Sun'};

    let tableHtml = `<table class="table table-bordered table-sm mb-0"><thead><tr><th></th>`;
    days.forEach(d => tableHtml += `<th>${dayLabels[d]}</th>`);
    tableHtml += '</tr></thead><tbody>';
    meals.forEach(meal => {
        tableHtml += `<tr><th class="text-nowrap">${meal}</th>`;
        days.forEach(day => {
            let recipes = proposal.menu?.[day]?.[meal] ?? [];
            if (!Array.isArray(recipes)) recipes = recipes ? [recipes] : [];
            tableHtml += `<td>${recipes.map(r => escapeHtml(r)).join('<br>') || '<span class="text-muted">—</span>'}</td>`;
        });
        tableHtml += '</tr>';
    });
    tableHtml += '</tbody></table>';

    const div = document.createElement('div');
    div.className = 'msg assistant';
    div.innerHTML = `
        <div class="msg-avatar">🤖</div>
        <div style="max-width:90%">
            <div class="menu-proposal-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>📅 Proposed menu — Week ${proposal.week}</strong>
                    <button class="btn btn-success btn-sm" onclick="openApplyModal()">
                        <i class="bi bi-calendar-check"></i> Apply to planner
                    </button>
                </div>
                ${proposal.summary ? `<p class="text-muted small mb-3">${escapeHtml(proposal.summary)}</p>` : ''}
                <div class="table-responsive">${tableHtml}</div>
            </div>
        </div>`;
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function openApplyModal() {
    if (!pendingMenu) return;
    document.getElementById('applyWeek').value = pendingMenu.week || <?= $current_week ?>;
    document.getElementById('applyYear').value = pendingMenu.year || <?= $current_year ?>;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('applyModal')).show();
}

document.getElementById('confirmApplyBtn').addEventListener('click', function() {
    if (!pendingMenu) return;
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Applying…';

    const fd = new FormData();
    fd.append('action', 'apply');
    fd.append('menu_json', JSON.stringify(pendingMenu.menu));
    fd.append('week', document.getElementById('applyWeek').value);
    fd.append('year', document.getElementById('applyYear').value);
    fd.append('overwrite', document.getElementById('applyOverwrite').checked ? '1' : '');

    fetch('<?= BASE_URL ?>/api/chat_message.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('applyModal')).hide();
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-calendar-check"></i> Apply to planner';
            if (data.error) {
                showToast('Error: ' + data.error, 'error');
            } else {
                const nf = data.not_found?.length ? ` (${data.not_found.length} not found)` : '';
                showToast(`✅ ${data.added} recipes added to week ${data.week}${nf}`);
                appendMessage('assistant', `✅ Menu applied to week ${data.week}! ${data.added} entries added.${data.not_found?.length ? '\n⚠️ Not found: ' + data.not_found.join(', ') : ''}`);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-calendar-check"></i> Apply to planner';
            showToast('Connection error', 'error');
        });
});

function clearConversation() {
    const fd = new FormData();
    fd.append('action', 'clear');
    fetch('<?= BASE_URL ?>/api/chat_message.php', { method: 'POST', body: fd });
    chatMessages.innerHTML = `
        <div class="msg assistant">
            <div class="msg-avatar">🤖</div>
            <div class="msg-bubble">¡Hola! Soy tu asistente de planificación de menús. Cuéntame qué tienes disponible y te preparo el menú semanal.</div>
        </div>`;
    pendingMenu = null;
}

function appendTyping() {
    const id  = 'typing-' + Date.now();
    const div = document.createElement('div');
    div.className = 'msg assistant';
    div.id = id;
    div.innerHTML = `<div class="msg-avatar">🤖</div><div class="msg-bubble"><div class="typing-indicator"><span></span><span></span><span></span></div></div>`;
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return id;
}

function removeTyping(id) {
    document.getElementById(id)?.remove();
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
