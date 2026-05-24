# 🏢 Système de Gestion des Cités Universitaires

**Plateforme intégrée de réservation et de gestion des logements universitaires**

Version 1.0 | Université de Ngaoundéré | Projet collaborative des 8 étudiants

---

## 📋 Table des Matières

- [Vue d'ensemble](#vue-densemble)
- [Fonctionnalités principales](#fonctionnalités-principales)
- [Architecture technique](#architecture-technique)
- [Structure du projet](#structure-du-projet)
- [Contributions de l'équipe](#contributions-de-léquipe)
- [Installation](#installation)
- [Guide d'utilisation](#guide-dutilisation)

---

## 🎯 Vue d'ensemble

Ce projet est un **système de gestion complet des logements universitaires** qui permet aux :

- **Étudiants** : de découvrir, réserver et payer des chambres en ligne
- **Administrateurs** : de gérer l'inventaire des chambres, les réservations et les paiements
- **Gestionnaires** : de générer des rapports et des statistiques

Le système offre une expérience utilisateur intuitive avec une interface responsive et des fonctionnalités complètes de réservation en ligne.

---

## ✨ Fonctionnalités principales

### 👤 Pour les Étudiants

| Fonctionnalité | Description |
|---|---|
| 📱 **Authentification** | Inscription, connexion sécurisée avec email |
| 🔍 **Recherche de chambres** | Filtrage par ville, type, prix, capacité |
| 🖼️ **Détails des chambres** | Images, équipements, avis (1-5 étoiles) |
| 📅 **Réservation** | Sélection de dates, confirmation instantanée |
| 💳 **Paiement** | Carte bancaire, Orange Money, MTN Mobile Money |
| 📊 **Tableau de bord** | Suivre les réservations actives et l'historique |
| ⭐ **Évaluations** | Noter et commenter les chambres après séjour |
| 🔔 **Notifications** | Alertes pour réservations et paiements |
| 👤 **Profil personnel** | Gestion des informations personnelles |

### 🔧 Pour les Administrateurs

| Fonctionnalité | Description |
|---|---|
| 📈 **Tableau de bord** | Statistiques complètes (chambres, réservations, revenus) |
| 🛏️ **Gestion des chambres** | Ajouter, modifier, supprimer des chambres + images |
| 📋 **Gestion des réservations** | Visualiser et modifier les statuts des réservations |
| 💰 **Gestion des paiements** | Suivi des transactions, ajout manuel de paiements |
| 👥 **Gestion des utilisateurs** | Gérer les comptes étudiants et administrateurs |
| 📊 **Rapports** | Revenus, taux d'occupation, statistiques |
| 🏘️ **Gestion des cités** | Détails des résidences universitaires |
| 🔔 **Système de notifications** | Communication avec les utilisateurs |

---

## 🏗️ Architecture technique

### Stack Technologique

| Technologie | Version | Rôle |
|---|---|---|
| **PHP** | 8.2.12 | Backend serveur |
| **MySQL** | 10.4.32 (MariaDB) | Base de données |
| **Bootstrap** | 5.1.3 | Framework UI |
| **Bootstrap Icons** | 1.8.1 | Icônes |
| **PDO** | Natif | Abstraction BD |
| **JavaScript** | Vanilla | Interactivité client |

### Architecture

```
Frontend (HTML/CSS/Bootstrap)
    ↓
PHP Pages (Controllers)
    ↓
Functions (Business Logic - includes/functions.php)
    ↓
PDO Database Layer
    ↓
MySQL Database
```

### Sécurité

✅ Requêtes SQL paramétrées (PDO prepared statements)  
✅ Hachage de mots de passe (bcrypt)  
✅ Contrôle d'accès basé sur les rôles  
✅ Validation des uploads de fichiers  
✅ Gestion des sessions sécurisée  

---

## 💾 Structure de la base de données

### Tables principales

| Table | Description |
|---|---|
| **utilisateurs** | Étudiants et administrateurs (email, mot de passe, rôle) |
| **cites** | Résidences universitaires (nom, localisation) |
| **chambres** | Chambres individuelles (type, prix, capacité) |
| **reservations** | Enregistrements de réservation (dates, statuts) |
| **paiements** | Transactions de paiement |
| **evaluations** | Évaluations et avis des chambres (1-5 étoiles) |
| **notifications** | Messages système (lu/non lu) |

---

## 📁 Structure du projet

```
cite-universitaire/
├── 📄 index.php                    # Page d'accueil
├── 📄 contact.php                  # Formulaire de contact
│
├── 🔐 auth/                        # Module d'authentification
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── 🛏️ chambres/                    # Catalogue des chambres
│   ├── index.php                   # Listing
│   ├── details.php                 # Détails d'une chambre
│   ├── reserver.php                # Formulaire de réservation
│   └── reservation-confirmation.php
│
├── 👤 etudiant/                    # Espace étudiant
│   ├── dashboard.php               # Tableau de bord
│   ├── mes-reservations.php        # Mes réservations
│   ├── annuler-reservation.php
│   ├── paiements.php               # Mes paiements
│   ├── historique-reservations.php
│   ├── facture.php
│   ├── evaluations.php
│   ├── notifications.php
│   └── profil.php
│
├── 💳 paiement/                    # Processus de paiement
│   ├── process.php                 # Traitement
│   ├── success.php
│   └── cancel.php
│
├── 🔧 admin/                       # Espace administrateur
│   ├── dashboard.php               # Statistiques
│   ├── gestion-chambres.php        # CRUD chambres
│   ├── ajouter-chambre.php
│   ├── modifier-chambre.php
│   ├── supprimer-chambre.php
│   ├── gestion-reservations.php
│   ├── details-reservation.php
│   ├── confirmation-reservation.php
│   ├── gestion-paiements.php
│   ├── gestion-utilisateurs.php
│   ├── gestion-cites.php
│   ├── rapports.php
│   ├── notifications.php
│   ├── profil.php
│   └── ajax/
│       └── get-cite-details.php
│
├── ⚙️ config/                      # Configuration
│   └── database.php                # Connexion BD
│
├── 🛠️ includes/                    # Fichiers utilitaires
│   ├── functions.php               # Fonctions métier
│   ├── auth-check.php              # Vérification auth
│   ├── header.php
│   ├── footer.php
│   ├── image-handler.php
│   ├── upload.php
│   └── pagination.php
│
├── 📚 assets/                      # Ressources statiques
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── images/
│       ├── chambres/               # Photos de chambres
│       └── cites/                  # Photos de résidences
│
├── 📊 sql/
│   └── database.sql                # Dump de la base de données
│
└── 📋 logs/                        # Fichiers journaux
```

---

## 🔁 Contributions de l'équipe

Selon la répartition décidée par la cheff **Fatime Hamid Ambou**, voici ce que chacun a réalisé :

### **Issa Abakar Issa** | 23A989FS
**🔧 Administration** — Développement complet du panel admin
- Gestion des chambres (ajouter, modifier, supprimer)
- Gestion des cités
- Gestion des utilisateurs
- Gestion des réservations (côté admin)
- Gestion des paiements (côté admin)
- Dashboard admin avec statistiques

**Fichiers clés :** `admin/*`

---

### **Outman Nassour Outman** | 23A907FS
**🗄️ Base de Données** — Architecture complète de la base de données
- Création du schéma SQL
- Design des 7 tables (utilisateurs, cites, chambres, reservations, paiements, evaluations, notifications)
- Gestion des relations et contraintes
- Optimisation des requêtes

**Fichiers clés :** `sql/database.sql`

---

### **Fatime Hamid Ambou** | 23B080FS 🎓 CHEFF
**🏠 Module Chambres** — Catalogue et interface de consultation
- Listing des chambres avec filtres
- Pages de détails des chambres
- Interface de réservation
- Confirmation des réservations
- Leadership et coordination de l'équipe

**Fichiers clés :** `chambres/*`

---

### **Mohamed Arafat** | 23A565FS
**💳 Paiements & Assets** — Système de paiement complet et ressources
- Processus de paiement multi-étapes
- Intégration des passerelles (Orange Money, MTN, Carte bancaire)
- Gestion des factures
- Gestion du dossier `assets/` (CSS, JS, images)
- Pages de paiement étudiant

**Fichiers clés :** `paiement/*`, `assets/*`, `etudiant/paiements.php`

---

### **Dimougna Clément** | 22B197FS
**📄 Pages Publiques & Logs** — Interface publique et maintenance
- Page d'accueil (`index.php`)
- Formulaire de contact (`contact.php`)
- Gestion des fichiers journaux
- Configuration des logs

**Fichiers clés :** `index.php`, `contact.php`, `logs/`

---

### **Adoum Mahamat-Zene** | 23A163FS
**👤 Espace Étudiant** — Interface complète pour les étudiants
- Tableau de bord étudiant
- Gestion des réservations personnelles
- Suivi des paiements
- Historique des réservations
- Annulation de réservations
- Profil personnel
- Évaluations et avis
- Notifications personnelles

**Fichiers clés :** `etudiant/*`

---

### **Kokanoun Marcel** | 22A255FS
**🔐 Configuration & Authentification** — Sécurité et configuration
- Système de login/inscription
- Gestion des sessions
- Hachage des mots de passe (bcrypt)
- Contrôle d'accès par rôles
- Vérification d'authentification
- Connexion à la base de données

**Fichiers clés :** `auth/*`, `config/database.php`, `includes/auth-check.php`

---

### **Souleymane Mahamat Tahir** | 23A169FS
**🛠️ Utilitaires & Fonctions** — Infrastructure logicielle réutilisable
- Fonctions métier (30+)
- Gestion des images (upload, redimensionnement)
- Validation des uploads de fichiers
- Système de pagination
- Templates header/footer
- Helpers et fonctions utilitaires

**Fichiers clés :** `includes/*`

---

## 🚀 Installation

### Prérequis

- PHP 8.0+
- MySQL 5.7+ ou MariaDB 10.4+
- Serveur web (Apache, Nginx)

### Étapes

1. **Cloner/Télécharger le projet**
   ```bash
   git clone [url-du-repository]
   cd cite-universitaire
   ```

2. **Configurer la base de données**
   - Importer `sql/database.sql` dans MySQL
   - Modifier `config/database.php` avec vos identifiants

3. **Configurer les permissions**
   ```bash
   chmod 755 assets/images/chambres/
   chmod 755 assets/images/cites/
   chmod 755 logs/
   ```

4. **Accéder au site**
   - Frontend : `http://localhost/cite-universitaire/`
   - Admin : `http://localhost/cite-universitaire/admin/dashboard.php` (après connexion)

### Identifiants par défaut

Vérifier dans `sql/database.sql` les utilisateurs de test.

---

## 💻 Guide d'utilisation

### Pour un Étudiant

1. **S'inscrire** : Aller à `/auth/register.php`
2. **Se connecter** : `/auth/login.php`
3. **Chercher une chambre** : `/chambres/` avec filtres
4. **Réserver** : Cliquer sur "Réserver" et sélectionner les dates
5. **Payer** : Effectuer le paiement via la plateforme
6. **Évaluer** : Noter la chambre après séjour

### Pour un Administrateur

1. **Accéder au dashboard** : `/admin/dashboard.php`
2. **Ajouter des chambres** : `admin/ajouter-chambre.php`
3. **Gérer les réservations** : `admin/gestion-reservations.php`
4. **Suivre les paiements** : `admin/gestion-paiements.php`
5. **Consulter les rapports** : `admin/rapports.php`

---

## 📊 Architecture des contributions

```
┌──────────────────────────────────────────┐
│  Base de Données (Outman)                │
│  sql/database.sql                        │
└────────────────┬─────────────────────────┘
                 │
┌────────────────▼─────────────────────────┐
│  Couche Utilitaires (Souleymane)         │
│  includes/* (fonctions, images, etc.)    │
└────────────────┬─────────────────────────┘
                 │
    ┌────────────┼────────────┬──────────────┐
    │            │            │              │
┌───▼──┐  ┌────▼───┐ ┌───────▼──┐  ┌──────▼──┐
│ Auth │  │ Admin  │ │ Chambres │  │Étudiant │
│(Marc)│  │ (Issa) │ │(Fatime)  │  │ (Adoum) │
└───┬──┘  └───┬────┘ └────┬─────┘  └───┬────┘
    │         │           │            │
    │    ┌────▼──────┬────▼──┐   ┌────▼──┐
    │    │ Paiements │Pages  │   │Assets │
    │    │ (Arafat)  │(Clém.)│   │(Arafat)
    └────┴───────────┴───────┴───┴───────┘
```

---

## 📞 Support et Contact

Pour toute question ou problème :
- Consulter la documentation du code
- Vérifier les fichiers de log dans `/logs/`
- Contacter la cheff du projet : Fatime Hamid Ambou

---

## 📄 Licence

Ce projet est la propriété de l'Université de Ngaoundéré.

---

## 👥 Équipe complète

| Nom | Matricule | Rôle |
|---|---|---|
| Fatime Hamid Ambou | 23B080FS | **Cheff du projet** |
| Issa Abakar Issa | 23A989FS | Admin |
| Outman Nassour Outman | 23A907FS | SQL |
| Mohamed Arafat | 23A565FS | Paiements & Assets |
| Dimougna Clément | 22B197FS | Pages publiques |
| Adoum Mahamat-Zene | 23A163FS | Espace Étudiant |
| Kokanoun Marcel | 22A255FS | Auth & Config |
| Souleymane Mahamat Tahir | 23A169FS | Utilitaires |

---
 
**Université** : Université de Ngaoundéré
