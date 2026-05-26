# Suivi d’intégration Managio ↔ Wekonex (équipe back-office)

> **Document miroir** — contenu aligné avec le suivi côté front communautaire.  
> **Source synchronisée** : `wekonex/docs/INTEGRATION-MANAGIO-PHASES.md`  
> Lors d’une mise à jour majeure, modifier les **deux** fichiers pour garder la cohérence.

**Positionnement**

| Plateforme | Rôle | Dépôt |
|------------|------|--------|
| **Wekonex** | Front communautaire (membres, events, adhésions) | `/Users/borix102/wekonex` |
| **Managio** | Back-office CRM / facturation / opérations (**Perfex CRM 3.4**) | `/Users/borix102/Documents/GitHub/managio` |

**Principe** : API + SSO — **ne pas** fusionner les bases SQL ni modifier le core Perfex en profondeur. Extension via module `wekonex_bridge` + API **PerfexGo** (`perfex_mobile_companion`).

**Dernière mise à jour** : 2026-05-25  
**Statut global** : 🟢 Phase 1 implémentée — tests E2E à valider en prod

---

## Focus équipe Managio

| Vous gérez surtout | Wekonex gère surtout |
|--------------------|----------------------|
| Module `modules/wekonex_bridge/` | Jobs, `ManagioClient`, routes `integrations` |
| Clients / contacts / factures / paiements | Inscription, profils alumni, checkout |
| SSO consume + session staff / contact | Émission jeton SSO |
| Hooks `before_client_added`, groupes, champs custom | Events Laravel après inscription / paiement |
| Compte staff API + clé `X-API-KEY` + JWT | `.env` `MANAGIO_*` |

**Stack Managio** : CodeIgniter 3.1.11 · PHP 8.1+ · API REST = module **PerfexGo** (JWT + `X-API-KEY`).

---

## Légende des statuts

| Symbole | Signification |
|---------|----------------|
| ⬜ | À faire |
| 🟡 | En cours |
| ✅ | Terminé |
| ⏸️ | En pause / bloqué |
| ❌ | Annulé |

---

## Vue d’ensemble des phases

| Phase | Objectif | Statut | Cible |
|-------|----------|--------|-------|
| **0** | Fondations (module bridge, config, API, health check) | ✅ | 2026-05-25 |
| **1** | SSO + sync contacts + sync factures / paiements | ✅ | 2026-05-26 |
| **2** | Events → CRM, webhooks, notifications | ⬜ | — |
| **3** | IA, scoring, automatisations diaspora | ⬜ | — |

---

## Phase 0 — Fondations

**Objectif** : module installable, API testée, pas de changement UX Perfex visible.

| # | Tâche | Repo | Statut | Responsable | Notes |
|---|--------|------|--------|-------------|-------|
| 0.1 | Valider spec mapping (`clients` / `contacts` / `invoices`) | Managio | ✅ | | [INTEGRATION-WEKONEX-ENTITY-MAPPING.md](./INTEGRATION-WEKONEX-ENTITY-MAPPING.md) — canonique : `wekonex/docs/INTEGRATION-MANAGIO-ENTITY-MAPPING.md` v1.0 |
| 0.2 | Tables mapping côté Wekonex (`integration_mappings`) | Wekonex | ✅ | | Migration `2026_05_25_120000` + modèles Eloquent |
| 0.3 | Créer staff « Wekonex Integration » + clé API PerfexGo | **Managio** | ✅ | | Admin → Wekonex Bridge → Step 0.3 · [MANAGIO-API-SETUP.md](./MANAGIO-API-SETUP.md) |
| 0.4 | Tester JWT + `X-API-KEY` (login API v1) | **Managio** | ✅ | | Bouton « Run PerfexGo API test » · `Wekonex_perfex_api` |
| 0.5 | Scaffold `modules/wekonex_bridge/` | **Managio** | ✅ | | `modules/wekonex_bridge/` |
| 0.6 | Options : URL Wekonex, secret HMAC SSO | **Managio** | ✅ | | Admin → Setup → Wekonex Bridge |
| 0.7 | Health check (appel entrant depuis Wekonex) | Wekonex | ✅ | | `php artisan wekonex:managio:health --ping-bridge` |
| 0.8 | Auth Wekonex (Sanctum) | Wekonex | ✅ | | `auth:sanctum` sur `/api/user` |
| 0.9 | Logs + idempotency webhooks | Les deux | ✅ | | `wekonex_sync_logs` · idempotency tables |
| 0.10 | Audit module **saas** (isolation tenant) | **Managio** | ✅ | | [INTEGRATION-SAAS-AUDIT.md](./INTEGRATION-SAAS-AUDIT.md) |

