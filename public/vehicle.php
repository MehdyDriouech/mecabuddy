<?php
/**
 * MecaBuddy - Page de sélection véhicule
 * 
 * Permet de :
 * - Entrer une plaque d'immatriculation (recherche simulée)
 * - OU sélectionner manuellement marque/modèle/année
 */

$pageTitle = 'Sélection véhicule - MecaBuddy';
$currentPage = 'vehicle';

require_once __DIR__ . '/../includes/header.php';

$plateEnabled = isPlateApiEnabled();
$demoMode = isDemoMode();
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">🚗</span>
        Sélectionner mon véhicule
    </h1>
    <p class="page-subtitle">
        Entre ta plaque d'immatriculation ou sélectionne ton véhicule manuellement
    </p>
</div>

<div class="vehicle-selection-container">
    
    <?php if ($plateEnabled): ?>
    <!-- Section Plaque d'immatriculation -->
    <section class="selection-section license-section">
        <div class="section-header">
            <h2>
                <span class="section-number">1</span>
                Par plaque d'immatriculation
            </h2>
            <span class="section-badge">Rapide</span>
        </div>
        
        <form id="licensePlateForm" class="license-form">
            <div class="license-plate-input">
                <div class="plate-visual">
                    <span class="plate-country">F</span>
                    <input type="text" 
                           id="licensePlate" 
                           name="license_plate" 
                           placeholder="AB-123-CD"
                           maxlength="12"
                           autocomplete="off"
                           pattern="[A-Za-z]{2}[-\s]?[0-9]{3}[-\s]?[A-Za-z]{2}"
                           class="plate-input">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lookup">
                <span class="btn-icon">🔍</span>
                Rechercher
            </button>
        </form>
        
        <div id="lookupResult" class="lookup-result hidden">
            <!-- Résultat de la recherche par plaque -->
        </div>
    </section>
    <?php endif; ?>
    
    <?php if ($plateEnabled || $demoMode): ?>
    <div class="section-divider">
        <span>OU</span>
    </div>
    <?php endif; ?>
    
    <!-- Section Sélection manuelle -->
    <section class="selection-section manual-section">
        <div class="section-header">
            <h2>
                <span class="section-number">2</span>
                Sélection manuelle
            </h2>
        </div>
        
        <form id="manualSelectionForm" class="manual-form">
            <div class="vehicle-category-toggle" role="group" aria-label="Type de véhicule">
                <button type="button" class="cat-btn active" data-cat="car">🚗 Voiture</button>
                <button type="button" class="cat-btn" data-cat="moto">🏍️ Moto</button>
            </div>

            <!-- Marque -->
            <div class="form-group">
                <label for="brand" class="form-label">
                    <span class="label-icon">🏭</span>
                    Marque
                </label>
                <select id="brand" name="brand" class="form-select" required>
                    <option value="">Sélectionnez une marque...</option>
                </select>
            </div>
            
            <!-- Modèle -->
            <div class="form-group">
                <label for="model" class="form-label">
                    <span class="label-icon">🚙</span>
                    Modèle
                </label>
                <select id="model" name="model" class="form-select" required disabled>
                    <option value="">Sélectionnez d'abord une marque...</option>
                </select>
            </div>
            
            <!-- Année -->
            <div class="form-group">
                <label for="year" class="form-label">
                    <span class="label-icon">📅</span>
                    Année
                </label>
                <select id="year" name="year" class="form-select" required>
                    <option value="">Sélectionnez une année...</option>
                </select>
            </div>

            <div id="section-engine" class="form-group" style="display:none">
                <label for="select-engine" class="form-label">
                    <span class="label-icon">⚙️</span>
                    Motorisation
                </label>
                <select id="select-engine" class="form-select">
                    <option value="">Sélectionnez une motorisation...</option>
                </select>
                <p class="dev-hint" style="font-size:0.75rem;margin-top:4px">
                    Optionnel — permet un diagnostic plus précis (essence vs diesel)
                </p>
            </div>
            
            <!-- Options avancées (collapsible) -->
            <details class="advanced-options">
                <summary class="advanced-toggle">
                    <span>⚙️ Options avancées</span>
                </summary>
                <div class="advanced-content">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="engineType" class="form-label">Type moteur</label>
                            <select id="engineType" name="engine_type" class="form-select">
                                <option value="">Non spécifié</option>
                                <option value="Essence">Essence</option>
                                <option value="Diesel">Diesel</option>
                                <option value="Hybride">Hybride</option>
                                <option value="Électrique">Électrique</option>
                                <option value="GPL">GPL</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="engineSize" class="form-label">Cylindrée</label>
                            <select id="engineSize" name="engine_size" class="form-select">
                                <option value="">Non spécifiée</option>
                                <option value="1.0L">1.0L</option>
                                <option value="1.2L">1.2L</option>
                                <option value="1.4L">1.4L</option>
                                <option value="1.5L">1.5L</option>
                                <option value="1.6L">1.6L</option>
                                <option value="1.8L">1.8L</option>
                                <option value="2.0L">2.0L</option>
                                <option value="2.2L">2.2L</option>
                                <option value="2.5L">2.5L</option>
                                <option value="3.0L+">3.0L+</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="transmission" class="form-label">Transmission</label>
                        <select id="transmission" name="transmission" class="form-select">
                            <option value="">Non spécifiée</option>
                            <option value="Manuelle">Manuelle</option>
                            <option value="Automatique">Automatique</option>
                            <option value="Semi-automatique">Semi-automatique</option>
                        </select>
                    </div>
                </div>
            </details>
            
            <button type="submit" class="btn btn-primary btn-save" disabled>
                <span class="btn-icon">✓</span>
                Enregistrer mon véhicule
            </button>
        </form>
    </section>
