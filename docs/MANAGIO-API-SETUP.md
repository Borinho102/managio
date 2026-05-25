# Configuration API PerfexGo — Phase 0.3 & 0.4 (Managio)

**Objectif** : compte staff dédié + validation `login_api` (JWT + `X-API-KEY`).

---

## Prérequis

- Module **Perfex Mobile Companion** (`perfex_mobile_companion`) actif
- Module **Wekonex Bridge** actif (réactiver une fois si la section API n’apparaît pas après mise à jour)
- Droits administrateur Managio

---

## Étape 0.3 — Créer le staff « Wekonex Integration »

1. **Setup → Wekonex Bridge** (ou `admin/wekonex_bridge/settings`)
2. Section **PerfexGo API — Wekonex Integration**
3. Renseigner :
   - Email : `integration@wekonex.net` (ou votre domaine)
   - Mot de passe : fort, dédié (non utilisé par un humain)
4. Cliquer **Create or update staff**

Le staff reçoit les permissions CRM nécessaires (clients, factures, paiements, devis, articles).

Options enregistrées :

| Option Perfex | Description |
|---------------|-------------|
| `wekonex_bridge_api_staff_id` | ID staff |
| `wekonex_bridge_api_staff_email` | Email |
| `wekonex_bridge_api_staff_password` | Mot de passe (admin uniquement) |

---

## Étape 0.4 — Tester JWT + X-API-KEY

1. Même page, section **Step 0.4**
2. Saisir email + mot de passe (si besoin)
3. Cliquer **Run PerfexGo API test**

Le test exécute :

1. `POST /perfex_mobile_companion/v1/login/login_api` → récupère `key` + `token`
2. `GET /perfex_mobile_companion/v1/customers/data` avec headers :
   - `X-API-KEY: {key}`
   - `Authorization: {jwt}`

En cas de succès :

| Option | Contenu |
|--------|---------|
| `wekonex_bridge_api_key` | Clé API Perfex |
| `wekonex_bridge_api_token` | JWT |
| `wekonex_bridge_api_last_test_status` | `ok` |

Journal : table `{prefix}wekonex_sync_logs`, action `perfexgo_api_test`.

---

## Copier vers Wekonex (.env)

```env
MANAGIO_ENABLED=true
MANAGIO_BASE_URL=https://managio.africa
MANAGIO_API_EMAIL=integration@wekonex.net
MANAGIO_API_PASSWORD=<same password>
# Optionnel si test OK :
MANAGIO_API_KEY=<from wekonex_bridge_api_key option>
MANAGIO_API_TOKEN=<from wekonex_bridge_api_token option>
```

Puis sur Wekonex :

```bash
php artisan wekonex:managio:health
```

---

## Dépannage

| Problème | Piste |
|----------|--------|
| Credentials not matched | Email/mot de passe ; staff `active=1` |
| Invalid API key | Relancer test 0.4 pour régénérer clé |
| Token expired | Relancer login (Wekonex cache 50 min) |
| CSRF sur login_api | Vérifier `csrf_exclude_uris` inclut `perfex_mobile_companion` (install PerfexGo) |

---

## Historique

| Date | Changement |
|------|------------|
| 2026-05-25 | Guide Phase 0.3 / 0.4 + UI module wekonex_bridge |