**Critère de fin Phase 0** : Wekonex reçoit une réponse 200 sur un endpoint test du module bridge ou PerfexGo.

---

## Phase 1 — Priorités business

### 1.1 Single Sign-On (SSO)

| # | Tâche | Repo | Statut | Responsable | Notes |
|---|--------|------|--------|-------------|-------|
| 1.1.1 | Émission token SSO | Wekonex | ✅ | | `GET admin/managio/open` |
| 1.1.2 | Jeton one-time HMAC (TTL 60–120 s) | Wekonex | ✅ | | Secret partagé dans module |
| 1.1.3 | **`wekonex_bridge/controllers/Auth.php`** — `consume` | **Managio** | ✅ | | Session staff / portail |
| 1.1.4 | Mapping rôle → staff limité ou contact portail | **Managio** | ✅ | | E-mail staff ou contact mappé |
| 1.1.5 | Bouton « Ouvrir Managio » | Wekonex | ✅ | | Dashboard admin |
| 1.1.6 | Journal SSO + rate limit | **Managio** | ✅ | | `wekonex_sync_logs` + RateLimiter Wekonex |

**Critère de fin 1.1** : URL `managio.africa/.../wekonex_bridge/auth/consume?token=...` ouvre une session valide.

---

### 1.2 Synchronisation membres → contacts

| # | Tâche | Repo | Statut | Responsable | Notes |
|---|--------|------|--------|-------------|-------|
| 1.2.1–1.2.4 | Jobs + hooks inscription (Wekonex) | Wekonex | ✅ | | `SyncMemberToManagioJob` + hooks |
| 1.2.5 | Webhook **`member.upsert`** | **Managio** | ✅ | | `Wekonex_sync_model` |
| 1.2.6 | Groupes clients par association (`tenant_id`) | **Managio** | ✅ | | `WEKONEX-TENANT-*` |
| 1.2.7 | Champs personnalisés Wekonex | **Managio** | ✅ | | `install.php` / `upgrade.php` |
| 1.2.8 | Retry côté Wekonex | Wekonex | ✅ | | Jobs 3× · mapping `failed` |

**Tables Perfex concernées** : `{prefix}clients`, `{prefix}contacts`, `{prefix}customfieldsvalues`, `{prefix}customer_groups`.

**Critère de fin 1.2** : contact visible dans **Clients** avec champs custom Wekonex remplis.

---

### 1.3 Synchronisation paiements → factures

| # | Tâche | Repo | Statut | Responsable | Notes |
|---|--------|------|--------|-------------|-------|
| 1.3.1–1.3.2 | Déclenchement après paiement Wekonex | Wekonex | ✅ | | `OrderController::verify` |
| 1.3.3 | Création facture adhésion | **Managio** | ✅ | | `payment.record` |
| 1.3.4 | Création facture event | **Managio** | ✅ | | Même action |
| 1.3.5 | Création facture don (`ALUDONATION`) | **Managio** | ✅ | | `payment_type=donation` · dons invités |
| 1.3.6 | Idempotency (ref externe `wekonex_payment_id`) | **Managio** | ✅ | | `wekonex_entity_mappings` |
| 1.3.7 | **`Payments_model`** — enregistrement paiement | **Managio** | ✅ | | `record_perfex_payment()` + `update_invoice_status` |
| 1.3.8 | Toutes passerelles (Maviance, Maxicash, etc.) | Wekonex | ✅ | | Observer `Payment` → `managioSyncPayment()` |

