<?php
/**
 * MecaBuddy - Gestion du garage (slots actifs + liste complète)
 */

$pageTitle = 'Mon garage - MecaBuddy';
$currentPage = 'garage';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">🏎️</span>
        Mon garage
    </h1>
    <p class="page-subtitle">
        Jusqu'à 12 véhicules enregistrés, 3 actifs en simultané (slots 1, 2 et 3)
    </p>
</div>

<section class="garage-slots-section" aria-label="Véhicules actifs">
    <h2 class="garage-section-title">Emplacements actifs</h2>
    <div class="slots-grid">
        <div class="slot-card" data-slot="1">
            <div class="slot-label">🚗 Véhicule principal</div>
            <div class="slot-body"></div>
        </div>
        <div class="slot-card" data-slot="2">
            <div class="slot-label">🚗 Véhicule 2</div>
            <div class="slot-body"></div>
        </div>
        <div class="slot-card" data-slot="3">
            <div class="slot-label">🚗 Véhicule 3</div>
            <div class="slot-body"></div>
        </div>
    </div>
</section>

<section class="garage-list-section" id="garage-list-section" aria-label="Tous les véhicules">
    <div class="garage-header">
        <h2 class="garage-list-title">🏎️ Mon garage <span id="garage-count">0/12</span></h2>
        <a href="<?= PUBLIC_URL ?>/vehicle.php" id="btn-add-vehicle" class="btn btn-primary">+ Ajouter un véhicule</a>
    </div>
    <div id="garage-grid" class="garage-grid" role="list"></div>
    <p id="garage-empty" class="garage-empty hidden">Aucun véhicule dans le garage. Ajoutez-en un pour commencer.</p>
</section>

<script>
const API_BASE = '<?= API_URL ?>';
let garageData = null;

const SLOT_LABELS = {
    1: 'Véhicule principal',
    2: 'Véhicule 2',
    3: 'Véhicule 3',
};

function escapeHtml(text) {
    const el = document.createElement('div');
    el.textContent = text ?? '';
    return el.innerHTML;
}

function transmissionChipLabel(transmission) {
    if (!transmission) return '';
    const key = String(transmission).toLowerCase();
    if (key === 'manuelle' || key === 'manual' || key === 'm') return 'Manuelle';
    if (key === 'automatique' || key === 'automatic' || key === 'a') return 'Automatique';
    return transmission;
}

function renderSlotCard(slotCard, vehicle, slot) {
    if (!slotCard) return;

    const body = slotCard.querySelector('.slot-body');
    slotCard.classList.toggle('occupied', !!vehicle);

    if (!vehicle) {
        body.innerHTML = `
            <p class="slot-empty-hint">Emplacement libre</p>
            <button type="button" class="btn btn-secondary btn-sm btn-assign-slot" data-slot="${slot}">
                + Assigner
            </button>
        `;
        return;
    }

    const icon = vehicle.category === 'moto' ? '🏍️' : '🚗';
    body.innerHTML = `
        <div class="slot-vehicle">
            <span class="slot-vehicle-icon">${icon}</span>
            <div class="slot-vehicle-info">
                <strong>${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)}</strong>
                <span class="year-badge">${escapeHtml(String(vehicle.year))}</span>
            </div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm btn-remove-from-slot" data-vehicle-id="${vehicle.id}">
            Retirer
        </button>
    `;
}

function createGarageCard(v) {
    const card = document.createElement('article');
    card.className = 'garage-card' + (v.is_active ? ' active' : '');
    card.dataset.id = String(v.id);
    card.setAttribute('role', 'listitem');

    const icon = v.category === 'moto' ? '🏍️' : '🚗';
    const chips = [];
    if (v.engine_type) {
        chips.push(`<span class="detail-chip">⚙️ ${escapeHtml(v.engine_type)}</span>`);
    }
    if (v.transmission) {
        chips.push(`<span class="detail-chip">🔄 ${escapeHtml(transmissionChipLabel(v.transmission))}</span>`);
    }

    let actionsHtml;
    if (v.is_active) {
        actionsHtml = `
            <span class="active-badge">Slot ${v.slot}</span>
            <button type="button" class="btn btn-secondary btn-sm btn-remove-slot" data-vehicle-id="${v.id}">
                Retirer du slot
            </button>
        `;
    } else {
        actionsHtml = `
            <div class="slot-assign">
                <span>Assigner au slot :</span>
                <button type="button" class="btn-slot" data-slot="1" data-vehicle-id="${v.id}">1</button>
                <button type="button" class="btn-slot" data-slot="2" data-vehicle-id="${v.id}">2</button>
                <button type="button" class="btn-slot" data-slot="3" data-vehicle-id="${v.id}">3</button>
            </div>
        `;
    }

    card.innerHTML = `
        <div class="garage-card-header">
            <span class="vehicle-category-icon">${icon}</span>
            <div class="garage-card-title">${escapeHtml(v.brand)} ${escapeHtml(v.model)}</div>
            <span class="year-badge">${escapeHtml(String(v.year))}</span>
        </div>
        <div class="garage-card-details">${chips.join('')}</div>
        <div class="garage-card-actions">
            ${actionsHtml}
            <button type="button" class="btn-delete-vehicle" data-vehicle-id="${v.id}" title="Supprimer du garage">🗑️</button>
        </div>
    `;

    return card;
}

