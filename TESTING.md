# Riwa Booking — Guide de Test

**Version 2.0.0** — Février 2026
À destination des testeurs QA, recette métier et développeurs.

---

## Préparation

### Environnement de test
- URL admin : `http://riwa-booking.local/wp-admin`
- Plugin actif : **Riwa Booking** (vérifier dans Extensions)
- Page frontend avec le shortcode `[riwa_booking]`

### Données de test rapides
Pour ne pas créer manuellement des données, utiliser les **données démo** :

1. Aller dans **Riwa Bookings > Paramètres > Données démo**
2. Cliquer **Injecter** → 24 réservations réalistes + blocages + prix spéciaux sont créés
3. Toutes les données démo sont préfixées `[DEMO]` et supprimables en un clic

---

## Liste des scénarios de test

---

### T01 — Réservation frontend

**Accès :** Page publique avec le shortcode `[riwa_booking]`

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Charger la page | Formulaire et calendrier s'affichent sans erreur console |
| 2 | Cliquer sur une date déjà réservée | Date grisée / non sélectionnable |
| 3 | Sélectionner des dates disponibles | Période surlignée, calcul de prix affiché |
| 4 | Remplir le formulaire complet (nom, email, téléphone, voyageurs) | Champs validés visuellement |
| 5 | Soumettre avec un email invalide | Message d'erreur affiché, pas de soumission |
| 6 | Soumettre avec des données valides | Message de succès + email de confirmation reçu |
| 7 | Vérifier dans l'admin | Réservation apparaît avec statut `En attente` |

**Points de vigilance :**
- Le prix affiché doit correspondre à la saison tarifaire couvrant les dates choisies
- Si aucune saison ne couvre la période, le prix par défaut de `production-config.php` s'applique

---

### T02 — Gestion des réservations (admin)

**Accès :** Riwa Bookings > Réservations

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Charger la section | Liste des réservations paginée (20/page) |
| 2 | Filtrer par statut "En attente" | Seules les réservations `pending` affichées |
| 3 | Filtrer par mois (ex: mois courant) | Filtre s'applique correctement |
| 4 | Rechercher par nom "Jean" | Seules les réservations correspondantes affichées |
| 5 | Cliquer "Confirmer" sur une réservation | Statut passe à `Confirmée`, badge vert |
| 6 | Cliquer "Annuler" sur une réservation | Statut passe à `Annulée`, badge rouge |
| 7 | Cliquer "Détails" | Popup s'ouvre avec toutes les infos |
| 8 | Cliquer "PDF" | Téléchargement PDF du bon de réservation |
| 9 | Voir badge paiement dans la liste | Badge coloré (Non payé / Partiel / Payé…) |

---

### T03 — Popup détail réservation

**Accès :** Réservations > Détails (icône loupe)

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Ouvrir le popup | Infos client, dates, voyageurs, prix total affichés |
| 2 | Changer le statut via le select | Statut mis à jour sans rechargement |
| 3 | Voir section Ménage | Statut ménage (`En attente` / `En cours` / `Prêt`) |
| 4 | Voir section Paiements | Timeline des paiements enregistrés |
| 5 | Voir section WhatsApp | Boutons de notification si WhatsApp activé |
| 6 | Fermer le popup | Popup se ferme proprement, fond déverrouillé |

---

### T04 — Planning

**Accès :** Riwa Bookings > Planning

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Charger la section | Calendrier du mois courant affiché |
| 2 | Naviguer mois précédent / suivant | Calendrier mis à jour |
| 3 | Voir les réservations | Barres colorées selon statut (jaune/vert/rouge) |
| 4 | Voir les blocages | Affichés différemment des réservations |
| 5 | Ajouter un blocage de dates | Dates bloquées visibles dans le calendrier |
| 6 | Voir les statistiques du mois | Taux d'occupation, nuits réservées affichés |

---

### T05 — Paiements

**Accès :** Riwa Bookings > Paiements

#### Onglet Vue globale
| # | Action | Résultat attendu |
|---|---|---|
| 1 | Charger l'onglet | 5 KPI cards affichées (encaissé, en attente, en retard, acomptes, prévision) |
| 2 | Vérifier les valeurs KPI | Montants affichés sans débordement (format compact si >1000) |
| 3 | Voir alertes retards | Liste des réservations avec solde en retard |
| 4 | Formulaire paiement rapide | Tous les champs alignés sur une ligne |
| 5 | Sélectionner une réservation dans le formulaire | Le champ "Montant" affiche un hint avec le solde restant |
| 6 | Remplir et soumettre le formulaire | Paiement enregistré, message de succès, formulaire réinitialisé |

