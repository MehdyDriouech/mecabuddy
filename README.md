# 🔧 MecaBuddy - Ton Compagnon Mécanique Intelligent

![MecaBuddy](https://img.shields.io/badge/version-1.0.0-orange?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0+-blue?style=for-the-badge&logo=php)
![SQLite](https://img.shields.io/badge/SQLite-défaut-003B57?style=for-the-badge&logo=sqlite)
![MySQL](https://img.shields.io/badge/MySQL-optionnel-4479A1?style=for-the-badge&logo=mysql)

**MecaBuddy** est une application web (PHP sans Composer ni framework) qui aide à entretenir et réparer un véhicule : tutoriels personnalisés, assistant **Buddy** avec prise en charge **LLM** optionnelle (Ollama, compatible OpenAI / **Google Gemini**, **Mistral AI**), recherche web (Serper ou DuckDuckGo HTML), et couche de sécurité mécanique.

---

## ✨ Fonctionnalités

### 🚗 Gestion des véhicules
- Recherche par plaque (selon configuration et fournisseur externe)
- Sélection manuelle : marque, modèle, année (+ options)
- Persistance en **SQLite** (par défaut), avec **MySQL en secours** si `USE_MYSQL` est activé et que SQLite échoue. **Mode mock** (`mock_db.php` / `MockDatabase`) lorsque la base persistante n’est pas considérée comme disponible (fichier SQLite inaccessible, échec de connexion, ou **mode démo** activé dans les paramètres)

### 📖 Tutoriels interactifs
- Génération **LLM** (JSON structuré) si un fournisseur actif est configuré, avec **repli** sur gabarits PHP statiques
- Flux **SSE** (`api/tutorial_stream.php`) : phases temps réel (`vehicle` → `search` → `search_done` → `llm` → `saving` → `done`) avec anti-buffering (padding SSE) ; repli **POST** `tutorial_api.php?action=generate`
- **Overlay de chargement** (`tutorial.php`) : messages fixes par phase + **messages humoristiques rotatifs** pendant la phase LLM (fallback si événements SSE retardés) ; nettoyage des timers à `done` / `error`
- **Pipeline LLM robuste** (`includes/llm_tutorial.php`) :
  - Recherche web en cascade (jusqu’à 3 requêtes) avant l’appel modèle
  - **Passe 1** : métadonnées + outils/pièces (`num_predict` 8192, `format: json`)
  - **Passe 2** (si JSON tronqué avec titre mais sans `steps`) : génération des étapes seules (`tutorial_fetch_llm_steps`)
  - Nettoyage JSON (pollution LLM, sauts de ligne dans les valeurs numériques, réparation de troncature)
  - Ollama **gemma4** : `think: false` + repli extraction depuis `message.thinking` si `content` vide
- Suggestions d’opérations (vidange, plaquettes, batterie…)
- Personnalisation selon le véhicule en session (marque/modèle/année dans le message ; catégorie voiture/moto via le prompt système)
- **Safety layer** : alertes pour opérations sensibles

### 💬 Buddy Mode (diagnostic)
- Chat multi-tour avec **LLM** si configuré, sinon réponses par motifs (`BuddyBrain`)
- Ollama : `think: false` (gemma4) ; si `content` vide, extraction de la réponse depuis `message.thinking` (`_mecabuddyExtractOllamaContent`)
- Contexte véhicule injecté dans le prompt système (`vehicle_context.php`) quand la fiche existe en session
- **Recherche web** optionnelle pour enrichir les réponses : **Serper** (si `serper_api_key`) puis repli **DuckDuckGo HTML** (sans clé) — voir `includes/search_helpers.php` + `includes/llm_chat.php`
- Requête de recherche enrichie par marque / modèle / année du véhicule ; blacklist domaines (fiches techniques, annonces…) ; filtrage best-effort des résultats hors-marque
- **Mode failsafe** : si aucune source web fiable, le LLM répond sans contexte web (tutoriels et Buddy)
- Diagnostic recherche : `mecabuddy_probe_web_search()` ; panneau debug détaillé si `APP_DEBUG` ou `debug_panel`
- Sources web affichées sous les bulles Buddy (`sources[]`) : bloc `[SOURCES]` du LLM, liens markdown, ou repli sur les URLs Serper/DDG
- Historique des conversations persisté (SQLite / mock), sources sauvegardées dans le `context` JSON
- En mode debug (`APP_DEBUG`), champ `debug` sur `action=ask` : `web_searched`, `search_provider`, `sources_raw_count`, etc.

### 🔑 Mode démo & BYOK (optionnel)
- **Authentification démo** (`includes/demo_auth.php`) : comptes seed, quotas journaliers tutoriel / Buddy ; page `public/login.php`
- **BYOK** (`includes/byok.php`) : clé LLM personnelle chiffrée (ex. Gemini) ; paramètres dans `public/account-settings.php` ; erreurs explicites (`byok_provider_invalid`, quota)

### ⚙️ Administration POC (`public/dev.php`, si `APP_DEBUG`)
- Paramètres applicatifs dans **`config/settings.json`** (clé API plaque, fournisseurs LLM avec `api_key`, Serper, mode démo, etc.) — **ne pas versionner** (`config/.gitignore`) ; modèle : `config/settings.example.json`
- Fournisseurs LLM : types **ollama**, **openai_compatible** (Gemini Google AI Studio, OpenAI-like), **mistral** (endpoint fixe `api.mistral.ai`) ; champ optionnel **`chat_path`** (ex. Gemini : `/chat/completions`)
- Section **Recherche web** : clé Serper optionnelle (recommandée en production / hébergement mutualisé), avertissements si `curl` / `dom` manquants ; bouton **Tester** (`GET test_search?q=…`)
- Tests plaque / connexion LLM / recherche web via `api/dev_api.php`
- **Panneau trace debug** (`includes/debug_panel.php`) sur **`tutorial.php`** et **`diagnostic.php`** : activable dans **`dev.php`** (`debug_panel` dans `settings.json`) ; requiert **`APP_DEBUG`** + toggle ; traces overlay tutoriel + SSE ; onglets Frontend / Server (tail `error_log`)

---

## 🛠️ Prérequis

- **Serveur web** : Apache (ou équivalent) avec **PHP 8.0+**
- **Base de données** :
  - **SQLite** (par défaut) : fichier `data/mecabuddy.sqlite`, créé automatiquement au premier accès ; schéma `sql/schema_sqlite.sql`
  - **MySQL / MariaDB** (optionnel) : définir `USE_MYSQL` à `true` dans `config/config.php` et importer `sql/schema.sql`
- **Extensions PHP** : `pdo`, `pdo_sqlite`, `json`, `mbstring`, `curl` (LLM / recherche web / tests), **`dom`** (parsing DuckDuckGo HTML — requis sans Serper) ; `pdo_mysql` uniquement si MySQL est utilisé
- **Hébergement mutualisé** : DuckDuckGo HTML est souvent bloqué ou non parsable → configurer **Serper** pour des sources fiables ; le mode failsafe LLM reste actif sans moteur web
- **Hébergement type** : AMPPS, XAMPP, WAMP, MAMP, etc.

---

## 📦 Installation

### 1. Téléchargement

Placez le projet dans la racine web (exemple AMPPS) :

```bash
cd "C:\Program Files\Ampps\www"
git clone <url-du-repo> mecabuddy
```

### 2. Configuration

Éditez **`config/config.php`** :

- **`BASE_URL`**, **`PUBLIC_URL`**, **`API_URL`** : détectés automatiquement depuis `DOCUMENT_ROOT` et `HTTP_HOST` (ex. `http://localhost/mecabuddy`, `…/public`, `…/api`) — plus de chemin hardcodé
- **`USE_MYSQL`** : à `false`, seul SQLite est tenté (puis mock si la base est indisponible selon `isDatabaseAvailable()`). À `true`, **MySQL est utilisé en secours** si la connexion SQLite échoue
- Identifiants MySQL (`DB_*`) : uniquement utiles si `USE_MYSQL` est activé

Au premier chargement avec SQLite, le dossier **`data/`** et le fichier **`data/mecabuddy.sqlite`** sont créés ; le schéma est appliqué depuis **`sql/schema_sqlite.sql`**.

### 3. MySQL (optionnel)

Si vous utilisez MySQL :

```bash
mysql -u root -p < sql/schema.sql
```

Puis `USE_MYSQL` à `true` et identifiants `DB_*` corrects dans `config/config.php`.

### 4. Paramètres applicatifs (`settings.json`)

Les réglages (LLM, plaque, Serper, mode démo…) sont lus/écrits dans **`config/settings.json`** (créé avec des valeurs par défaut si absent, via `config/settings.php`). Ce fichier est listé dans **`config/.gitignore`** : ne jamais committer de secrets (`api_key` Mistral/OpenAI, Serper, plaque).

Chaque fournisseur LLM dans `llm_providers[]` peut inclure : `id`, `name`, `type`, `base_url`, `model`, `api_key` (vide pour Ollama local), `active`, `chat_path?` (openai_compatible / Gemini).

Exemple Gemini (sans clé réelle) :

```json
{
  "type": "openai_compatible",
  "base_url": "https://generativelanguage.googleapis.com/v1beta/openai",
  "model": "gemini-2.5-flash-lite",
  "chat_path": "/chat/completions",
  "api_key": "VOTRE_CLE"
}
```

### 5. Permissions

Le serveur doit pouvoir écrire dans **`data/`** (SQLite) et dans **`config/`** (écriture de `settings.json` depuis la page dev).

```bash
chmod -R 755 mecabuddy/
chmod 775 mecabuddy/data mecabuddy/config
```

---

## 🚀 Démarrage

1. Démarrez Apache (et MySQL seulement si vous l’utilisez).
2. Ouvrez : `http://localhost/mecabuddy/public/` (adapter selon `BASE_URL`).
3. Tests manuels des APIs : `http://localhost/mecabuddy/public/test_api.php`
4. Admin POC (si `APP_DEBUG` est `true`) : `http://localhost/mecabuddy/public/dev.php`

---

## 📁 Structure du projet

```
mecabuddy/
├── api/
│   ├── diagnostic_api.php
│   ├── dev_api.php              # save_settings, get_settings, tests (APP_DEBUG)
│   ├── tutorial_api.php
│   ├── tutorial_stream.php      # Génération tutoriel SSE
│   ├── debug_log_stream.php     # Tail error_log PHP en SSE (APP_DEBUG uniquement)
│   └── vehicle_api.php
├── config/
│   ├── config.php
│   ├── db.php                   # SQLite → MySQL (optionnel) → mock
│   ├── db_sqlite.php
│   ├── mock_db.php
│   ├── settings.php             # Lecture / écriture settings.json
│   └── settings.json          # Généré localement (voir .gitignore)
├── data/                        # mecabuddy.sqlite (+ .gitignore)
├── includes/
│   ├── header.php, footer.php
│   ├── debug_panel.php          # Trace debug UI (APP_DEBUG uniquement)
│   ├── llm_bridge.php, llm_chat.php, llm_tutorial.php
│   ├── search_helpers.php         # Blacklist, diagnostic DDG, helpers debug recherche
│   ├── demo_auth.php, byok.php
│   ├── vehicle_context.php
│   ├── plate_lookup.php
│   └── safety_layer.php
├── public/
│   ├── assets/css, assets/js
│   ├── dev.php
│   ├── diagnostic.php, index.php, login.php, tutorial.php, vehicle.php
│   ├── account-settings.php       # BYOK utilisateur
│   └── test_api.php
├── sql/
│   ├── schema.sql               # MySQL
│   └── schema_sqlite.sql        # SQLite (POC)
├── context-llm.md               # Référence architecture LLM / recherche web
└── README.md
```

---

## 🔌 API (paramètre `action`)

### Vehicle API (`vehicle_api.php`)

| Méthode | Action | Description |
|---------|--------|---------------|
| GET | `current` | Véhicule courant (session) |
| GET | `brands` | Marques |
| GET | `models&brand_id=` | Modèles |
| POST | `save` | Enregistrer un véhicule |
| POST | `lookup` | Recherche plaque |
| POST | `clear` | Retirer le véhicule de la session |

### Tutorial API (`tutorial_api.php`)

| Méthode | Action | Description |
|---------|--------|---------------|
| GET | `suggestions` | Suggestions |
| GET | `get&id=` | Détail d’un tutoriel |
| GET | `list&limit=` | Liste récente |
| POST | `generate` | Génération (LLM 1–2 passes + fallback statique si échec) |

### Diagnostic API (`diagnostic_api.php`)

| Méthode | Action | Description |
|---------|--------|---------------|
| POST | `ask` | Message au Buddy ; réponse : `response`, `sources[]`, `provider`, `debug` (si `APP_DEBUG` + LLM) |
| GET | `history&limit=` | Historique (inclut `sources` par conversation) |
| POST | `clear` | Effacer l’historique |

### Dev API (`dev_api.php`, `APP_DEBUG` requis)

| Méthode | Action | Description |
|---------|--------|---------------|
| GET | `get_settings` | État courant (équivalent `getSettings()`) |
| POST | `save_settings` | Sauvegarde JSON des paramètres |
| GET | `test_plate` | Test API plaque |
| GET | `test_llm&provider_id=` | Test fournisseur LLM (« Réponds juste OK » ; 401/429 explicites) |
| GET | `test_search` | Sonde recherche (`?q=` optionnel, défaut plaquettes Peugeot 308) ; `success`, `provider`, `count`, `results`, `error`, `warnings[]`, `debug` (`curl_available`, `dom_available`, `http_status`, `raw_result_count`, `final_count`, `body_hint` si échec, etc.) — **jamais de clé API dans la réponse** |

---

## 🛡️ Safety layer

Analyse des textes (tutoriels, messages) pour repérer des opérations sensibles et afficher des avertissements.

- **High** : freins, airbags, carburant, climatisation…
- **Medium** : huile chaude, refroidissement, électricité…
- **Low** : filtres, opérations simples…

---

## 🎨 Design

- **Mobile-first**, thème sombre
- **Palette** (extraits) : primary `#FF6B35`, fond `#1A1A2E`, success `#00D9A5`, danger `#FF4757`

---

## 🧪 Tests

1. Ouvrir `public/test_api.php` et exécuter les requêtes de test.
2. Parcours manuel : véhicule → tutoriel → Buddy.
3. `php -l fichier.php` pour valider la syntaxe PHP.
4. **Buddy + recherche web** (avec `APP_DEBUG`) :
   - Configurer un fournisseur LLM actif dans `/dev`
   - Optionnel : clé Serper ; sans clé, DuckDuckGo est utilisé
   - Sur `/diagnostic`, envoyer un message technique (> 3 mots) ; vérifier dans l’onglet Réseau : `debug.web_searched`, `debug.search_provider` (`serper` | `duckduckgo`), `sources` non vide
5. **Recherche web seule** : `/dev` → section Recherche web → **Tester** → vérifier `debug.http_status`, `raw_result_count`, `final_count` ; en local AMPPS : `ssl_mode: disabled (dev)` dans `debug`
6. **Tutoriel SSE** : générer une vidange avec le panneau debug ; overlay : phases `vehicle` / `search` / messages fun pendant `llm` ; logs `SSE status reçu`, `Rotation messages fun démarrée`
7. **Tutoriel LLM** (gemma4 / Ollama / Gemini) : logs serveur attendus : passe 1, éventuelle passe 2 (`steps` manquants), `Tutoriel généré — N étapes`
8. **Failsafe web** : sans Serper sur hébergement bloqué → tutoriel généré quand même, badge failsafe visible

Voir aussi **`context-llm.md`** pour le détail des appels Ollama, du parsing JSON et des fallbacks.

### SSL en développement local

Si curl échoue sur certificats auto-signés (AMPPS), `_mecabuddyCurl()` dans `includes/llm_chat.php` désactive la vérification SSL **uniquement** lorsque `APP_DEBUG === true`. Ne jamais déployer en production avec `APP_DEBUG` activé.

---

## 🔮 Pistes d’évolution

- [x] LLM Buddy + tutoriels (Ollama / OpenAI-compatible / Gemini / Mistral via `settings.json` / page dev)
- [x] Recherche web (Serper + repli DuckDuckGo, diagnostic `test_search`, failsafe, sources UI)
- [x] Overlay tutoriel SSE (phases + messages humoristiques LLM)
- [x] BYOK + auth démo + quotas
- [ ] API SIV / plaque réelle selon contrats fournisseurs
- [ ] PWA / mode hors-ligne
- [ ] Comptes utilisateurs et auth
- [ ] Notifications / rappels d’entretien

---

## 📝 Licence

Projet éducatif — libre d’utilisation et de modification.

---

## 👨‍💻 Auteur

**MecaBuddy** — démonstration PHP (SQLite par défaut, MySQL optionnel, configuration JSON).

---

<p align="center">
  <strong>🔧 MecaBuddy v1.0.0</strong><br>
  <em>Ton compagnon mécanique intelligent</em>
</p>
