const state = {
  connected: false,
  readOnly: false,
  resources: [],
  order: [],
  onboot: {},
  initialOrder: [],
  initialOnboot: {},
  draggedId: null,
};

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => Array.from(document.querySelectorAll(selector));

function api(path, options = {}) {
  return fetch(path, {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    ...options,
  }).then(async (response) => {
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.error || `Erreur HTTP ${response.status}`);
    }
    return data;
  });
}

function showToast(message, kind = 'info') {
  const toast = $('#toast');
  toast.textContent = message;
  toast.className = `toast ${kind}`;
  toast.hidden = false;
  setTimeout(() => {
    toast.hidden = true;
  }, 4500);
}

function formToObject(form) {
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());
  data.verifySsl = formData.has('verifySsl');
  data.readOnly = formData.has('readOnly');
  return data;
}

function setConnected(session) {
  state.connected = session.connected;
  state.readOnly = session.mode === 'read-only' || session.readOnly === true;
  $('#connect-panel').hidden = state.connected;
  $('#workspace').hidden = !state.connected;
  $('#logout-btn').hidden = !state.connected;
  $('#mode-badge').hidden = !state.connected;
  $('#mode-badge').textContent = state.readOnly ? 'Lecture seule' : 'Écriture';
  $('#mode-badge').className = `badge ${state.readOnly ? 'readonly' : 'write'}`;
  $('#session-state').textContent = state.connected
    ? `Connecté à ${session.baseUrl || 'Proxmox'}`
    : 'Non connecté';
}

async function loadVersion() {
  try {
    const data = await api('/api/version');
    $('#app-version').textContent = `v${data.version}`;
  } catch (error) {
    $('#app-version').textContent = 'vdev';
  }
}

function filteredResources() {
  const query = $('#search').value.trim().toLowerCase();
  const type = $('#type-filter').value;
  const node = $('#node-filter').value;
  const onboot = $('#onboot-filter').value;

  return state.order
    .map((id) => state.resources.find((resource) => resource.id === id))
    .filter(Boolean)
    .filter((resource) => type === 'all' || resource.type === type)
    .filter((resource) => node === 'all' || resource.node === node)
    .filter((resource) => {
      if (onboot === 'all') return true;
      return state.onboot[resource.id] === (onboot === 'on');
    })
    .filter((resource) => {
      if (query === '') return true;
      return [
        resource.name,
        resource.node,
        String(resource.vmid),
        resource.type,
      ].some((value) => value.toLowerCase().includes(query));
    });
}

function currentChanges() {
  return state.order
    .map((id, index) => {
      const resource = state.resources.find((item) => item.id === id);
      if (!resource) return null;
      const nextOrder = index + 1;
      const currentOrder = resource.startup.order;
      const nextOnboot = state.onboot[id];
      const currentOnboot = Boolean(resource.onboot);
      const orderChanged = nextOnboot && currentOrder !== nextOrder;
      const onbootChanged = currentOnboot !== nextOnboot;
      if (!orderChanged && !onbootChanged) return null;
      return {
        type: resource.type,
        node: resource.node,
        vmid: resource.vmid,
        name: resource.name,
        from: currentOrder,
        order: nextOrder,
        currentOnboot,
        onboot: nextOnboot,
        orderChanged,
        onbootChanged,
        up: resource.startup.up,
        down: resource.startup.down,
      };
    })
    .filter(Boolean);
}

function resetLocalChanges() {
  const changes = currentChanges();
  if (changes.length === 0) {
    return;
  }

  if (!confirm(`Annuler ${changes.length} modification(s) locale(s) non appliquée(s) ?`)) {
    return;
  }

  state.order = [...state.initialOrder];
  state.onboot = { ...state.initialOnboot };
  renderResources();
  showToast('Modifications locales annulées.');
}

function displayOrder(order) {
  return order ?? 'Non défini';
}

function buildApplyPayload(changes) {
  return changes.map((change) => {
    const payload = {
      type: change.type,
      node: change.node,
      vmid: change.vmid,
    };

    if (change.orderChanged) {
      payload.order = change.order;
      payload.up = change.up;
      payload.down = change.down;
    }

    if (change.onbootChanged) {
      payload.onboot = change.onboot;
    }

    return payload;
  });
}

