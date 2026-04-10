# Dashboard Analytics — Design Spec

## Contexte

Le bundle `cookieless-analytics` collecte des pages vues et des événements custom dans PostgreSQL. Il manque la partie affichage : un dashboard standalone permettant de visualiser ces données. Ce dashboard cible principalement des utilisateurs non-techniques.

## Périmètre

### Inclus (v1)
- Dashboard standalone avec 4 widgets : Overview, Top pages, Events, Trends
- Sélecteur de période avec raccourcis et dates libres
- Rechargement partiel via Turbo Frames
- Layout responsive
- CSS autonome scopé

### Exclu (v2+)
- Navigation paths (parcours A → B → C)
- Export de données
- Comparaison de périodes côte à côte

## Stack technique

| Composant | Choix | Justification |
|-----------|-------|---------------|
| Rendu HTML | Twig | Cohérent avec le reste du bundle |
| Interactivité | Symfony UX Turbo + Stimulus | Idiomatique Symfony, rechargement partiel sans SPA |
| Graphiques | uPlot (~35KB) via importmap | Léger, performant, suffisant pour courbes et barres |
| CSS | Fichier unique scopé `.ca-dashboard` | Pas de build, pas de collision avec l'app hôte |
| Assets JS | Symfony AssetMapper (importmap) | Pas de build Node requis |

## Architecture

### Routes & contrôleur

Un `DashboardController` unique monté sur le préfixe configurable (défaut `/analytics`), protégé par `#[IsGranted]` avec le rôle configuré.

**Actions :**

| Action | Route | Rôle |
|--------|-------|------|
| `index()` | `GET /analytics` | Page complète avec 4 Turbo Frames |
| `overview()` | `GET /analytics/overview` | Fragment HTML — cartes KPI |
| `topPages()` | `GET /analytics/top-pages` | Fragment HTML — tableau top pages |
| `events()` | `GET /analytics/events` | Fragment HTML — tableau événements |
| `trends()` | `GET /analytics/trends` | Fragment HTML contenant un `<div>` avec les données en `data-*` attributes pour uPlot |

Toutes les actions acceptent les query params `from` et `to` (format `Y-m-d`). Valeur invalide → fallback sur les 30 derniers jours.

### Flux de données

```
Browser GET /analytics
  → DashboardController::index()
    → Twig rend la page avec 4 <turbo-frame> (loading="lazy")
      → Chaque frame charge son fragment en parallèle

Changement de période :
  → Stimulus controller met à jour les query params
  → Turbo recharge chaque frame avec les nouveaux params
  → Seuls les fragments HTML sont retournés
```

### Repositories

Extension des repositories existants avec des méthodes agrégées :

**PageViewRepository :**
- `countByPeriod(DateTimeImmutable $from, DateTimeImmutable $to): int`
- `countUniqueVisitorsByPeriod($from, $to): int`
- `findTopPages($from, $to, int $limit = 10): array`
- `countByDay($from, $to): array` — retourne `[['date' => '2026-04-01', 'count' => 42, 'unique' => 15], ...]`

**AnalyticsEventRepository :**
- `countByPeriod($from, $to): int`
- `findTopEvents($from, $to, int $limit = 10): array` — retourne nom, occurrences, nombre de valeurs distinctes
- `countByDay($from, $to): array`

## Widgets

### Overview (bandeau KPI)

4 cartes en ligne :

| Carte | Source | Calcul |
|-------|--------|--------|
| Pages vues | `PageViewRepository::countByPeriod()` | `COUNT(*)` |
| Visiteurs uniques | `PageViewRepository::countUniqueVisitorsByPeriod()` | `COUNT(DISTINCT fingerprint)` |
| Événements | `AnalyticsEventRepository::countByPeriod()` | `COUNT(*)` |
| Pages/visiteur | Calculé dans le contrôleur | pages vues / visiteurs uniques |