**Critère de fin 1.3** : facture + paiement liés au bon `contact` / `client`.

**Critère de fin Phase 1** : parcours complet testé avec compte staff + client test.

---

## Phase 2 — CRM & expérience unifiée

| # | Tâche | Repo | Statut | Responsable | Notes |
|---|--------|------|--------|-------------|-------|
| 2.1 | Notes / activités sur contact (event Wekonex) | **Managio** | ⬜ | | API ou hook |
| 2.2 | Tags diaspora, segments | **Managio** | ⬜ | | |
| 2.3 | Leads / conversion | **Managio** | ⬜ | | `Leads_model` |
| 2.4 | Exposer KPIs via API (lecture Wekonex) | **Managio** | ⬜ | | PerfexGo ou `Custom_api` v3 |
| 2.5 | Workflows email / SMS (Twilio) | **Managio** | ⬜ | | |
| 2.6 | Webhook sortant statut facture → Wekonex | **Managio** | ⬜ | | Optionnel |

**Critère de fin Phase 2** : campagne / event traceable dans la fiche contact.

---

## Phase 3 — Intelligence & scale

| # | Tâche | Repo | Statut | Responsable | Notes |
|---|--------|------|--------|-------------|-------|
| 3.1 | Rapports / exports croisés | **Managio** | ⬜ | | Module `exports` |
| 3.2 | Cron + automatisations diaspora | **Managio** | ⬜ | | `Cron.php` |
| 3.3 | API mobile (PerfexGo existant) | **Managio** | ⬜ | | |
| 3.4 | Module **openai** + règles métier | **Managio** | ⬜ | | |
| 3.5 | Suppression contact si demande RGPD Wekonex | **Managio** | ⬜ | | Hook dédié |

---

## Sprints suggérés

| Sprint | Focus Managio | Statut | Date début | Date fin |
|--------|---------------|--------|------------|----------|
| S1 | Staff API + module bridge vide + install | ⬜ | | |
| S2 | SSO `consume` + tests session | ⬜ | | |
| S3 | Upsert contact + champs custom | ⬜ | | |
| S4 | Facture + paiement adhésion | ⬜ | | |
| S5 | Notes event + webhooks | ⬜ | | |
| S6 | Endpoints KPI pour dashboard Wekonex | ⬜ | | |

---

## Mapping entités (référence Perfex)

**Spec complète (Phase 0.1)** : [INTEGRATION-WEKONEX-ENTITY-MAPPING.md](./INTEGRATION-WEKONEX-ENTITY-MAPPING.md)  
**Canonique** : `wekonex/docs/INTEGRATION-MANAGIO-ENTITY-MAPPING.md`

| Wekonex | Managio (Perfex) | Table / PK |
|---------|------------------|------------|
| `Tenant` | Client organisation | `clients.userid` |
| `User` + `Alumni` | Contact | `contacts.id` → `contacts.userid` |
| `Payment` | Facture + paiement | `invoices.id`, `invoicepaymentrecords` |
| Event + inscription | Note sur contact | — |
| SaaS package | Client + abonnement | Module `saas` si actif |

---

## Structure de code — Managio (à livrer)

```text
modules/wekonex_bridge/
  wekonex_bridge.php          # En-tête module Perfex
  install.php                 # Tables optionnelles + champs custom
  config/wekonex.php
  controllers/
    Auth.php                  # SSO consume
    Webhook.php               # Endpoints entrants Wekonex
  models/
    Wekonex_mapping_model.php
  hooks.php                   # before_client_added, after_invoice_added, ...
```