function buildApplySummary(changes) {
  const startupChanges = changes.filter((change) => change.orderChanged).length;
  const onbootChanges = changes.filter((change) => change.onbootChanged).length;
  const lines = [
    'Appliquer les modifications dans Proxmox ?',
    '',
    `${changes.length} ressource(s) modifiée(s)`,
    `${startupChanges} changement(s) d'ordre startup`,
    `${onbootChanges} changement(s) de démarrage automatique`,
    '',
    ...changes.slice(0, 8).map((change) => {
      const details = [];
      if (change.orderChanged) {
        details.push(`ordre ${displayOrder(change.from)} -> ${change.order}`);
      }
      if (change.onbootChanged) {
        details.push(`${change.currentOnboot ? 'Auto' : 'Manuel'} -> ${change.onboot ? 'Auto' : 'Manuel'}`);
      }
      return `- ${change.name} (${change.type.toUpperCase()} ${change.vmid}) : ${details.join(', ')}`;
    }),
  ];

  if (changes.length > 8) {
    lines.push(`- ... ${changes.length - 8} autre(s) ressource(s)`);
  }

  return lines.join('\n');
}

function showApplyResults(data) {
  const lines = [
    `Application terminée : ${data.success} succès, ${data.failed} échec(s).`,
    '',
    ...data.results.map((result) => {
      const target = `${result.type.toUpperCase()} ${result.vmid} (${result.node})`;
      return result.status === 'success'
        ? `OK  ${target}`
        : `ERR ${target} : ${result.error || 'Erreur inconnue'}`;
    }),
  ];

  alert(lines.join('\n'));
}

function renderNodeFilter() {
  const current = $('#node-filter').value;
  const nodes = [...new Set(state.resources.map((resource) => resource.node))].sort();
  $('#node-filter').innerHTML = '<option value="all">Tous</option>' +
    nodes.map((node) => `<option value="${escapeHtml(node)}">${escapeHtml(node)}</option>`).join('');
  $('#node-filter').value = nodes.includes(current) ? current : 'all';
}

function renderResources() {
  const resources = filteredResources();
  const list = $('#resource-list');
  list.innerHTML = resources.map((resource) => {
    const order = state.order.indexOf(resource.id) + 1;
    const onboot = Boolean(state.onboot[resource.id]);
    return `
      <article class="resource-row" draggable="true" data-id="${escapeHtml(resource.id)}">
        <div class="drag-handle" aria-hidden="true">::</div>
        <div class="order">${order}</div>
        <div>
          <strong>${escapeHtml(resource.name)}</strong>
          <p>${resource.type.toUpperCase()} ${resource.vmid} · ${escapeHtml(resource.node)}</p>
        </div>
        <span class="status ${escapeHtml(resource.status)}">${escapeHtml(resource.status)}</span>
        <label class="onboot-toggle" title="Démarrage automatique au boot Proxmox">
          <input type="checkbox" data-onboot-id="${escapeHtml(resource.id)}" ${onboot ? 'checked' : ''}>
          <span>${onboot ? 'Auto' : 'Manuel'}</span>
        </label>
        <div class="startup">
          <span>up ${resource.startup.up ?? '-'}</span>
          <span>down ${resource.startup.down ?? '-'}</span>
        </div>
      </article>
    `;
  }).join('');

  $('#empty-state').hidden = resources.length > 0;
  $('#resource-count').textContent = `${resources.length} ressource${resources.length > 1 ? 's' : ''}`;
  bindOnbootToggles();
  bindDrag();
  renderPreview();
}

function renderPreview() {
  const changes = currentChanges();
  const changedIds = new Set(changes.map((change) => `${change.type}-${change.vmid}`));
  const previewItems = filteredResources()
    .filter((resource) => Boolean(state.onboot[resource.id]))
    .map((resource) => {
      const order = state.order.indexOf(resource.id) + 1;
      return {
        id: resource.id,
        type: resource.type,
        node: resource.node,
        vmid: resource.vmid,
        name: resource.name,
        from: resource.startup.order,
        order,
        currentOnboot: Boolean(resource.onboot),
        onboot: Boolean(state.onboot[resource.id]),
        changed: changedIds.has(resource.id),
      };
    });

  $('#change-count').textContent = `${changes.length} modification${changes.length > 1 ? 's' : ''}`;
  $('#reset-btn').disabled = changes.length === 0;
  $('#preview-list').innerHTML = previewItems.length === 0
    ? '<p class="empty">Aucune ressource en démarrage automatique à prévisualiser.</p>'
    : previewItems.map((item) => `
        <div class="preview-item ${item.changed ? 'changed' : 'unchanged'}">
          <div class="preview-name">
            <strong>${escapeHtml(item.name)}</strong>
            <span>${item.type.toUpperCase()} ${item.vmid} · ${escapeHtml(item.node)}</span>
          </div>
          <div class="preview-flow" aria-label="Ordre actuel et nouvel ordre">
            <div class="preview-order current">
              <small>Actuel</small>
              <strong>${escapeHtml(displayOrder(item.from))}</strong>
              <span>${item.currentOnboot ? 'Auto' : 'Manuel'}</span>
            </div>
            <span class="preview-arrow">→</span>
            <div class="preview-order next">
              <small>Après</small>
              <strong>${escapeHtml(item.order)}</strong>
              <span>${item.onboot ? 'Auto' : 'Manuel'}</span>
            </div>
          </div>
          ${item.changed ? '<span class="preview-badge">Modifié</span>' : ''}
        </div>
      `).join('');

  $('#apply-btn').disabled = changes.length === 0 || state.readOnly;
}