Chaque carte affiche :
- La valeur en grand
- Un indicateur de variation par rapport à la période précédente équivalente (ex : 7 derniers jours vs 7 jours d'avant)
- Flèche haut/bas + pourcentage en vert/rouge
- Si pas assez de données historiques : pas de variation affichée

### Top pages (tableau)

| Colonne | Description |
|---------|-------------|
| URL | URL de la page (tronquée visuellement si trop longue) |
| Pages vues | Nombre total de vues |
| Visiteurs uniques | Fingerprints distincts |

- Trié par pages vues décroissantes
- Limité à 10 lignes (pas de pagination en v1)

### Events (tableau)

| Colonne | Description |
|---------|-------------|
| Nom | Nom de l'événement |
| Occurrences | Nombre total |
| Valeurs distinctes | Nombre de `value` différentes pour cet événement |

- Trié par occurrences décroissantes
- Limité à 10 lignes

### Trends (graphique uPlot)

- Courbe pleine : pages vues par jour
- Courbe pointillée/atténuée : visiteurs uniques par jour
- Axe X : jours de la période
- Axe Y : compteurs
- Tooltip au survol : date + les deux valeurs
- Rendu via un Stimulus controller qui initialise uPlot avec les données passées en attributs `data-*` (JSON encodé) sur le conteneur du graphique

## Sélecteur de période

### Position
En haut à droite de la page, au-dessus des widgets.

### Raccourcis (boutons inline)
- Aujourd'hui
- 7 jours
- 30 jours
- Ce mois-ci

Le raccourci actif est visuellement mis en évidence. Un clic met à jour `?from=...&to=...` dans l'URL.

### Dates personnalisées
- Deux `<input type="date">` (du / au) + bouton "Appliquer"
- Quand les dates libres sont utilisées, aucun raccourci n'est actif
- Validation : `from <= to`

### Stimulus controller `date-range-controller`
- Gère les clics sur les raccourcis et le formulaire de dates
- Met à jour l'attribut `src` de chaque Turbo Frame avec les nouveaux params
- La période est toujours reflétée dans l'URL (bookmarkable, partageable)
- Période par défaut (aucun param) : 30 derniers jours

## Layout

```
┌──────────────────────────────────────────────────┐
│  "Analytics"                  [Sélecteur période] │
├──────────────────────────────────────────────────┤
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐│
│  │Pg. vues │ │Visiteurs│ │Événem.  │ │Pg/visit.││
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘│
├──────────────────────────────────────────────────┤
│  Trends (graphique uPlot, pleine largeur)        │
├────────────────────────┬─────────────────────────┤
│  Top pages (tableau)   │  Events (tableau)        │
└────────────────────────┴─────────────────────────┘
```

### Responsive (< 768px)
- KPI : grille 2x2
- Trends : pleine largeur (inchangé)
- Tableaux : empilés verticalement

## Style

- **Scope** : tout le CSS sous `.ca-dashboard` pour éviter les collisions
- **Fond** : clair, cartes blanches avec ombre légère (`box-shadow`)
- **Typographie** : `system-ui, sans-serif` — pas de fonte custom
- **Couleurs fonctionnelles** : vert (variation positive), rouge (variation négative), bleu (courbe pages vues), gris (courbe visiteurs)
- **Un seul fichier CSS** embarqué dans le bundle

## Templates Twig

| Template | Rôle |
|----------|------|
| `@CookielessAnalytics/dashboard/index.html.twig` | Page principale, hérite du layout |
| `@CookielessAnalytics/dashboard/_overview.html.twig` | Fragment KPI (Turbo Frame) |
| `@CookielessAnalytics/dashboard/_top_pages.html.twig` | Fragment tableau top pages |
| `@CookielessAnalytics/dashboard/_events.html.twig` | Fragment tableau événements |
| `@CookielessAnalytics/dashboard/_trends.html.twig` | Fragment graphique tendances |
| `@CookielessAnalytics/dashboard/layout.html.twig` | Layout HTML minimal par défaut |

### Layout surchargeable

Le bundle fournit un layout HTML minimal (doctype, head, body). L'utilisateur peut configurer `dashboard_layout` pour utiliser son propre layout (avec sa navbar, son footer, etc.). Le template `index.html.twig` utilise `{% extends dashboard_layout %}`.

## Configuration

```yaml
cookieless_analytics:
    # ... config existante

    dashboard_enabled: true                # Active/désactive le dashboard
    dashboard_prefix: '/analytics'         # Préfixe des routes dashboard
    dashboard_role: 'ROLE_ANALYTICS'       # Rôle requis pour accéder
    dashboard_layout: null                 # Layout Twig custom (null = layout par défaut du bundle)
```

- `dashboard_enabled: false` → aucune route enregistrée, zéro overhead
- `dashboard_role: 'ROLE_ANALYTICS'` par défaut — l'app hôte attribue ce rôle comme elle veut (hiérarchie, attribution directe, etc.)
- `dashboard_layout: null` → le bundle utilise son propre layout HTML minimal standalone

## Sécurité

- Protection par `#[IsGranted]` sur chaque action du contrôleur (index + fragments)
- Le bundle ne touche pas au `security.yaml` de l'app hôte
- Le rôle par défaut `ROLE_ANALYTICS` est indépendant de `ROLE_ADMIN` — l'utilisateur peut les lier via la hiérarchie des rôles s'il le souhaite
- Paramètres `from`/`to` validés et convertis en `DateTimeImmutable`, fallback sur 30 jours si invalide
- Requêtes SQL via paramètres préparés Doctrine

## Tests

- **Tests unitaires** : méthodes des repositories avec fixtures en base (PostgreSQL)
- **Tests fonctionnels** : `DashboardController` — réponses HTTP, contrôle d'accès (403 sans rôle, 200 avec), contenu des fragments, paramètres de période
- **Pas de tests JS en v1** — la logique Stimulus est minimale (date picker + init uPlot)
