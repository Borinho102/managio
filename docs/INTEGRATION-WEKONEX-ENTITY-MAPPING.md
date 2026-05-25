# Spécification de mapping entités — Managio ↔ Wekonex (validation back-office)

**Version** : 1.0  
**Date** : 2026-05-25  
**Statut** : ✅ Validé (Phase 0.1)

> **Document canonique** (rédaction détaillée) :  
> `wekonex/docs/INTEGRATION-MANAGIO-ENTITY-MAPPING.md`  
> Les deux fichiers doivent rester **synchronisés** (même numéro de version).

---

## Validation Managio — Phase 0.1

| # | Point | Résultat |
|---|-------|----------|
| V1 | Tables `{prefix}clients`, `{prefix}contacts` (Perfex 3.4) | ✅ Conforme |
| V2 | Champs custom `customers` / `contacts` / `invoice` | ✅ Réalisable via `Custom_fields_model` |
| V3 | API **PerfexGo** : `v1/Customers`, `Contacts`, `Invoices`, `Payments` | ✅ Suffisant Phase 1 |
| V4 | Email contact unique **par client** (`userid`) | ✅ Compatible multi-association Wekonex |
| V5 | Idempotency factures via `wekonex_payment_id` | ✅ Validé |
| V6 | Module **saas** Managio | ✅ Audit [INTEGRATION-SAAS-AUDIT.md](./INTEGRATION-SAAS-AUDIT.md) |
| V7 | Extension sans modifier le core | ✅ Module `wekonex_bridge` + hooks uniquement |

**Référence code** : `application/models/Clients_model.php`, `modules/perfex_mobile_companion/controllers/v1/`.

---

## Résumé cardinalité (Perfex)

```text
1 Client (company = association Wekonex)
   └── N Contacts (membres)
         └── Factures / Paiements / Notes
```

| Wekonex | Perfex | PK Perfex |
|---------|--------|-----------|
| `tenants.id` | `clients` | `userid` |
| `users.id` + `tenant_id` | `contacts` | `id` (+ `userid`) |
| `payments.id` (paid) | `invoices` + `invoicepaymentrecords` | `id` |

---

## Champs custom à provisionner (Managio)

Voir section **§ 8** du document canonique. Priorité installation :

1. `wekonex_tenant_id` sur **customers**
2. `wekonex_user_id`, `wekonex_tenant_id` sur **contacts**
3. `wekonex_payment_id` sur **invoice** (unique)

---

## Groupes clients recommandés

| Groupe | Usage |
|--------|-------|
| `WEKONEX-ASSOCIATION` | Toutes les orgs synchronisées |
| `WEKONEX-TENANT-{id}` | Segmentation par association |
| `WEKONEX-MEMBER` / `WEKONEX-ADMIN` / `WEKONEX-BOARD` | Segmentation par rôle |

---

## API PerfexGo (opérations Phase 1)

| Opération | Controller |
|-----------|------------|
| Créer / lire client | `v1/Customers.php` |
| Créer / MAJ contact | `v1/Contacts.php` |
| Créer facture | `v1/Invoices.php` |
| Enregistrer paiement | `v1/Payments.php` |

Extensions SSO / upsert : **`modules/wekonex_bridge/`** (Phase 0.5).

---

## Mapping rôles → SSO (aperçu)

| `wekonex_role` | Session Managio cible |
|----------------|----------------------|
| `admin` | Staff limité ou contact privilégié |
| `alumni` | Contact portail |
| `board_member` | Contact + tag |
| `super_admin` | Staff restreint (à définir en 1.1.4) |

---

## Spec complète

Le mapping champ par champ, payloads JSON, idempotency et cas limites sont dans :

**`wekonex/docs/INTEGRATION-MANAGIO-ENTITY-MAPPING.md`** (§ 3 à 13).

---

## Historique

| Version | Date | Changement |
|---------|------|------------|
| 1.0 | 2026-05-25 | Validation Phase 0.1 — copie symétrique |