</div>

<!-- Modal de confirmation -->
<div id="confirmationModal" class="modal hidden">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-icon success">✓</div>
        <h2>Véhicule enregistré !</h2>
        <div id="savedVehicleInfo" class="saved-vehicle-info">
            <!-- Info du véhicule sauvegardé -->
        </div>
        <div class="modal-actions">
            <a href="<?= PUBLIC_URL ?>/tutorial.php" class="btn btn-primary">
                Générer un tutoriel
            </a>
            <a href="<?= PUBLIC_URL ?>/index.php" class="btn btn-secondary">
                Retour à l'accueil
            </a>
        </div>
    </div>
</div>

<style>
.vehicle-category-toggle { display:flex; gap:8px; margin-bottom:16px; }
.cat-btn {
  flex:1; padding:10px; border-radius:8px; border:2px solid var(--border,#333);
  background:transparent; color:var(--text,#fff); cursor:pointer; font-size:0.9rem;
  transition:all 0.2s;
}
.cat-btn.active {
  background:var(--primary,#f97316);
  border-color:var(--primary,#f97316);
  color:#fff; font-weight:600;
}
</style>

<script>
const API_BASE = '<?= API_URL ?>';
let selectedVehicleCategory = 'car';

document.addEventListener('DOMContentLoaded', () => {
    loadBrands(selectedVehicleCategory);
    populateYears();
    setupFormListeners();
    setupCategoryToggle();
});

function setupCategoryToggle() {
    document.querySelectorAll('.cat-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const cat = btn.dataset.cat;
            if (!cat || cat === selectedVehicleCategory) {
                return;
            }
            selectedVehicleCategory = cat;
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.toggle('active', b.dataset.cat === cat));
            resetModelAndEngine();
            loadBrands(cat);
        });
    });
}

function resetModelAndEngine() {
    const modelSelect = document.getElementById('model');
    modelSelect.innerHTML = '<option value="">Sélectionnez d\'abord une marque...</option>';
    modelSelect.disabled = true;
    hideEngineSection();
}

function hideEngineSection() {
    const section = document.getElementById('section-engine');
    const engineSelect = document.getElementById('select-engine');
    section.style.display = 'none';
    engineSelect.innerHTML = '<option value="">Sélectionnez une motorisation...</option>';
}

