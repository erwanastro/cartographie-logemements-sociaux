# Guide pour Claude - Projet Cartographie Logements Sociaux

Ce document explique l'architecture et les conventions du projet pour faciliter le développement avec Claude Code.

## Objectif du Projet

Créer une application Symfony qui fusionne des données de logements sociaux (format MAJIC) avec des géométries cadastrales (format CNIG PCI) pour générer des fichiers cartographiques exploitables (GeoJSON).

## Commandes Utiles

```bash
# Installation
composer install

# Traitement principal
php bin/console app:process-parcelles

# Ajout colonne CNIG
php bin/console app:add-cnig-column

# Liste des commandes
php bin/console list

# Tests
php bin/phpunit
```

## Architecture

### Structure des Services

Le projet suit le principe de responsabilité unique (SRP) avec des services spécialisés:

1. **MajicConverterService** (`src/Service/MajicConverterService.php`)
   - Conversion des codes MAJIC (14 caractères) vers CNIG PCI (12 caractères)
   - Formule: `CNIG = MAJIC[0:5] + MAJIC[7:3] + MAJIC[10:4]`
   - Retourne une chaîne vide si le format est invalide

2. **CsvService** (`src/Service/CsvService.php`)
   - Lecture/écriture de fichiers CSV avec séparateur `;`
   - Gestion de l'encodage UTF-8 avec BOM
   - Manipulation des colonnes dynamiques

3. **GeoJsonService** (`src/Service/GeoJsonService.php`)
   - Extraction des coordonnées depuis les géométries GeoJSON
   - Calcul de distances GPS (formule euclidienne simplifiée)
   - Recherche de parcelles par proximité

### Commandes Symfony

1. **ProcessParcellesCommand** (`src/Command/ProcessParcellesCommand.php`)
   - Commande principale: `php bin/console app:process-parcelles`
   - Fusionne les deux fichiers CSV
   - Applique la stratégie de matching (code puis GPS)

2. **AddCnigColumnCommand** (`src/Command/AddCnigColumnCommand.php`)
   - Commande optionnelle: `php bin/console app:add-cnig-column`
   - Ajoute la colonne `code_parcelle_cnig` au fichier `parcelles-des-personnes-morales.csv`

### Note technique

Ce projet n'est pas une application Symfony standard. Il s'agit d'une application console qui utilise les composants Symfony (Console, DependencyInjection, Dotenv).
Le point d'entrée principal est le fichier `bin/console`, qui a été modifié pour initialiser manuellement le conteneur de services et les commandes.
Cette approche "single-file" est légère et adaptée pour un outil en ligne de commande.

Les variables d'environnement sont directement injectées en tant que paramètre dans le fichier `bin/console` et donc pas au runtime.

## Conventions de Code

### Principes de Développement

En tant que développeur backend et architecte logiciel de niveau expert, toujours appliquer les principes suivants:

#### Principes SOLID
- **S**ingle Responsibility: Chaque classe a une seule responsabilité clairement définie
- **O**pen/Closed: Ouvert à l'extension, fermé à la modification
- **L**iskov Substitution: Les sous-classes doivent pouvoir remplacer leurs classes parentes
- **I**nterface Segregation: Préférer plusieurs interfaces spécifiques plutôt qu'une interface générale
- **D**ependency Inversion: Dépendre des abstractions, pas des implémentations concrètes

#### Principes KISS et Clean Code
- **Keep It Simple, Stupid**: Privilégier la simplicité à la complexité inutile
- Code lisible et explicite plutôt que concis mais obscur
- Noms de variables et méthodes descriptifs et significatifs
- Fonctions courtes avec un seul niveau d'abstraction
- Éviter les commentaires inutiles, le code doit être auto-documenté

#### Principe DRY (Don't Repeat Yourself)
- Ne jamais dupliquer de logique
- Préférer le refactoring de composants existants à la création de nouveaux
- Créer des modules réutilisables plutôt que copier-coller du code
- Extraire les constantes et configurations partagées

#### Qualité du Code
- Code cohérent dans tout le projet
- Maintenabilité à long terme privilégiée
- Lisibilité pour faciliter les contributions futures
- Tests unitaires pour garantir la stabilité
- Documentation inline pour les cas complexes uniquement

### Nommage

- Services: Suffixe `Service` (ex: `MajicConverterService`)
- Commandes: Suffixe `Command` (ex: `ProcessParcellesCommand`)
- Méthodes: camelCase avec verbes d'action (ex: `convertToCnigPci`, `extractCoordinates`)
- Constantes: SCREAMING_SNAKE_CASE (ex: `INPUT_FILE_SOCIAL`)
- Variables: camelCase descriptif (ex: `$cadastralData`, `$closestParcel`)