function updateGarageFullState(total) {
    const btnAdd = document.getElementById('btn-add-vehicle');
    if (!btnAdd) return;
    const full = total >= 12;
    btnAdd.classList.toggle('disabled', full);
    btnAdd.dataset.garageFull = full ? '1' : '0';
    if (full) {
        btnAdd.setAttribute('aria-disabled', 'true');
        btnAdd.setAttribute('title', 'Garage plein (12 véhicules maximum)');
    } else {
        btnAdd.removeAttribute('aria-disabled');
        btnAdd.removeAttribute('title');
    }
}

async function loadGarage() {
    try {
        const res = await fetch(`${API_BASE}/vehicle_api.php?action=garage`);
        const data = await res.json();

        if (!data.success) {
            window.showToast?.(data.error || 'Erreur chargement garage', 'error');
            return;
        }

        garageData = data;

        [1, 2, 3].forEach((slot) => {
            const slotCard = document.querySelector(`.slot-card[data-slot="${slot}"]`);
            renderSlotCard(slotCard, data.slots[slot], slot);
        });

        document.getElementById('garage-count').textContent = `${data.total}/12`;

        const grid = document.getElementById('garage-grid');
        const emptyMsg = document.getElementById('garage-empty');
        grid.innerHTML = '';
        data.vehicles.forEach((v) => grid.appendChild(createGarageCard(v)));
        emptyMsg.classList.toggle('hidden', data.vehicles.length > 0);

        updateGarageFullState(data.total);

        if (data.active_count >= 3) {
            document.querySelectorAll('.btn-slot').forEach((btn) => {
                const parentCard = btn.closest('.garage-card');
                const vehicleId = parseInt(parentCard?.dataset.id, 10);
                const vehicle = data.vehicles.find((item) => item.id === vehicleId);
                if (!vehicle?.is_active) {
                    btn.disabled = true;
                }
            });
        }
    } catch (err) {
        console.error(err);
        window.showToast?.('Erreur réseau (garage)', 'error');
    }
}

async function assignSlot(vehicleId, slot) {
    try {
        const res = await fetch(`${API_BASE}/vehicle_api.php?action=set_active`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ vehicle_id: vehicleId, slot, active: true }),
        });
        const data = await res.json();
        if (!data.success) {
            window.showToast?.(data.error || 'Impossible d\'assigner le slot', 'error');
            return;
        }
        await loadGarage();
        window.showToast?.(`✅ Véhicule assigné au slot ${slot}`, 'success');
    } catch (err) {
        console.error(err);
        window.showToast?.('Erreur réseau', 'error');
    }
}

async function removeSlot(vehicleId) {
    try {
        const res = await fetch(`${API_BASE}/vehicle_api.php?action=set_active`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ vehicle_id: vehicleId, slot: null, active: false }),
        });
        const data = await res.json();
        if (!data.success) {
            window.showToast?.(data.error || 'Impossible de retirer le slot', 'error');
            return;
        }
        await loadGarage();
        window.showToast?.('↩️ Véhicule retiré du slot', 'info');
    } catch (err) {
        console.error(err);
        window.showToast?.('Erreur réseau', 'error');
    }
}

async function deleteVehicle(vehicleId) {
    if (!confirm('Supprimer ce véhicule du garage ?')) return;
    try {
        const res = await fetch(`${API_BASE}/vehicle_api.php?action=delete_vehicle`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ vehicle_id: vehicleId }),
        });
        const data = await res.json();
        if (!data.success) {
            window.showToast?.(data.error || 'Suppression impossible', 'error');
            return;
        }
        await loadGarage();
        window.showToast?.('🗑️ Véhicule supprimé', 'success');
    } catch (err) {
        console.error(err);
        window.showToast?.('Erreur réseau', 'error');
    }
}

function scrollToGarageForSlot(slot) {
    document.getElementById('garage-list-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.showToast?.(`Choisissez un véhicule ci-dessous et assignez-le au slot ${slot}`, 'info');
}

function bindGarageEvents() {
    document.querySelector('.slots-grid')?.addEventListener('click', (e) => {
        const assignBtn = e.target.closest('.btn-assign-slot');
        if (assignBtn) {
            scrollToGarageForSlot(parseInt(assignBtn.dataset.slot, 10));
            return;
        }
        const removeBtn = e.target.closest('.btn-remove-from-slot');
        if (removeBtn) {
            removeSlot(parseInt(removeBtn.dataset.vehicleId, 10));
        }
    });

    document.getElementById('garage-grid')?.addEventListener('click', (e) => {
        const slotBtn = e.target.closest('.btn-slot');
        if (slotBtn && !slotBtn.disabled) {
            assignSlot(parseInt(slotBtn.dataset.vehicleId, 10), parseInt(slotBtn.dataset.slot, 10));
            return;
        }
        const removeSlotBtn = e.target.closest('.btn-remove-slot');
        if (removeSlotBtn) {
            removeSlot(parseInt(removeSlotBtn.dataset.vehicleId, 10));
            return;
        }
        const deleteBtn = e.target.closest('.btn-delete-vehicle');
        if (deleteBtn) {
            deleteVehicle(parseInt(deleteBtn.dataset.vehicleId, 10));
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btn-add-vehicle')?.addEventListener('click', (e) => {
        if (e.currentTarget.dataset.garageFull === '1') {
            e.preventDefault();
            window.showToast?.('Garage plein (12 véhicules maximum)', 'warning');
        }
    });
    bindGarageEvents();
    loadGarage();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