async function loadBrands(category = null) {
    try {
        let url = `${API_BASE}/vehicle_api.php?action=getBrands`;
        if (category) {
            url += `&category=${encodeURIComponent(category)}`;
        }
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success && data.brands) {
            const brandSelect = document.getElementById('brand');
            brandSelect.innerHTML = '<option value="">Sélectionnez une marque...</option>';
            
            data.brands.forEach(brand => {
                const option = document.createElement('option');
                option.value = brand.name;
                option.textContent = brand.name;
                option.dataset.brandId = brand.id;
                brandSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erreur chargement marques:', error);
        showToast('Erreur lors du chargement des marques', 'error');
    }
}

// ============================================
// Chargement des modèles pour une marque
// ============================================
async function loadModels(brandId) {
    const modelSelect = document.getElementById('model');
    modelSelect.disabled = true;
    modelSelect.innerHTML = '<option value="">Chargement...</option>';
    hideEngineSection();
    
    try {
        const response = await fetch(`${API_BASE}/vehicle_api.php?action=getModels&brand_id=${brandId}`);
        const data = await response.json();
        
        if (data.success && data.models) {
            modelSelect.innerHTML = '<option value="">Sélectionnez un modèle...</option>';
            
            data.models.forEach(model => {
                const option = document.createElement('option');
                option.value = model.name;
                option.textContent = model.name;
                option.dataset.modelId = model.id;
                modelSelect.appendChild(option);
            });
            
            modelSelect.disabled = false;
        }
    } catch (error) {
        console.error('Erreur chargement modèles:', error);
        modelSelect.innerHTML = '<option value="">Erreur de chargement</option>';
    }
}

async function loadEngines(modelId) {
    hideEngineSection();
    if (!modelId) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/vehicle_api.php?action=getEngines&model_id=${modelId}`);
        const data = await response.json();

        if (!data.success || !data.engines || data.engines.length === 0) {
            return;
        }

        const section = document.getElementById('section-engine');
        const engineSelect = document.getElementById('select-engine');
        engineSelect.innerHTML = '<option value="">Sélectionnez une motorisation...</option>';

        data.engines.forEach(engine => {
            const option = document.createElement('option');
            option.value = engine.label;
            option.textContent = engine.label;
            option.dataset.fuel = engine.fuel_type || '';
            engineSelect.appendChild(option);
        });

        section.style.display = '';
    } catch (error) {
        console.error('Erreur chargement motorisations:', error);
    }
}

// ============================================
// Remplissage des années
// ============================================
function populateYears() {
    const yearSelect = document.getElementById('year');
    const currentYear = new Date().getFullYear();
    
    yearSelect.innerHTML = '<option value="">Sélectionnez une année...</option>';
    
    for (let year = currentYear + 1; year >= 1980; year--) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        yearSelect.appendChild(option);
    }
}

// ============================================
// Configuration des listeners
// ============================================
function setupFormListeners() {
    // Changement de marque → charger les modèles
    document.getElementById('brand').addEventListener('change', (e) => {
        const selectedOption = e.target.selectedOptions[0];
        const brandId = selectedOption?.dataset?.brandId;
        
        if (brandId) {
            loadModels(brandId);
        } else {
            resetModelAndEngine();
        }
        validateManualForm();
    });

    document.getElementById('model').addEventListener('change', (e) => {
        const modelId = e.target.selectedOptions[0]?.dataset?.modelId;
        if (modelId) {
            loadEngines(modelId);
        } else {
            hideEngineSection();
        }
        validateManualForm();
    });
    
    ['year', 'select-engine'].forEach(id => {
        document.getElementById(id).addEventListener('change', validateManualForm);
    });
    
    const licensePlateForm = document.getElementById('licensePlateForm');
    if (licensePlateForm) {
        licensePlateForm.addEventListener('submit', handleLicenseLookup);
    }
    
    // Soumission formulaire manuel
    document.getElementById('manualSelectionForm').addEventListener('submit', handleManualSave);
    
    const licensePlateInput = document.getElementById('licensePlate');
    if (licensePlateInput) {
        licensePlateInput.addEventListener('input', formatLicensePlate);
    }
}

// ============================================
// Formatage automatique de la plaque
// ============================================
function formatLicensePlate(e) {
    let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    
    // Format XX-NNN-XX
    if (value.length > 2) {
        value = value.slice(0, 2) + '-' + value.slice(2);
    }
    if (value.length > 6) {
        value = value.slice(0, 6) + '-' + value.slice(6);
    }
    if (value.length > 9) {
        value = value.slice(0, 9);
    }
    
    e.target.value = value;
}

// ============================================
// Validation du formulaire manuel
// ============================================
function validateManualForm() {
    const brand = document.getElementById('brand').value;
    const model = document.getElementById('model').value;
    const year = document.getElementById('year').value;
    
    const submitBtn = document.querySelector('.btn-save');
    submitBtn.disabled = !(brand && model && year);
}

// ============================================
// Recherche par plaque d'immatriculation
// ============================================
async function handleLicenseLookup(e) {
    e.preventDefault();
    
    const plate = document.getElementById('licensePlate').value;
    if (!plate || plate.length < 7) {
        showToast('Veuillez entrer une plaque valide', 'warning');
        return;
    }
    
    showLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/vehicle_api.php?action=lookup`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ license_plate: plate })
        });
        
        const data = await response.json();
        
        if (data.success && data.found) {
            displayLookupResult(data);
        } else {
            showToast('Véhicule non trouvé', 'error');
        }
    } catch (error) {
        console.error('Erreur lookup:', error);
        showToast('Erreur lors de la recherche', 'error');
    } finally {
        showLoading(false);
    }
}