### Documentation

- Tous les services ont des commentaires de classe expliquant leur rôle
- Les méthodes publiques ont des docblocks complets avec:
    - Description détaillée
    - Exemples de données
    - Types de paramètres et retour
    - Notes sur les cas particuliers
- Éviter les commentaires évidents, privilégier du code auto-documenté
- Commenter uniquement les algorithmes complexes ou les choix non-évidents

### Tests

Les tests unitaires doivent couvrir:
- Conversion MAJIC → CNIG avec différents formats
- Extraction de coordonnées depuis GeoJSON valides/invalides
- Calcul de distances GPS
- Lecture/écriture CSV avec différents encodages
- Cas limites et erreurs (codes invalides, fichiers vides, formats incorrects)

## Formats de Données

### Code MAJIC (14 caractères)

```
Format: DDCCCSSSSXNNNN
Exemple: 67482000010017

DD    = Département (2 digits)    → 67
CCC   = Commune (3 digits)        → 482
SSSS  = Section numérique (4)    → 0000
X     = Numéro de section (1)    → 1
NNNN  = Numéro parcelle (4)      → 0017
```

### Code CNIG PCI (12 caractères)

```
Format: DDCCCSSNNNN
Exemple: 674820010017

DDDCC = Département + Commune (5) → 67482
SS    = Section (2-3 chars)       → 001 ou LC
NNNN  = Numéro parcelle (4)       → 0017
```

**Note importante**: Certaines parcelles utilisent des sections avec lettres (A, B, LC, EB, etc.) qui nécessitent un traitement spécial via la colonne `N_SECTION` du fichier cadastral.

### GeoJSON

Les géométries cadastrales sont au format GeoJSON avec des polygones:

```json
{
  "type": "Polygon",
  "coordinates": [
    [
      [longitude, latitude],
      [longitude, latitude],
      ...
    ]
  ]
}
```

Coordonnées au format WGS84, ordre `[longitude, latitude]` (inverse de l'ordre habituel latitude/longitude).

## Fichiers Importants

### Fichiers d'Entrée

- `parcelles-des-personnes-morales.csv` - Logements sociaux avec codes MAJIC
- `parcelles_cadastrales.csv` - Parcelles cadastrales avec géométries GeoJSON

### Fichier de Sortie

- `parcelles_resultat.csv` - Fusion des deux avec colonnes:
  - Toutes les colonnes du fichier d'entrée social
  - `Geo Shape` - Polygone GeoJSON de la parcelle
  - GPS vidé quand une correspondance est trouvée
  - (Optionnel) `code_parcelle_cnig` - Code converti

### Documentation

- `README.md` - Documentation principale pour les utilisateurs
- `claude.md` - Ce fichier, guide pour Claude

## Stratégie de Matching

Le système utilise un matching direct par code:

### Match par Code

```php
$cnigCode = $majicConverter->convertToCnigPci($majicCode);
if (isset($cadastralData[$cnigCode])) {
    // Match trouvé - utilise le polygone cadastral
} else {
    // Pas de match - utilise le point GPS de la parcelle
}
```

Si aucune correspondance directe n'est trouvée, le système utilise les coordonnées GPS du fichier MAJIC pour créer une géométrie de type Point.

## Points d'Attention

### Encodage

Les fichiers CSV utilisent UTF-8 avec BOM et séparateur `;` (standard français). Le BOM est automatiquement géré par PHP.

### Performance

- **Indexation cadastrale**: Les données cadastrales sont indexées par code CNIG dans un tableau associatif pour un accès O(1)

### Cas Particuliers

1. **Sections avec lettres**: Certaines parcelles ont des sections alphabétiques (LC, EB, etc.) stockées dans la colonne `N_SECTION`
2. **Parcelles manquantes**: Toutes les parcelles MAJIC n'ont pas d'équivalent CNIG (suppressions, fusions, divisions)
3. **Désynchronisation**: Les données MAJIC et CNIG peuvent être de dates différentes

## Ressources

- **Documentation GeoJSON**: https://geojson.org/
- **Visualiseur GeoJSON**: https://geojson.io
- **Cartographie**: https://umap.openstreetmap.fr
- **Données cadastrales**: https://data.strasbourg.eu
- **Symfony Console**: https://symfony.com/doc/current/console.html