#### Onglet Réservations
| # | Action | Résultat attendu |
|---|---|---|
| 7 | Filtrer par "En retard" | Seules les réservations en retard affichées |
| 8 | Filtrer par "Payé" | Seules les réservations soldées affichées |
| 9 | Cliquer sur une ligne (client) | Panel latéral glissant s'ouvre |
| 10 | Panel : voir timeline des paiements | Historique chronologique affiché |
| 11 | Panel : ajouter un paiement | Champs montant, mode, date, référence ; soumettre |
| 12 | Panel : paiement ajouté | Timeline mise à jour, statut de la ligne mis à jour |
| 13 | Cliquer "Enregistrer paiement" (icône dans la ligne) | Modal s'ouvre |
| 14 | Modal : remplir et valider | Paiement enregistré, modal ferme |
| 15 | Supprimer un paiement (icône corbeille dans le panel) | Confirmation demandée, paiement supprimé |

#### Onglet Export
| # | Action | Résultat attendu |
|---|---|---|
| 16 | Cliquer "Télécharger le CSV" sans filtre | Fichier CSV de tous les paiements téléchargé |
| 17 | Sélectionner un mois + télécharger | CSV filtré sur le mois sélectionné |
| 18 | Ouvrir le CSV dans Excel | Encodage UTF-8, séparateur `;`, colonnes lisibles |

---

### T06 — Notifications WhatsApp

**Prérequis :** Activer WhatsApp dans Paramètres > Notifications + renseigner un numéro admin

**Accès :** Popup d'une réservation > section WhatsApp

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Voir les boutons WhatsApp | 4 boutons : Confirmation, Rappel, Infos arrivée, Demande avis |
| 2 | Cliquer "Confirmation" | Modal d'aperçu s'ouvre avec le message rendu (variables remplacées) |
| 3 | Vérifier les variables dans le message | `{nom_client}`, `{date_arrivee}` etc. remplacés par les vraies valeurs |
| 4 | Cliquer "Ouvrir WhatsApp" | Navigateur ouvre `wa.me/...?text=...` + log enregistré |
| 5 | Voir l'historique | Entrée ajoutée dans la timeline du popup |
| 6 | Fermer le modal sans envoyer | Aucun log enregistré |

**Accès :** Riwa Bookings > Notifications

| # | Action | Résultat attendu |
|---|---|---|
| 7 | Charger la section | Réservations du jour (arrivées + départs + séjours en cours) |
| 8 | Voir le centre de notifications | Cartes avec labels "Arrive aujourd'hui", "Part aujourd'hui" |
| 9 | Voir l'historique | 20 derniers envois WhatsApp listés |

---

### T07 — Statistiques

**Accès :** Riwa Bookings > Statistiques

#### Onglet Pulse
| # | Action | Résultat attendu |
|---|---|---|
| 1 | Score de santé affiché | Jauge 0-100 avec grade A/B/C/D et couleur |
| 2 | Breakdown du score | Détail des 4 critères (occupation, confirmation, annulation, ménage) |
| 3 | Alertes actionnables | Liste des alertes (ex: "3 réservations en attente de confirmation") |
| 4 | Météo financière 7j | Graphique CA jour par jour sur 7 jours glissants |

#### Onglet Analyse
| # | Action | Résultat attendu |
|---|---|---|
| 5 | KPIs annuels | CA total, nb réservations, durée moyenne séjour, etc. |
| 6 | Graphique CA mensuel | Chart.js ligne sur 12 mois |
| 7 | Graphique taux occupation | Chart.js barres par mois |
| 8 | Profil voyageurs | Répartition adultes/enfants, jours d'arrivée préférés |
| 9 | Changer d'année | Données rechargées pour l'année sélectionnée |

#### Onglet Prévision
| # | Action | Résultat attendu |
|---|---|---|
| 10 | Projection fin d'année | CA projeté vs CA actuel |
| 11 | Opportunités | Trous tarifaires détectés avec impact estimé |

---

### T08 — Paramètres > Général

**Accès :** Riwa Bookings > Paramètres > Général

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Modifier la langue | Select FR/EN |
| 2 | Modifier la devise | Select EUR/USD/CHF/MAD |
| 3 | Modifier le fuseau horaire | Select d'options courantes |
| 4 | Renseigner une URL de logo | Champ URL + bouton "Choisir" (media uploader) |
| 5 | Logo défini → aperçu | Vignette du logo affichée sous le champ |
| 6 | Modifier la couleur principale | Color picker WordPress |
| 7 | Cliquer "Enregistrer" | Message de succès, valeurs persistées après rechargement |

---

### T09 — Paramètres > Tarification

**Accès :** Riwa Bookings > Paramètres > Tarification

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Charger l'onglet | Formulaire d'ajout + liste des saisons existantes |
| 2 | Remplir le formulaire (nom, prix, dates, séjour min.) | Champs stylisés cohérents avec le reste |
| 3 | Soumettre une saison valide | Ligne ajoutée dans le tableau |
| 4 | Tenter d'ajouter une période qui chevauche | Erreur (check_date_overlap) |
| 5 | Supprimer une saison | Confirmation demandée, ligne supprimée |
| 6 | Voir le badge "Active" | Vert si `is_active = 1` |

---

### T10 — Paramètres > Email

