<?php

defined('BASEPATH') or exit('No direct script access allowed');

$lang['wasabi_storage'] = 'Stockage Wasabi';
$lang['wasabi_storage_settings_intro'] = 'Stockez les pièces jointes CRM (tâches, projets, tickets, documents commerciaux, produits) et éventuellement les sauvegardes base de données sur Wasabi Hot Cloud Storage. Créez un bucket privé et des clés API dans la console Wasabi, puis activez le module.';
$lang['wasabi_storage_enabled'] = 'Activer Wasabi pour les nouveaux envois';
$lang['wasabi_access_key'] = 'Clé d\'accès';
$lang['wasabi_secret_key'] = 'Clé secrète';
$lang['wasabi_bucket'] = 'Nom du bucket';
$lang['wasabi_region'] = 'Région';
$lang['wasabi_endpoint'] = 'URL endpoint S3';
$lang['wasabi_path_prefix'] = 'Préfixe des clés objet';
$lang['wasabi_signed_url_ttl'] = 'Durée des URL signées (secondes)';
$lang['wasabi_backup_enabled'] = 'Aussi envoyer les sauvegardes BDD vers Wasabi';
$lang['wasabi_storage_actions'] = 'Actions';
$lang['wasabi_storage_test_connection'] = 'Tester la connexion';
$lang['wasabi_storage_push_backups'] = 'Envoyer les sauvegardes existantes';
$lang['wasabi_storage_migrate'] = 'Migrer les fichiers locaux existants';
$lang['wasabi_storage_purge_local'] = 'Supprimer les fichiers locaux après envoi réussi (optionnel)';
$lang['wasabi_storage_migrate_confirm'] = 'Migrer tous les fichiers sous uploads/ vers Wasabi ?';
$lang['wasabi_storage_migrate_status'] = 'Dernière migration';
$lang['wasabi_storage_migrate_done'] = 'Migration terminée : %s';
$lang['wasabi_storage_connection_ok'] = 'Connexion Wasabi réussie';
$lang['wasabi_storage_connection_failed'] = 'Échec de la connexion Wasabi';
$lang['wasabi_storage_configure_first'] = 'Enregistrez d\'abord les identifiants Wasabi';
$lang['wasabi_storage_backups_folder_missing'] = 'Dossier local des sauvegardes introuvable. Créez d\'abord une sauvegarde BDD (Configuration → Sauvegarde), ou vérifiez que le dossier backups/ existe sur le serveur.';
$lang['wasabi_storage_backups_none'] = 'Aucun fichier de sauvegarde BDD trouvé dans backups/. Créez une sauvegarde d\'abord, puis réessayez.';
$lang['wasabi_storage_backups_push_failed'] = 'Échec de l\'envoi des sauvegardes BDD vers Wasabi';
$lang['wasabi_storage_backups_pushed_partial'] = '%s fichier(s) envoyé(s), %s en échec';
$lang['wasabi_backup_enabled_help'] = 'Une fois activée (et enregistrée), chaque nouvelle sauvegarde BDD est envoyée automatiquement vers Wasabi. Utilisez « Envoyer les sauvegardes existantes » pour les fichiers déjà présents dans backups/.';
$lang['wasabi_storage_last_error'] = 'Dernière erreur';
$lang['wasabi_storage_secret_unchanged'] = 'Laissez tel quel pour conserver le secret actuel';
$lang['wasabi_storage_backups_pushed'] = '%s fichier(s) de sauvegarde envoyé(s) vers Wasabi';