// ============================================
// Affichage du résultat de recherche par plaque
// ============================================
function displayLookupResult(data) {
    const resultDiv = document.getElementById('lookupResult');
    const vehicle = data.vehicle;
    
    resultDiv.innerHTML = `
        <div class="lookup-card">
            <div class="lookup-header">
                <span class="lookup-icon">✓</span>
                <span>Véhicule trouvé !</span>
            </div>
            <div class="lookup-body">
                <div class="lookup-vehicle-name">
                    ${vehicle.brand} ${vehicle.model}
                </div>
                <div class="lookup-vehicle-details">
                    <span class="detail">${vehicle.year}</span>
                    <span class="detail">${vehicle.engine_type || ''}</span>
                    <span class="detail">${vehicle.engine_size || ''}</span>
                </div>
                <p class="lookup-plate">Plaque : ${data.license_plate}</p>
            </div>
            <button type="button" class="btn btn-primary btn-confirm-lookup" onclick="saveLookupVehicle()">
                <span class="btn-icon">✓</span>
                Confirmer ce véhicule
            </button>
        </div>
    `;
    
    resultDiv.classList.remove('hidden');
    
    // Stocke les données pour la sauvegarde
    resultDiv.dataset.vehicle = JSON.stringify({
        license_plate: data.license_plate,
        ...vehicle
    });
}

// ============================================
// Sauvegarde du véhicule trouvé par plaque
// ============================================
async function saveLookupVehicle() {
    const resultDiv = document.getElementById('lookupResult');
    const vehicleData = JSON.parse(resultDiv.dataset.vehicle);
    
    await saveVehicle(vehicleData);
}

// ============================================
// Sauvegarde manuelle du véhicule
// ============================================
async function handleManualSave(e) {
    e.preventDefault();
    
    const engineSelect = document.getElementById('select-engine');
    const selectedEngineOption = engineSelect.selectedOptions[0];
    const formData = {
        brand: document.getElementById('brand').value,
        model: document.getElementById('model').value,
        year: document.getElementById('year').value,
        engine_label: engineSelect.value || null,
        fuel_type: selectedEngineOption?.dataset?.fuel || null,
        engine_size: document.getElementById('engineSize').value || null,
        transmission: document.getElementById('transmission').value || null
    };
    
    await saveVehicle(formData);
}

// ============================================
// Sauvegarde du véhicule (commun)
// ============================================
async function saveVehicle(vehicleData) {
    showLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/vehicle_api.php?action=save`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(vehicleData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showConfirmationModal(data.vehicle);
        } else {
            showToast(data.error || 'Erreur lors de l\'enregistrement', 'error');
        }
    } catch (error) {
        console.error('Erreur sauvegarde:', error);
        showToast('Erreur lors de l\'enregistrement', 'error');
    } finally {
        showLoading(false);
    }
}

// ============================================
// Affichage de la modal de confirmation
// ============================================
function showConfirmationModal(vehicle) {
    const modal = document.getElementById('confirmationModal');
    const vehicleInfo = document.getElementById('savedVehicleInfo');
    
    vehicleInfo.innerHTML = `
        <div class="saved-vehicle-card">
            <span class="saved-icon">🚗</span>
            <span class="saved-name">${vehicle.brand} ${vehicle.model}</span>
            <span class="saved-year">${vehicle.year}</span>
        </div>
    `;
    
    modal.classList.remove('hidden');
    
    // Ferme la modal si on clique sur l'overlay
    modal.querySelector('.modal-overlay').addEventListener('click', () => {
        modal.classList.add('hidden');
    });
}

// ============================================
// Fonctions utilitaires (définies dans app.js, mais fallback ici)
// ============================================
function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.toggle('visible', show);
    }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('visible'), 100);
    setTimeout(() => {
        toast.classList.remove('visible');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