function bindOnbootToggles() {
  $$('[data-onboot-id]').forEach((input) => {
    input.addEventListener('change', () => {
      state.onboot[input.dataset.onbootId] = input.checked;
      renderResources();
    });
  });
}

function bindDrag() {
  $$('.resource-row').forEach((row) => {
    row.addEventListener('dragstart', () => {
      state.draggedId = row.dataset.id;
      row.classList.add('dragging');
    });

    row.addEventListener('dragend', () => {
      state.draggedId = null;
      row.classList.remove('dragging');
    });

    row.addEventListener('dragover', (event) => {
      event.preventDefault();
      const targetId = row.dataset.id;
      if (!state.draggedId || state.draggedId === targetId) return;

      const from = state.order.indexOf(state.draggedId);
      const to = state.order.indexOf(targetId);
      if (from < 0 || to < 0) return;

      state.order.splice(from, 1);
      state.order.splice(to, 0, state.draggedId);
      renderResources();
    });
  });
}

async function loadResources() {
  const data = await api('/api/resources');
  state.resources = data.resources;
  state.order = data.resources.map((resource) => resource.id);
  state.onboot = Object.fromEntries(data.resources.map((resource) => [resource.id, Boolean(resource.onboot)]));
  state.initialOrder = [...state.order];
  state.initialOnboot = { ...state.onboot };
  renderNodeFilter();
  renderResources();
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

$('#auth-mode').addEventListener('change', (event) => {
  const tokenMode = event.target.value === 'token';
  $$('.password-auth').forEach((field) => {
    field.hidden = tokenMode;
  });
  $$('.token-auth').forEach((field) => {
    field.hidden = !tokenMode;
  });
});

$('#connect-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = await api('/api/connect', {
      method: 'POST',
      body: JSON.stringify(formToObject(event.currentTarget)),
    });
    setConnected(data);
    await loadResources();
    showToast('Connexion établie.');
  } catch (error) {
    showToast(error.message, 'error');
  }
});

$('#logout-btn').addEventListener('click', async () => {
  await api('/api/logout', { method: 'POST', body: '{}' });
  state.resources = [];
  state.order = [];
  state.onboot = {};
  state.initialOrder = [];
  state.initialOnboot = {};
  setConnected({ connected: false });
});

$('#reload-btn').addEventListener('click', async () => {
  try {
    await loadResources();
    showToast('Ressources actualisées.');
  } catch (error) {
    showToast(error.message, 'error');
  }
});

$('#reset-btn').addEventListener('click', resetLocalChanges);

$('#apply-btn').addEventListener('click', async () => {
  try {
    const changes = currentChanges();
    if (changes.length === 0) {
      return;
    }

    if (!confirm(buildApplySummary(changes))) {
      return;
    }

    const payload = buildApplyPayload(changes);
    const data = await api('/api/startup', {
      method: 'PUT',
      body: JSON.stringify({ changes: payload }),
    });
    showToast(`${data.success} succès, ${data.failed} échec(s).`, data.failed > 0 ? 'error' : 'info');
    showApplyResults(data);
    await loadResources();
  } catch (error) {
    showToast(error.message, 'error');
  }
});

['search', 'type-filter', 'node-filter', 'onboot-filter'].forEach((id) => {
  $(`#${id}`).addEventListener('input', renderResources);
});

loadVersion();

api('/api/session')
  .then(async (session) => {
    setConnected(session);
    if (session.connected) {
      await loadResources();
    }
  })
  .catch(() => setConnected({ connected: false }));