**Ne pas modifier** : `application/controllers/*` core, `Clients_model` sauf via hooks.

**Réutiliser** : `modules/perfex_mobile_companion/controllers/v1/Customers.php`, `Contacts.php`, `Invoices.php`, `Payments.php`.

---

## Checklist déploiement Managio

- [ ] Module `wekonex_bridge` activé dans **Setup → Modules**
- [ ] Compte staff API créé, clé stockée côté Wekonex (`.env` uniquement)
- [ ] Champs custom Wekonex créés (Clients + Contacts)
- [ ] Secret SSO configuré (identique des deux côtés)
- [ ] Cron `APP_CRON_KEY` opérationnel (sync différée si besoin)
- [ ] Backup BDD avant première sync masse

---

## Journal des décisions

| Date | Décision | Impact |
|------|----------|--------|
| 2026-05-25 | Architecture API + SSO (pas fusion BDD) | Plan validé |
| 2026-05-25 | Copie symétrique doc dans dépôt Managio | Suivi back-office |
| 2026-05-25 | Phase 0.1 : validation spec mapping entités v1.0 | |
| 2026-05-25 | Phase 0.2 : tables intégration côté Wekonex | |
| 2026-05-25 | Phase 0.3 Wekonex : config + variables `.env` documentées | |
| 2026-05-25 | Phase 0.5–0.10 : module bridge, settings, logs, audit SaaS | |
| 2026-05-25 | Phase 0.3–0.4 : staff API + test PerfexGo (UI + doc) | |
| | | |

---

## Blocages & risques (vue Managio)

| ID | Description | Gravité | Mitigation | Statut |
|----|-------------|---------|------------|--------|
| R1 | Module **saas** actif | Haute | Valider tenant avant sync | ⬜ Ouvert |
| R3 | Pas de SSO natif Perfex | Moyenne | Module `wekonex_bridge` uniquement | ⬜ Ouvert |
| R5 | PerfexGo non maintenu | Moyenne | Encapsuler dans module bridge | ⬜ Ouvert |
| R6 | Modification core Perfex | Haute | **Interdit** — hooks + module seulement | ⬜ Ouvert |

---

## Tests de recette (côté Managio)

- [ ] Login API PerfexGo (staff intégration) → JWT valide
- [ ] `POST` upsert contact (payload test Wekonex) → contact créé / mis à jour
- [ ] SSO consume → session admin ou portail client
- [ ] Facture créée avec référence `wekonex_payment_id` unique
- [ ] Second appel même `payment_id` → pas de doublon facture
- [ ] Note activité visible sur fiche contact (event test)
- [ ] Logs module consultables en cas d’échec

---

## Historique des mises à jour

| Date | Auteur | Changement |
|------|--------|------------|
| 2026-05-25 | — | Création copie symétrique (équipe back-office) |

---

## Liens utiles

| Ressource | Emplacement |
|-----------|-------------|
| Suivi miroir (front) | `wekonex/docs/INTEGRATION-MANAGIO-PHASES.md` |
| Spec mapping entités | `wekonex/docs/INTEGRATION-MANAGIO-ENTITY-MAPPING.md` (résumé : [INTEGRATION-WEKONEX-ENTITY-MAPPING.md](./INTEGRATION-WEKONEX-ENTITY-MAPPING.md)) |
| Setup API PerfexGo (0.3–0.4) | [MANAGIO-API-SETUP.md](./MANAGIO-API-SETUP.md) |
| API REST | `modules/perfex_mobile_companion/` (PerfexGo v2.1) |
| Config app | `application/config/app-config.php` |
| Hooks Perfex | `application/models/Clients_model.php`, `application/helpers/modules_helper.php` |
| Enregistrement routes API custom | `register_api_route()` dans `perfex_mobile_companion_helper.php` |
