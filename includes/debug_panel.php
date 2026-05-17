<?php
if (!function_exists('isDebugPanelEnabled')) {
    require_once __DIR__ . '/../config/settings.php';
}
if (defined('APP_DEBUG') && APP_DEBUG && isDebugPanelEnabled()):
    $debugLogStreamUrl = (defined('API_URL') ? rtrim(API_URL, '/') : '') . '/debug_log_stream.php';
?>
<div id="debug-panel" style="display:none">
  <div id="debug-header">
    <div style="display:flex;gap:0;flex:1;min-width:0">
      <button type="button" class="debug-tab active" data-tab="frontend">🖥️ Frontend</button>
      <button type="button" class="debug-tab" data-tab="server">🖧 Server</button>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
      <button type="button" id="debug-clear" title="Vider">🗑️</button>
      <button type="button" id="debug-copy" title="Copier">📋</button>
      <button type="button" id="debug-close">✕</button>
    </div>
  </div>
  <div id="debug-log" data-tab-content="frontend"></div>
  <div id="server-log" data-tab-content="server" style="display:none"></div>
</div>

<button type="button" id="debug-toggle" title="Ouvrir le tracelog debug">🐛 Debug</button>

<style>
#debug-toggle {
  position: fixed;
  bottom: 16px;
  right: 16px;
  z-index: 9998;
  background: var(--surface-alt, #1e2130);
  border: 1px solid var(--border, #333);
  color: var(--text-secondary, #aaa);
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 0.75rem;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.2s;
}
#debug-toggle:hover { opacity: 1; }

#debug-panel {
  position: fixed;
  bottom: 52px;
  right: 16px;
  width: 480px;
  max-width: calc(100vw - 32px);
  max-height: 420px;
  z-index: 9997;
  background: #0d0f1a;
  border: 1px solid #2a2d3e;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-size: 0.72rem;
  box-shadow: 0 8px 32px rgba(0,0,0,0.5);
  overflow: hidden;
}

#debug-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 8px 0 0;
  background: #161825;
  border-bottom: 1px solid #2a2d3e;
  color: #7c83a0;
  font-size: 0.75rem;
  font-weight: 600;
  flex-shrink: 0;
}
#debug-header button {
  background: none;
  border: none;
  cursor: pointer;
  color: #7c83a0;
  font-size: 0.8rem;
  padding: 2px 4px;
  border-radius: 4px;
  transition: background 0.15s;
}
#debug-header button:hover { background: #2a2d3e; }

