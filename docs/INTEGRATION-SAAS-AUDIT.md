# Audit module SaaS Managio — impact intégration Wekonex (Phase 0.10)

**Date** : 2026-05-25  
**Statut** : ✅ Documenté — actions requises avant sync production

**Module** : `modules/saas` (Perfect SaaS v1.2.4)  
**Fichier principal** : `modules/saas/saas.php`

---

## Constat

Managio inclut un module **SaaS multi-entreprise** qui modifie le comportement Perfex (config `my_config.php`, cron dédié, isolation par « company »).

Wekonex utilise **Stancl Tenancy** (1 association = 1 `tenant_id`).

Les deux modèles multi-tenant **ne sont pas alignés nativement**.

---

## Risques

| ID | Risque | Gravité |
|----|--------|---------|
| S1 | Sync contact créé dans la mauvaise « company » SaaS | **Haute** |
| S2 | API PerfexGo opère sur la DB de l’instance courante uniquement | Moyenne |
| S3 | `saas` modifie `app-config.php` au boot | Moyenne (déploiement) |
| S4 | Factures / clients d’une association visibles par une autre | **Haute** |

---

## Recommandations (Phase 0 → 1)

### 1. Environnement d’intégration

- Utiliser une instance Managio **sans SaaS actif** pour les premiers tests **OU**
- Désigner **une company SaaS = plateforme Wekonex** et n’y synchroniser que les clients « organisation » globaux.

### 2. Règle de mapping (validée)

| Wekonex | Managio (avec SaaS) |
|---------|---------------------|
| `tenant_id` (association) | 1 `client` dans la **company courante** de l’API |
| Super-admin Wekonex | Client « plateforme » séparé, pas mélangé aux associations |

### 3. Avant sync masse

- [ ] Confirmer si `saas` est activé sur managio.africa production
- [ ] Identifier l’ID company / contexte DB utilisé par le compte API « Wekonex Integration »
- [ ] Tester `GET perfex_mobile_companion/v1/customers/data` et vérifier l’isolation
- [ ] Documenter l’ID company dans les options `wekonex_bridge` si nécessaire (Phase 1)

### 4. Module `wekonex_bridge`

- Ne pas appeler les hooks SaaS directement
- Logger chaque création client avec `tenant_id` Wekonex en custom field
- Hook `wekonex_bridge_webhook_received` pour extensions futures

---

## Décision Phase 0

| Question | Décision provisoire |
|----------|---------------------|
| Bloquer Phase 1 ? | **Non** — poursuivre en dev/staging avec SaaS documenté |
| Test production ? | **Bloqué** tant que S1/S4 non validés manuellement |

---

## Historique

| Date | Changement |
|------|------------|
| 2026-05-25 | Audit initial Phase 0.10 |
