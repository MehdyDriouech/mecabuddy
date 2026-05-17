<?php
/**
 * Modale de sélection du véhicule de travail (diagnostic / tutoriels).
 * Requiert API_URL, PUBLIC_URL (config chargée par header).
 */
?>
<div id="vehicle-selector-modal" class="vsel-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="vsel-modal-title">
    <div class="vsel-modal-box">
        <div class="vsel-modal-header" id="vsel-modal-title">
            <span>🚗 Sur quel véhicule on travaille ?</span>
        </div>

        <div class="vsel-modal-body">
            <p class="vsel-modal-hint" id="vsel-hint">
                Ton véhicule principal est sélectionné par défaut.
            </p>

            <div id="vsel-cards" class="vsel-cards"></div>
        </div>

        <div class="vsel-modal-footer">
            <button type="button" id="vsel-confirm" class="btn btn-primary" disabled>
                ✅ Confirmer
            </button>
        </div>
    </div>
</div>

<style>
.vsel-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9990;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}
.vsel-modal-box {
    background: var(--color-secondary-light, #1a1d2e);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 24px;
    width: 100%;
    max-width: 480px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.5);
}
.vsel-modal-header {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary, #fff);
}
.vsel-modal-hint {
    font-size: 0.82rem;
    color: var(--text-secondary, #aaa);
    margin: 0;
}
.vsel-cards {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.vsel-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 10px;
    border: 2px solid rgba(255, 255, 255, 0.12);
    cursor: pointer;
    transition: all 0.15s;
    background: transparent;
    text-align: left;
    width: 100%;
    color: inherit;
    font: inherit;
}
.vsel-card:hover {
    border-color: var(--color-primary, #f97316);
    background: rgba(249, 115, 22, 0.05);
}
.vsel-card.selected {
    border-color: var(--color-primary, #f97316);
    background: rgba(249, 115, 22, 0.1);
}
.vsel-card-info {
    flex: 1;
    min-width: 0;
}
.vsel-card-name {
    font-weight: 600;
    color: var(--text-primary, #fff);
    font-size: 0.95rem;
}
.vsel-card-detail {
    font-size: 0.75rem;
    color: var(--text-secondary, #aaa);
    margin-top: 2px;
}
.vsel-slot-badge {
    font-size: 0.65rem;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: var(--text-secondary, #aaa);
    white-space: nowrap;
}
.vsel-slot-badge.principal {
    background: rgba(249, 115, 22, 0.2);
    color: var(--color-primary, #f97316);
    font-weight: 700;
}
.vsel-modal-footer {
    display: flex;
    justify-content: flex-end;
}
</style>

<script>
window.VehicleSelector = (() => {
    const API_BASE = <?= json_encode(rtrim(API_URL, '/'), JSON_UNESCAPED_UNICODE) ?>;
    const VEHICLE_PAGE = <?= json_encode(PUBLIC_URL . '/vehicle.php', JSON_UNESCAPED_UNICODE) ?>;

    let selectedVehicleId = null;
    let selectedVehicle = null;
    let onConfirmCallback = null;

    function escapeHtml(text) {
        const el = document.createElement('div');
        el.textContent = text ?? '';
        return el.innerHTML;
    }

    function renderCard(container, vehicle, slot, preSelected) {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'vsel-card' + (preSelected ? ' selected' : '');
        card.dataset.vehicleId = String(vehicle.id);

        const details = [vehicle.engine_type, vehicle.transmission]
            .filter(Boolean)
            .join(' · ');

        const icon = vehicle.category === 'moto' ? '🏍️' : '🚗';
        const slotNum = parseInt(slot, 10);

        card.innerHTML = `
            <span class="vsel-card-icon">${icon}</span>
            <div class="vsel-card-info">
                <div class="vsel-card-name">${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)} ${escapeHtml(String(vehicle.year))}</div>
                ${details ? `<div class="vsel-card-detail">${escapeHtml(details)}</div>` : ''}
            </div>
            <span class="vsel-slot-badge ${slotNum === 1 ? 'principal' : ''}">
                ${slotNum === 1 ? '⭐ Principal' : `Slot ${slotNum}`}
            </span>
        `;

        card.addEventListener('click', () => {
            document.querySelectorAll('.vsel-card').forEach((c) => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedVehicleId = vehicle.id;
            selectedVehicle = vehicle;
            const confirmBtn = document.getElementById('vsel-confirm');
            if (confirmBtn) {
                confirmBtn.disabled = false;
            }
        });

        container.appendChild(card);
    }

    async function open(onConfirm) {
        onConfirmCallback = typeof onConfirm === 'function' ? onConfirm : null;
        selectedVehicleId = null;
        selectedVehicle = null;

        let data;
        try {
            const res = await fetch(`${API_BASE}/vehicle_api.php?action=garage`);
            data = await res.json();
        } catch (err) {
            console.error(err);
            window.showToast?.('Impossible de charger le garage', 'error');
            return;
        }

        if (!data.success) {
            window.showToast?.(data.error || 'Erreur garage', 'error');
            return;
        }

        const slots = data.slots || {};
        const filled = Object.entries(slots).filter(([, v]) => v !== null);

        if (filled.length === 0) {
            window.location.href = VEHICLE_PAGE;
            return;
        }

        const modal = document.getElementById('vehicle-selector-modal');
        const cards = document.getElementById('vsel-cards');
        const hint = document.getElementById('vsel-hint');
        const confirm = document.getElementById('vsel-confirm');

        if (!modal || !cards || !hint || !confirm) {
            return;
        }

        cards.innerHTML = '';
        confirm.disabled = true;

        if (filled.length === 1) {
            hint.textContent = "C'est parti, on travaille sur :";
            const [slot, vehicle] = filled[0];
            renderCard(cards, vehicle, slot, true);
            selectedVehicleId = vehicle.id;
            selectedVehicle = vehicle;
            confirm.disabled = false;
        } else {
            hint.textContent = 'Ton véhicule principal est sélectionné. Tu peux en choisir un autre.';
            filled.forEach(([slot, vehicle]) => {
                const isDefault = parseInt(slot, 10) === 1;
                renderCard(cards, vehicle, slot, isDefault);
                if (isDefault) {
                    selectedVehicleId = vehicle.id;
                    selectedVehicle = vehicle;
                    confirm.disabled = false;
                }
            });
        }

        modal.style.display = 'flex';
    }

    document.getElementById('vsel-confirm')?.addEventListener('click', async () => {
        if (!selectedVehicleId) {
            return;
        }

        try {
            const res = await fetch(`${API_BASE}/vehicle_api.php?action=set_current`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vehicle_id: selectedVehicleId }),
            });
            const data = await res.json();

            if (!data.success) {
                window.showToast?.(data.error || 'Erreur de sélection', 'error');
                return;
            }

            if (data.vehicle) {
                selectedVehicle = data.vehicle;
            }

            const modal = document.getElementById('vehicle-selector-modal');
            if (modal) {
                modal.style.display = 'none';
            }

            if (onConfirmCallback) {
                onConfirmCallback(selectedVehicleId, selectedVehicle);
            }
        } catch (err) {
            console.error(err);
            window.showToast?.('Erreur réseau', 'error');
        }
    });

    return { open };
})();
</script>