.debug-tab {
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #7c83a0;
  cursor: pointer;
  padding: 8px 14px;
  font-size: 0.72rem;
  font-weight: 600;
  transition: all 0.15s;
}
.debug-tab.active {
  color: var(--primary, #f97316);
  border-bottom-color: var(--primary, #f97316);
}

#debug-log,
#server-log {
  overflow-y: auto;
  padding: 8px;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-height: 0;
}

.dl { display:flex; gap:8px; align-items:flex-start; padding:3px 4px; border-radius:4px; }
.dl:hover { background: #161825; }
.dl-time { color:#4a5070; flex-shrink:0; }
.dl-tag  { flex-shrink:0; padding:1px 6px; border-radius:4px; font-size:0.65rem; font-weight:700; text-transform:uppercase; }
.dl-msg  { color:#c9d1d9; word-break:break-word; flex:1; }
.dl-val  { color:#6e7891; font-size:0.68rem; word-break:break-all; }

.tag-info    { background:#1a2a4a; color:#58a6ff; }
.tag-search  { background:#1a3a2a; color:#3fb950; }
.tag-source  { background:#1a3020; color:#56d364; }
.tag-llm     { background:#2a1a3a; color:#bc8cff; }
.tag-timing  { background:#2a2a1a; color:#e3b341; }
.tag-error   { background:#3a1a1a; color:#f85149; }
.tag-result  { background:#1a2a1a; color:#7ee787; }
.tag-warn    { background:#2a2010; color:#d29922; }

#server-log {
  font-size: 0.7rem;
}
.sl { padding: 3px 4px; border-radius: 3px; word-break: break-word; line-height: 1.4; }
.sl:hover { background: #161825; }
.sl-error  { color: #f85149; }
.sl-warn   { color: #d29922; }
.sl-notice { color: #6e7891; }
.sl-app    { color: #bc8cff; }
.sl-info   { color: #8b949e; }
.sl-meta   { color: #3fb950; font-style: italic; }
#server-log .badge {
  display: inline-block;
  padding: 0 5px;
  border-radius: 3px;
  font-size: 0.62rem;
  font-weight: 700;
  margin-right: 4px;
  text-transform: uppercase;
}
</style>

<script>
window.MECABUDDY_DEBUG_LOG_STREAM_URL = <?= json_encode($debugLogStreamUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

window.DebugPanel = (() => {
  let startTime = null;

  const panelEl = () => document.getElementById('debug-panel');
  const logEl = () => document.getElementById('debug-log');
  const toggleEl = () => document.getElementById('debug-toggle');

  function relTime() {
    if (!startTime) return '0ms';
    return `+${Date.now() - startTime}ms`;
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  window.debugPanelEscapeHtml = escapeHtml;

  function add(tag, tagClass, message, detail = null) {
    const log = logEl();
    const toggle = toggleEl();
    if (!log) return;

    const row = document.createElement('div');
    row.className = 'dl';
    const detailStr = detail !== null && detail !== undefined
      ? escapeHtml(typeof detail === 'string' ? detail : JSON.stringify(detail))
      : '';
    row.innerHTML = `
      <span class="dl-time">${relTime()}</span>
      <span class="dl-tag ${tagClass}">${tag}</span>
      <span class="dl-msg">${escapeHtml(String(message))}</span>
      ${detailStr ? `<span class="dl-val">${detailStr}</span>` : ''}
    `;
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;

    const panel = panelEl();
    if (panel && panel.style.display !== 'flex' && toggle) {
      toggle.textContent = '🐛 Debug (●)';
    }
  }

  return {
    start() {
      startTime = Date.now();
      const log = logEl();
      const toggle = toggleEl();
      if (log) log.innerHTML = '';
      if (toggle) toggle.textContent = '🐛 Debug';
      this.info('Session démarrée');
    },

    info(msg, d) { add('INFO', 'tag-info', msg, d); },
    search(msg, d) { add('SEARCH', 'tag-search', msg, d); },
    source(msg, d) { add('SOURCE', 'tag-source', msg, d); },
    llm(msg, d) { add('LLM', 'tag-llm', msg, d); },
    timing(msg, d) { add('TIMING', 'tag-timing', msg, d); },
    error(msg, d) { add('ERROR', 'tag-error', msg, d); },
    result(msg, d) { add('RESULT', 'tag-result', msg, d); },
    warn(msg, d) { add('WARN', 'tag-warn', msg, d); },

    injectApiDebug(debug) {
      if (!debug) return;
      if (debug.serper_key_present !== undefined) {
        this.info('Serper key', { present: debug.serper_key_present });
      }
      if (debug.search_provider) {
        this.search('Provider recherche', debug.search_provider);
      }
      const details = debug.query_details || [];
      if (details.length > 0) {
        details.forEach((qd, i) => {
          const added = qd.added ?? 0;
          const suffix = added > 0
            ? ` → ${added} source${added > 1 ? 's' : ''}`
            : ' → 0';
          this.search(`Requête ${i + 1} : "${qd.query || '?'}"${suffix}`);
        });
      } else if (Array.isArray(debug.queries_run) && debug.queries_run.length > 0) {
        debug.queries_run.forEach((q, i) => {
          this.search(`Requête ${i + 1} : "${q}"`);
        });
      } else if (debug.web_searched || debug.failsafe) {
        const n = debug.queries_attempted ?? 3;
        for (let i = 0; i < n; i++) {
          this.search(`Requête ${i + 1} → 0`);
        }
      }
      const count = debug.sources_raw_count ?? 0;
      const qCount = (debug.queries_run || []).length || debug.queries_attempted || 0;
      if (debug.failsafe) {
        this.source(`${count} source(s) — mode LLM failsafe`);
        this.warn('Mode LLM failsafe — aucune source web fiable');
      } else if (count >= 3) {
        const after = qCount > 1 ? ` après ${qCount} requête${qCount > 1 ? 's' : ''}` : '';
        this.source(`${count} source(s) utiles — objectif atteint${after}`);
      } else if (debug.web_searched) {
        this.source(`${count} source(s) utiles (après filtrage)`);
      }
      if (debug.provider_used) {
        this.llm(
          `Appel LLM → ${debug.provider_used}`
          + (debug.failsafe ? ' sans contexte web' : ' avec contexte web')
        );
      }
      if (debug.sources_type) {
        this.source('Type sources', debug.sources_type);
      }
    },

    injectSSE(eventType, data) {
      const phase = data.phase || eventType;
      switch (phase) {
        case 'vehicle':
          this.info('Contexte véhicule chargé', data.vehicle || null);
          break;
        case 'search':
          this.search('Lancement recherche web', {
            vehicle: data.vehicle,
            category: data.category,
            requête: data.search_query,
          });
          break;
        case 'search_done': {
          const details = data.query_details || [];
          if (details.length > 0) {
            details.forEach((qd, i) => {
              const added = qd.added ?? 0;
              const suffix = added > 0
                ? ` → ${added} source${added > 1 ? 's' : ''}`
                : ' → 0';
              this.search(`Requête ${i + 1} : "${qd.query || '?'}"${suffix}`);
            });
          } else {
            (data.queries_run || []).forEach((q, i) => {
              this.search(`Requête ${i + 1} : "${q}"`);
            });
          }
          const count = data.sources_count ?? 0;
          const qCount = (data.queries_run || []).length;
          if (data.failsafe) {
            this.source(`${count} source(s) utiles — mode LLM failsafe activé`);
          } else if (count >= 3) {
            const after = qCount > 1 ? ` après ${qCount} requête${qCount > 1 ? 's' : ''}` : '';
            this.source(`${count} source(s) utiles — objectif atteint${after}`);
          } else {
            this.source(`${count} source(s) utiles (après filtrage)`);
          }
          (data.sources || []).forEach((s, i) => {
            this.source(`[${i + 1}] ${s.title}`, s.url);
          });
          this.timing('Recherche terminée', {
            contexte_injecté: data.context_chars
              ? `~${data.context_chars} chars`
              : data.failsafe
                ? 'aucun (failsafe)'
                : undefined,
            requêtes: data.queries_run,
          });
          break;
        }
        case 'llm':
          this.llm(
            `Appel LLM → ${data.model || '?'}`
            + (data.failsafe ? ' sans contexte web' : ' avec contexte web')
          );
          break;
        case 'saving':
          this.info('Sauvegarde en base');
          break;
        case 'done':
          this.result('Tutoriel généré', {
            vehicle_used: data.vehicle_used,
            generated_by: data.generated_by,
            failsafe: data.failsafe === true,
          });
          if (data.failsafe) {
            this.warn('Mode LLM failsafe — aucune source web fiable');
          }
          this.timing('Durée totale');
          break;
        case 'error':
          this.error(data.message || 'Erreur inconnue', {
            keys_present: data.keys_present,
            raw_preview: data.raw_preview,
          });
          break;
        default:
          break;
      }
    },
  };
})();

(function initDebugPanelUi() {
  const toggle = document.getElementById('debug-toggle');
  const panel = document.getElementById('debug-panel');
  const closeBtn = document.getElementById('debug-close');
  const clearBtn = document.getElementById('debug-clear');
  const copyBtn = document.getElementById('debug-copy');
  const frontendLog = document.getElementById('debug-log');
  const serverLog = document.getElementById('server-log');
  if (!toggle || !panel) return;

  const escapeHtml = window.debugPanelEscapeHtml || ((s) => String(s));
  let serverLogES = null;

  function switchTab(tabName) {
    document.querySelectorAll('.debug-tab').forEach((t) => {
      t.classList.toggle('active', t.dataset.tab === tabName);
    });
    if (frontendLog) {
      frontendLog.style.display = tabName === 'frontend' ? 'flex' : 'none';
    }
    if (serverLog) {
      serverLog.style.display = tabName === 'server' ? 'flex' : 'none';
    }
  }

  document.querySelectorAll('.debug-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      switchTab(tab.dataset.tab || 'frontend');
    });
  });

  function appendServerRow(data) {
    if (!serverLog) return;

    const row = document.createElement('div');
    const level = data.level || 'info';
    row.className = `sl sl-${level}`;

    const badgeColors = {
      error: '#3a1a1a',
      warn: '#2a2010',
      notice: '#1a1a2a',
      app: '#2a1a3a',
      meta: '#1a3020',
      info: '#161825',
    };

    if (data.type === 'meta') {
      row.className = 'sl sl-meta';
      row.innerHTML = `<span>▶ ${escapeHtml(data.message || '')}</span>`;
      if (data.hint) {
        const hint = document.createElement('div');
        hint.style.cssText = 'color:#6e7891;font-size:0.65rem;margin-top:2px;display:block';
        hint.textContent = data.hint;
        if (data.checked && Array.isArray(data.checked)) {
          hint.textContent += ' — ' + data.checked.filter(Boolean).join(', ');
        }
        row.appendChild(hint);
      }
    } else {
      row.innerHTML =
        `<span class="badge" style="background:${badgeColors[level] || '#161825'}">`
        + escapeHtml(String(level).toUpperCase())
        + '</span>'
        + escapeHtml(data.message || data.raw || '');
    }

    serverLog.appendChild(row);
    serverLog.scrollTop = serverLog.scrollHeight;

    const serverTab = document.querySelector('.debug-tab[data-tab="server"]');
    if (serverTab && !serverTab.classList.contains('active') && level === 'error') {
      serverTab.textContent = '🖧 Server ⚠️';
    }
  }

  function startServerLog() {
    if (serverLogES) return;

    const url = window.MECABUDDY_DEBUG_LOG_STREAM_URL
      || (typeof window.API_URL !== 'undefined' ? `${window.API_URL}/debug_log_stream.php` : '../api/debug_log_stream.php');

    serverLogES = new EventSource(url);

    serverLogES.onmessage = (e) => {
      try {
        appendServerRow(JSON.parse(e.data));
      } catch (err) {
        console.warn('Server log SSE parse:', err);
      }
    };

    serverLogES.onerror = () => {
      if (!serverLog) return;
      const row = document.createElement('div');
      row.className = 'sl sl-warn';
      row.textContent = '⚠️ Connexion au log serveur perdue (reconnexion automatique…)';
      serverLog.appendChild(row);
      serverLog.scrollTop = serverLog.scrollHeight;
    };
  }

  toggle.addEventListener('click', () => {
    const opening = panel.style.display === 'none' || panel.style.display === '';
    panel.style.display = opening ? 'flex' : 'none';
    toggle.textContent = '🐛 Debug';
    if (opening) {
      startServerLog();
    }
  });

  closeBtn?.addEventListener('click', () => {
    panel.style.display = 'none';
  });

  clearBtn?.addEventListener('click', () => {
    if (frontendLog) frontendLog.innerHTML = '';
    if (serverLog) serverLog.innerHTML = '';
    const serverTab = document.querySelector('.debug-tab[data-tab="server"]');
    if (serverTab) serverTab.textContent = '🖧 Server';
  });

  copyBtn?.addEventListener('click', () => {
    const activeTab = document.querySelector('.debug-tab.active')?.dataset.tab || 'frontend';
    const container = activeTab === 'frontend' ? frontendLog : serverLog;
    if (!container) return;

    let text;
    if (activeTab === 'frontend') {
      text = [...container.querySelectorAll('.dl')].map((row) => {
        const time = row.querySelector('.dl-time')?.textContent || '';
        const tag = row.querySelector('.dl-tag')?.textContent || '';
        const msg = row.querySelector('.dl-msg')?.textContent || '';
        const val = row.querySelector('.dl-val')?.textContent || '';
        return `[${time}] [${tag}] ${msg} ${val}`.trim();
      }).join('\n');
    } else {
      text = [...container.querySelectorAll('.sl')].map((r) => r.textContent.trim()).filter(Boolean).join('\n');
    }

    navigator.clipboard.writeText(text)
      .then(() => window.showToast?.('📋 Log copié', 'info'))
      .catch(() => {});
  });
})();
</script>
<?php endif; ?>