**Accès :** Riwa Bookings > Paramètres > Email

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Toggle "Activer les notifications" | Switch ON/OFF visuel |
| 2 | Remplir email admin, nom expéditeur, email expéditeur | 3 champs sur une ligne |
| 3 | Modifier le template client | Textarea éditable |
| 4 | Renseigner un email de test | Champ email |
| 5 | Cliquer "Tester email client" | Email reçu à l'adresse de test |
| 6 | Cliquer "Tester email admin" | Email reçu à l'adresse de test |
| 7 | Sauvegarder | Message de succès, données persistées |

---

### T11 — Paramètres > Notifications

**Accès :** Riwa Bookings > Paramètres > Notifications

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Toggle "Activer les boutons WhatsApp" | Switch vert ON |
| 2 | Renseigner indicatif pays (+33) | Champ court |
| 3 | Renseigner numéro admin | Format international (+212...) |
| 4 | Voir la grille des variables disponibles | 13 variables affichées en chips cliquables |
| 5 | Modifier les 4 templates | Textareas monospace |
| 6 | Sauvegarder | Message de succès, templates persistés |

---

### T12 — Paramètres > Diagnostic

**Accès :** Riwa Bookings > Paramètres > Diagnostic

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Charger l'onglet | 3 cards affichées |
| 2 | Infos système | PHP, WP, MySQL, version plugin affichés avec badges |
| 3 | État tables | wp_riwa_payments affichée "Présente" si migration OK |
| 4 | Tarification active | Liste des saisons actives avec prix |
| 5 | Badge "WP Debug activé" | Vert si WP_DEBUG = true (normal en local) |

---

### T13 — Paramètres > Données démo

**Accès :** Riwa Bookings > Paramètres > Données démo

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Cliquer "Injecter" → confirmer | Message "24 réservations injectées", bouton "Effacer" apparaît |
| 2 | Aller sur le Planning | Réservations `[DEMO]` visibles sur 90 jours |
| 3 | Aller sur Paiements > Vue globale | KPIs mis à jour avec les données démo |
| 4 | Retourner dans Données démo | Bouton "Effacer" présent |
| 5 | Cliquer "Effacer" → confirmer | Message "Données démo supprimées", bouton "Effacer" masqué |
| 6 | Vérifier Planning | Réservations `[DEMO]` disparues |

---

### T14 — PDF Doc Studio

**Accès :** Riwa Bookings > Factures / PDF

| # | Action | Résultat attendu |
|---|---|---|
| 1 | Charger l'éditeur | Canvas A4 + palette de blocs à gauche |
| 2 | Changer de type de document | Layout spécifique au type chargé |
| 3 | Glisser un bloc depuis la palette | Bloc apparaît dans le canvas |
| 4 | Réordonner les lignes | Ordre persisté |
| 5 | Double-cliquer sur un bloc | Bascule entre demi-largeur et pleine largeur |
| 6 | Supprimer un bloc (bouton ✕) | Bloc retiré du canvas |
| 7 | Cliquer "Actualiser aperçu" | Iframe mise à jour avec le HTML rendu |
| 8 | Cliquer "Tester PDF" | Téléchargement PDF respectant le layout |
| 9 | Sauvegarder → recharger la page | Layout identique au retour |
| 10 | Modifier les infos société | Champs persistés, visibles dans l'aperçu |

---

## Cas limites à tester

| Scénario | Comportement attendu |
|---|---|
| Réservation avec dates chevauchant une autre | Refusée avec message d'erreur |
| Paiement d'un montant supérieur au solde | Autorisé (sur-paiement possible) |
| Suppression d'une réservation avec paiements | Réservation supprimée, paiements orphelins (accepté) |
| Template WhatsApp avec toutes les variables | Toutes remplacées, aucun `{var}` résiduel |
| Export CSV sans aucun paiement enregistré | Fichier CSV vide (juste les en-têtes) |
| Injection données démo deux fois | Doublon prévenu ? (vérifier comportement) |
| Calendrier frontend sans aucune saison tarifaire | Prix par défaut affiché (pas d'erreur) |
| Formulaire frontend avec dates bloquées | Dates grisées dans Flatpickr |

---

## Vérifications transversales

### Console navigateur
- Aucune erreur JS rouge sur toutes les sections
- Aucun appel AJAX en erreur 400/500 (vérifier onglet Réseau)

### Responsive / mobile
- Menu admin latéral non testé mobile (usage exclusivement desktop)
- Frontend booking form : vérifier sur mobile (formulaire + calendrier)

### Cohérence visuelle
- Toutes les sections utilisent les mêmes composants : `.riwa-btn`, `.riwa-input`, `.riwa-setting-group`, badges de statut
- Les onglets de Paramètres s'activent correctement (un seul actif à la fois)
- Pas de débordement de contenu sur les cards KPI

---

## Rapport de bug

Pour chaque bug trouvé, noter :
1. **Section** concernée (ex: Paiements > Vue globale)
2. **Étape** reproduite (ex: T05-6)
3. **Comportement observé** (texte exact du message, capture d'écran)
4. **Comportement attendu**
5. **Environnement** : navigateur, résolution, si données démo actives

---

*Guide de test Riwa Booking v2.0.0 — Février 2026*
