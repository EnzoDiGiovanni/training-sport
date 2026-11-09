# Prompt Claude Code — Recompo, tracker sportif personnel

> À utiliser **après validation du prototype**. Place ce fichier à la racine du dépôt (`docs/PROMPT.md`) avec le prototype (`docs/prototype.html`), puis lance Claude Code avec : *« Lis docs/PROMPT.md et docs/prototype.html, puis commence par l'étape 0. »*

---

## 0. Rôle et méthode de travail

Tu es un développeur full stack senior (Laravel, Vue 3, Tailwind) et un product designer exigeant. Tu construis une application **personnelle, mono-utilisateur**, de suivi sportif pour une recomposition corporelle (course sur tapis + musculation avec haltères à domicile).

Règles de travail :

1. **Le prototype `docs/prototype.html` est la référence visuelle et fonctionnelle.** Ouvre-le, lis son code (CSS, données, logique) et reproduis fidèlement l'organisation des écrans, la hiérarchie visuelle, les couleurs, la typographie, les interactions et les textes. En cas de doute entre ce document et le prototype sur un point visuel, le prototype fait foi. Sur un point métier ou technique, ce document fait foi.
2. **Avance par étapes** (section 11). À la fin de chaque étape : tests verts, commit avec un message clair, court résumé de ce qui a été fait et de ce qui reste. Ne commence pas l'étape suivante si l'étape courante n'est pas fonctionnelle.
3. **Avant d'écrire du code pour une étape, présente un plan court** (fichiers créés ou modifiés, choix techniques). Si tu identifies une incohérence ou une meilleure option, dis-le explicitement plutôt que de suivre aveuglément.
4. **Vérifie les versions actuelles** des paquets (Laravel, Inertia, Vue, Tailwind, Chart.js) au moment de l'installation au lieu de supposer une version. Utilise les versions stables les plus récentes compatibles entre elles.
5. Pas de sur-ingénierie : pas de microservices, pas de CQRS, pas de repository pattern générique, pas de multi-tenant. Code simple, lisible, testé sur les règles métier.
6. Toute l'interface est en **français**. Locale `fr`, fuseau `Europe/Paris`, semaine commençant le **lundi**, nombres au format français (virgule décimale).

---

## 1. Contexte utilisateur

- Taille 167 cm, poids ≈ 66,5 kg, objectif : perdre progressivement de la masse grasse, gagner du muscle, améliorer l'endurance. Physique athlétique, pas de bodybuilding.
- 7 000 à 13 000 pas par jour.
- Course sur tapis autour de 9 km/h ; **tapis limité à 10 km/h**.
- Matériel : 2 haltères réglables de 10 kg max chacun, combinables en un haltère de 20 kg ; un banc (inclinable, à confirmer dans les paramètres).
- Toutes ces données sont **modifiables dans les paramètres** et servent à valider les saisies (vitesse max du tapis, charge max des haltères).

---

## 2. Stack technique

- **Backend** : Laravel (dernière version stable), PHP compatible, MySQL 8.
- **Frontend** : Vue 3 (Composition API, `<script setup>`), **Inertia.js** pour rester en monolithe sans API séparée. TypeScript si le starter kit officiel le propose, sinon JavaScript.
- **CSS** : Tailwind CSS, thème configuré à partir des tokens du prototype (section 9).
- **Graphiques** : `vue-chartjs` + Chart.js (léger, bien maintenu), **ou** composants SVG maison portés depuis le prototype (`drawChart` dans le prototype). Choisis l'une des deux options et justifie-la en une phrase. Ne pas installer de bibliothèque lourde.
- **Auth** : starter kit officiel Laravel (Vue + Inertia) avec login simple. **Désactiver l'inscription publique** ; l'utilisateur unique est créé par une commande artisan (`php artisan app:create-user`) ou par le seeder.
- **Tests** : Pest (ou PHPUnit si Pest n'est pas dans le starter kit). Tests Feature sur les règles métier, pas de tests de snapshot UI.
- **Qualité** : Laravel Pint, ESLint/Prettier si fournis par le starter kit.
- Endpoints JSON uniquement là où Inertia n'est pas adapté : sauvegarde automatique pendant une séance en cours (validation de série, saisie de poids/répétitions). Les routes JSON sont sous `/api/…` dans `routes/web.php` avec le middleware `auth` (session), pas de Sanctum token.

---

## 3. Modèle de données

Principe central : **séparer strictement le programme théorique, les séances réalisées et l'historique des performances.** Une séance réalisée stocke une **copie (snapshot)** des noms et cibles au moment où elle a été faite. Modifier ou supprimer un élément du programme ne doit **jamais** modifier ou supprimer une séance réalisée.

### Tables

**users** (starter kit) + **user_profiles** (1–1)
- `height_cm` smallint, `target_weight_kg` decimal(5,2), `steps_min`, `steps_max` int
- `run_level_kmh` decimal(4,1), `treadmill_max_kmh` decimal(4,1) défaut 10, `treadmill_incline_max` decimal(4,1)
- `dumbbell_max_kg` decimal(5,2) défaut 10, `dumbbell_count` tinyint défaut 2, `dumbbells_combinable` bool défaut true, `weight_step_kg` decimal(4,2) défaut 1, `has_bench` bool, `bench_incline` bool
- `weekly_sessions_goal` tinyint défaut 5, `weekly_km_goal` decimal(5,1) défaut 15, `default_rest_seconds` smallint défaut 90
- `goals` JSON (liste de textes)
- Le **poids actuel n'est pas stocké ici** : c'est la dernière ligne de `weight_records`.

**exercises** (bibliothèque)
- `user_id` nullable (null = exercice de base), `name`, `slug` unique
- `primary_muscle`, `secondary_muscles` (string), `muscle_group` enum (`chest`, `back`, `legs`, `shoulders`, `arms`, `core`) pour les filtres
- `equipment`, `description` text, `instructions` JSON (liste), `common_mistakes` JSON (liste)
- `measurement` enum `reps|time`, `load_type` enum `two_dumbbells|one_dumbbell|bodyweight`, `is_unilateral` bool
- `image_path` nullable (image importée), `illustration_key` nullable (illustration SVG intégrée, voir section 6)
- `is_custom` bool, `archived_at` nullable (archivage au lieu de suppression si l'exercice a un historique)

**training_programs** : `user_id`, `name`, `is_active` (un seul actif ; prévoir la table même si un seul programme existe pour l'instant).

**workouts** (séances type du programme)
- `training_program_id`, `name`, `type` enum `run|strength|recovery|rest`, `objective` text, `estimated_duration_min` nullable (calculée pour la course), `position`, `archived_at` nullable

**training_days** : `training_program_id`, `day_of_week` (1 = lundi … 7 = dimanche), `workout_id` nullable (null = repos). Unique (`training_program_id`, `day_of_week`).

**workout_exercises** : `workout_id`, `exercise_id`, `position`, `sets`, `reps_min`, `reps_max` (secondes si `measurement = time`), `target_weight_kg` decimal(5,2) nullable, `rest_seconds`, `notes` nullable.

**run_segments** : `workout_id`, `parent_id` nullable (bloc de répétition), `position`, `kind` enum `warmup|run|fast|recovery|cooldown|repeat`, `label`, `duration_min` decimal(5,2), `speed_min_kmh`, `speed_max_kmh` decimal(4,1), `incline_pct` decimal(4,1), `repeat_count` nullable (uniquement pour `kind = repeat`). Un segment `repeat` contient des enfants (ex. 5 × [1 min à 9,5–10 km/h + 2 min à 7 km/h]).

**workout_sessions** (séances réalisées)
- `user_id`, `workout_id` nullable (`nullOnDelete`), **`workout_name`** (snapshot), `type` (snapshot), `performed_on` date, `started_at`, `ended_at` nullable, `duration_seconds`, `status` enum `in_progress|completed|abandoned`
- `perceived_effort` tinyint nullable (1 Très facile … 5 Très dur), `comment` text nullable, `activity` nullable (pour la récupération : Marche, Mobilité…)
- `planned_snapshot` JSON : copie des cibles du programme au démarrage (segments de course ou exercices avec séries/reps/charge/repos)

**session_exercises** : `workout_session_id`, `exercise_id` nullable (`nullOnDelete`), **`exercise_name`**, `measurement`, `load_type`, `is_unilateral` (snapshots), `position`, `target_sets`, `target_reps_min`, `target_reps_max`, `target_weight_kg`, `rest_seconds`, `note`.

**exercise_sets** : `session_exercise_id`, `set_number`, `weight_kg` decimal(5,2) (charge **par haltère** pour `two_dumbbells`), `reps` (ou secondes), `is_completed`, `completed_at`.

**running_records** (1–1 avec la séance) : `workout_session_id` unique, `distance_km` decimal(5,2), `max_speed_kmh` nullable, `incline_pct` nullable, `cycles_completed` nullable, `cycles_planned` nullable. **La vitesse moyenne n'est pas stockée** : elle est calculée (`distance / durée`), via un accesseur.

**weight_records** : `user_id`, `measured_on` date, `weight_kg` decimal(5,2). Unique (`user_id`, `measured_on`) : une nouvelle pesée le même jour remplace l'ancienne.

### Contraintes de suppression
- Supprimer une séance du programme : **archivage** (`archived_at`) si elle a des séances réalisées, sinon suppression. Les jours où elle était planifiée passent en repos.
- Supprimer un exercice personnalisé utilisé dans le programme : refusé avec un message clair. S'il a un historique : archivage.
- Aucune cascade de `workouts` ou `exercises` vers `workout_sessions`, `session_exercises`, `exercise_sets`.

---

## 4. Règles métier (à tester)

1. **Snapshot** : démarrer une séance copie les cibles du programme dans `planned_snapshot` et `session_exercises`. Modifier ensuite le programme ne change rien à la séance.
2. **Dashboard dynamique** : l'entraînement du jour est déterminé par `training_days` du programme actif ; toute modification du programme se reflète immédiatement.
3. **Pré-remplissage** d'une séance de musculation : pour chaque série, reprendre le poids et les répétitions de la **dernière performance** sur cet exercice (série par série), sinon les cibles du programme.
4. **Volume** = Σ (poids × reps × nb d'haltères × 2 si unilatéral) sur les séries validées. `two_dumbbells` → ×2, `one_dumbbell` → ×1, `bodyweight` → exclu du volume. Afficher sous le chiffre « charge × reps × haltères ».
5. **Vitesse moyenne** = distance (km) / durée (h), calculée côté serveur et affichée en direct côté client dès que distance et durée sont renseignées.
6. **Validations course** : vitesse max ≤ vitesse max du tapis ; vitesse moyenne calculée ≤ vitesse max du tapis (sinon erreur « vérifie la distance ou la durée ») ; vitesse max ≥ vitesse moyenne ; inclinaison entre 0 et le max du profil ; durée au format `mm:ss` ou `h:mm:ss`.
7. **Validations musculation** : charge ≤ `dumbbell_max_kg` pour `two_dumbbells`, ≤ `dumbbell_max_kg × 2` pour `one_dumbbell` si combinable. Pendant la séance c'est un **avertissement visuel** (bordure rouge + infobulle), pas un blocage. Dans l'éditeur de programme, c'est un avertissement listé en haut de page.
8. **Programme** : `reps_min ≤ reps_max`, `sets ≥ 1`, vitesses de course ≤ vitesse max du tapis, au moins un exercice (musculation) ou une phase (course).
9. **Calories** : jamais présentées comme exactes. Toujours préfixées par « ≈ » avec la mention « estimation indicative ». Course : ≈ 1 kcal × kg × km. Musculation : MET ≈ 4,5. Non stockées en base.
10. **Records** (affichés dans le récapitulatif) : nouvelle charge max sur un exercice, volume en hausse de plus de 5 %, plus longue distance et meilleure vitesse moyenne **pour une même séance type** (ne pas comparer fondamentale et fractionné).
11. **Statut de la semaine** par jour : réalisé, aujourd'hui, à venir, manqué (passé sans séance), repos, récupération.
12. **Poids** : moyenne mobile sur 7 jours, variation sur 30 jours, rythme moyen en kg/semaine. Afficher une alerte douce si le rythme dépasse ≈ 0,75 kg/semaine de perte (risque de perte musculaire en recomposition).
13. **Une seule séance `in_progress` à la fois.** Si une séance est en cours et que l'utilisateur en démarre une autre : proposer de reprendre ou d'abandonner.

---

## 5. Écrans et parcours

Navigation : **Accueil, Entraînements, Programme, Progression, Paramètres.** Sidebar sur desktop, barre d'onglets fixe en bas sur mobile. Si une séance est en cours, une pastille « Séance en cours · chrono · Reprendre » est visible partout.

1. **Accueil** : date et numéro de semaine ; carte « Aujourd'hui » (type, nom, objectif, durée estimée, distance estimée pour la course, nombre d'exercices et de séries pour la musculation) ; bouton principal **« Commencer ma séance »** ; liste des exercices avec image, séries × reps et charge, ou déroulé de la course avec frise des vitesses ; message de récupération les jours de repos. Frise des 7 jours cliquable pour prévisualiser un autre jour et le démarrer. Statistiques rapides : séances de la semaine / objectif, km / objectif, temps total, poids avec mini courbe et variation 30 jours. Trois dernières séances.
2. **Détail d'une séance type** (course et musculation) : comme dans le prototype, avec « Dernière fois » et bouton Modifier.
3. **Séance de musculation en cours** (inspiration Hevy) : en-tête collant avec durée, volume total en direct, séries validées / total, bouton Terminer ; puces de navigation entre exercices ; pour chaque exercice : image animée, groupe musculaire, cibles, repos, « Dernière fois », bouton historique ; tableau **Série | Précédent | kg | Reps | ✓** éditable ; ajout/retrait de série ; note ; « Exercice suivant ». Valider une série lance le **chrono de récupération** (barre flottante, −15 s, +15 s, Passer) et passe automatiquement à l'exercice suivant quand toutes les séries sont faites. **Sauvegarde automatique** de chaque modification (debounce ≈ 400 ms) ; la séance survit à un rechargement de page. La durée se calcule depuis `started_at`, pas depuis un compteur côté client.
4. **Séance de course en cours** : chronomètre (démarrer, pause, reprendre, remise à zéro) ; phase actuelle du programme avec vitesse cible, temps restant et phase suivante ; frise avec curseur de progression ; formulaire : durée (bouton « Utiliser le chrono »), distance, vitesse moyenne calculée en direct, vitesse max, inclinaison, cycles effectués (si fractionné), ressenti, commentaire, calories estimées. L'état du chrono est conservé côté client (`localStorage`) pour survivre à un rechargement.
5. **Récapitulatif** : durée, volume, séries, répétitions (ou distance, vitesses), records, comparaison avec la séance précédente du même type, ressenti et commentaire, Enregistrer / Reprendre.
6. **Entraînements (historique)** : filtres Toutes / Course / Musculation / Récupération ; regroupement Par semaine / Par mois avec totaux par groupe ; chaque séance ouvre un panneau de détail **modifiable** (date, durée, distance, vitesses, séries) et supprimable avec confirmation.
7. **Progression** : onglets Course (km hebdomadaires avec ligne d'objectif, vitesse moyenne, distance, durée, records, filtre par séance type), Musculation (sélecteur d'exercice, charge max par séance, répétitions, volume, séances par semaine, historique de l'exercice), Poids (courbe des pesées + moyenne mobile 7 jours, ligne d'objectif, saisie d'une pesée, liste des dernières pesées).
8. **Programme** : semaine type (un sélecteur par jour) ; cartes des séances (modifier, dupliquer, supprimer) ; nouvelle séance (choix du type). **Éditeur** : nom, type, objectif, durée, jours planifiés ; musculation : exercices avec réordonnancement (glisser-déposer + boutons haut/bas pour le clavier), séries, reps min/max, charge, repos, ajout depuis la bibliothèque ; course : phases et blocs de répétition, vitesses, durées, inclinaison, durée totale et distance estimée calculées. Bandeau rappelant que l'historique n'est pas modifié.
9. **Bibliothèque d'exercices** : recherche, filtre par groupe musculaire, cartes avec image ; fiche exercice (image animée, positions de départ et finale, muscles, matériel, description, consignes, erreurs fréquentes, mini graphique de performances) ; création et modification d'exercices personnalisés avec import d'image.
10. **Paramètres** : profil, objectifs, activité quotidienne, course et tapis, matériel, repos par défaut. Validation côté serveur avec messages par champ. Modifier le poids ici crée une pesée datée du jour.

---

## 6. Images des exercices (obligatoire dès la première version)

Chaque exercice doit afficher une **vraie illustration du mouvement**, jamais une icône générique partagée.

- **Illustrations intégrées** : porte le système d'illustrations du prototype (`ILLOS`, `illoSvg` dans `docs/prototype.html`) en un composant `ExerciseIllustration.vue` (props : `illustrationKey`, `animated`, `pose: 'start' | 'end' | null`). Chaque illustration définit deux poses (départ, fin) d'une silhouette de profil et anime le passage de l'une à l'autre. Respecter `prefers-reduced-motion`. Les 13 exercices du seeder ont chacun leur `illustration_key`, plus `run` (tapis) et `walk` (récupération).
- **Images importées** : un exercice peut recevoir une photo ou un schéma (`image_path`, disque `public`, `storage/app/public/exercises`). Validation : `image`, `mimes:jpg,jpeg,png,webp`, `max:2048`. Si `image_path` existe, il remplace l'illustration.
- **Composant unique** `ExerciseImage.vue` qui choisit image importée → illustration → silhouette neutre « Ajoute une image », dans un ratio 16:10 constant.
- Images présentes dans : détail des séances, séance en cours, bibliothèque, éditeur de programme, historique de l'exercice, récapitulatif.
- Les illustrations du prototype sont des schémas simples. Prévois qu'elles puissent être remplacées plus tard par de vraies photos ou des illustrations professionnelles **sous licence** sans changer le code.

---

## 7. Organisation du code

```
app/
  Models/                      UserProfile, Exercise, TrainingProgram, Workout, TrainingDay,
                               WorkoutExercise, RunSegment, WorkoutSession, SessionExercise,
                               ExerciseSet, RunningRecord, WeightRecord
  Enums/                       WorkoutType, ExerciseMeasurement, LoadType, SegmentKind, SessionStatus
  Http/Controllers/            DashboardController, WorkoutController, ProgramController,
                               ExerciseController, LiveSessionController, SessionController,
                               StatsController, WeightController, SettingsController
  Http/Requests/               une FormRequest par écriture (Store/Update…)
  Services/                    SessionStarter (snapshot + pré-remplissage),
                               VolumeCalculator, RunMetrics (vitesse moyenne, distance estimée),
                               PersonalRecords, WeekStatus, StatsService, CalorieEstimator
  Actions/ (facultatif)        DuplicateWorkout, ArchiveWorkout…
resources/js/
  Pages/                       Dashboard, Workouts/Show, Live/Strength, Live/Run, Live/Summary,
                               History/Index, Stats/Index, Program/Index, Program/Edit,
                               Exercises/Index, Settings/Edit
  Components/                  AppShell, NavSidebar, TabBar, LiveSessionPill, WeekStrip, StatTile,
                               ExerciseImage, ExerciseIllustration, SetTable, RestTimer, RunTimeline,
                               Chronometer, EffortRating, LineChart, BarChart, Sheet (modal/bottom
                               sheet), Toast, Stepper, SegmentEditor, ExercisePicker
  composables/                 useLiveSession (autosave), useRestTimer, useChronometer, useFormat
                               (nombres, durées, dates en français)
```

- La logique métier vit dans les **services PHP**, testée unitairement. Les composants Vue affichent et appellent les routes.
- Les calculs affichés en direct côté client (vitesse moyenne, volume) utilisent les mêmes formules que le serveur ; le serveur reste la source de vérité à l'enregistrement.

---

## 8. Seeders

- `UserSeeder` : utilisateur unique (identifiants via `.env` : `APP_OWNER_EMAIL`, `APP_OWNER_PASSWORD`) et profil (valeurs de la section 1).
- `ExerciseSeeder` : les 13 exercices du prototype avec nom, muscles, matériel, description, consignes, erreurs fréquentes, `measurement`, `load_type`, `is_unilateral`, `illustration_key` (copie les textes du prototype, fonction `makeExercises`).
- `ProgramSeeder` : le programme du prototype (`makeWorkouts`) : Lundi endurance fondamentale, Mardi musculation A, Mercredi fractionné (bloc 5 × [1 min 9,5–10 km/h + 2 min 7 km/h]), Jeudi récupération, Vendredi musculation B, Samedi endurance longue, Dimanche repos.
- `DemoHistorySeeder` (**séparé et facultatif**, `php artisan db:seed --class=DemoHistorySeeder`) : 6 semaines d'historique cohérent et pesées, porté de `makeHistory` et `makeWeights`. Ne jamais l'exécuter par défaut en production.

---

## 9. Design system (repris du prototype)

- Thème **sombre unique**. Tokens : `bg #09090B`, `surface #121214`, `surface-2 #19191C`, `surface-3 #222226`, `border #26262B`, `border-strong #34343A`, `text #F4F4F5`, `muted #A6A6AF`, `faint #8A8A94`, `accent #C5F23D` (lime, réservé aux actions et aux progrès), `run #8FB8FF`, `recovery #9BD3C7`, `warn #F2B13D`, `danger #F27272`.
- Polices : **Geist** (texte) et **Geist Mono** (tous les chiffres, `tabular-nums`). Auto-héberger les polices (paquet npm ou fichiers locaux) plutôt que Google Fonts.
- Rayons 8 / 12 / 16 px, bordures fines, pas d'ombres sauf sur les éléments flottants (chrono de repos, panneaux).
- Cibles tactiles ≥ 44 px, focus clavier visible (anneau lime), contrastes AA, `aria-label` sur les boutons-icônes, panneaux en `role="dialog"` fermables avec Échap.
- Mobile : panneaux en bottom sheet, barre d'onglets fixe avec `env(safe-area-inset-bottom)`, tableaux de séries utilisables au pouce (inputs `inputmode="decimal"`).
- Animations légères (≤ 250 ms), désactivées avec `prefers-reduced-motion`.

---

## 10. Hors périmètre de la V1

Fonctions sociales, multi-utilisateurs, synchronisation montre ou Strava, suivi nutritionnel, notifications push, application native. Prévoir seulement de ne pas les rendre impossibles. Une PWA installable (manifest + icône) est **facultative** et peut être proposée en fin de projet.

---

## 11. Étapes de réalisation

Chaque étape se termine par : tests verts, `pint`, commit, résumé.

0. **Initialisation** : projet Laravel avec starter kit Vue + Inertia + Tailwind, MySQL, inscription désactivée, locale et fuseau, tokens Tailwind, polices, `AppShell` avec navigation desktop et mobile. Copier `docs/prototype.html` dans le dépôt.
1. **Modèle de données** : migrations, modèles, enums, relations, factories, seeders (sauf historique de démo). Tests : relations, contraintes d'unicité, absence de cascade vers l'historique.
2. **Bibliothèque d'exercices + images** : `ExerciseIllustration` (portage des 15 illustrations), `ExerciseImage`, liste, fiche, création/modification avec import d'image.
3. **Programme** : semaine type, CRUD des séances, duplication, archivage, éditeur musculation (réordonnancement) et course (phases, blocs de répétition), validations et avertissements matériel/tapis.
4. **Accueil** : séance du jour, prévisualisation des autres jours, frise de la semaine, statistiques rapides, dernières séances.
5. **Séance de musculation en cours** : `SessionStarter` (snapshot + pré-remplissage), tableau des séries, autosave, chrono de repos, navigation entre exercices, historique d'un exercice, reprise après rechargement, récapitulatif avec records.
6. **Séance de course en cours** : chronomètre, phase actuelle, formulaire, calculs et validations, récapitulatif. Enregistrement d'une activité de récupération.
7. **Historique** : filtres, regroupements, panneau de détail modifiable, suppression.
8. **Progression** : graphiques course, musculation, poids ; saisie des pesées ; moyenne mobile.
9. **Paramètres** : formulaires et validations, effets sur les contrôles (tapis, haltères).
10. **Finitions** : responsive vérifié à 390 px et 1440 px, accessibilité clavier, états vides, messages d'erreur, `DemoHistorySeeder`, README (installation, création de l'utilisateur, sauvegarde de la base).
11. **Déploiement sur VPS** (section 13).

### Tests minimum attendus
- Modifier une séance du programme ne modifie pas une séance réalisée (noms, cibles, séries).
- Supprimer une séance du programme conserve son historique et passe ses jours en repos.
- Calcul du volume selon le type de charge et l'unilatéral.
- Calcul de la vitesse moyenne et refus d'une vitesse supérieure à celle du tapis.
- Pré-remplissage depuis la dernière performance.
- Une seule séance `in_progress` à la fois.
- Statut de la semaine (réalisé, manqué, à venir, repos).
- Une pesée le même jour remplace la précédente.

---

## 12. Points à confirmer avec moi avant ou pendant le développement

Pose-moi ces questions au moment où elles deviennent bloquantes, pas toutes au début :

1. Les haltères réglables : par quel pas peut-on changer la charge (0,5 kg, 1 kg, 2,5 kg) ? Valeur par défaut retenue : 1 kg.
2. Le banc est-il inclinable (nécessaire pour le développé incliné) ?
3. La charge saisie est-elle bien **par haltère** pour les exercices à deux haltères (choix retenu dans le prototype) ?
4. Fournisseur du VPS et choix entre Coolify et une installation manuelle (section 13). Le VPS sera partagé avec des projets d'école.

---

## 13. Déploiement (VPS Linux, Ubuntu LTS)

L'application est hébergée sur un VPS personnel (2 vCPU / 4 Go de RAM minimum) **partagé avec d'autres projets**. Elle doit donc être isolée et se déployer sans manipulation manuelle sur le serveur.

- **Méthode par défaut : Coolify** (PaaS open source auto-hébergé) qui déploie depuis le dépôt GitHub à chaque push sur `main`. Fournis un `Dockerfile` de production (PHP-FPM + Nginx, ou FrankenPHP si tu le justifies) avec build des assets Vite en étape multi-stage, et un `docker-compose.yml` pour le développement local (app + MySQL).
- **Méthode alternative** documentée dans le README : Nginx + PHP-FPM + MySQL installés directement, déploiement par script (`git pull`, `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, caches `config`, `route`, `view`).
- **Configuration** : tout passe par les variables d'environnement (`.env.example` complet et commenté) ; `APP_ENV=production`, `APP_DEBUG=false`, HTTPS forcé, cookies sécurisés, `TrustProxies` configuré si l'app est derrière un reverse proxy.
- **Stockage** : `storage/` (dont les images d'exercices importées) sur un **volume persistant**, jamais dans l'image Docker ; `php artisan storage:link` au démarrage.
- **Sauvegardes** : `spatie/laravel-backup` (ou un équivalent justifié), planifié chaque nuit par le scheduler Laravel : dump de la base + `storage/app/public`, envoyé **hors du VPS** (stockage S3 compatible ou autre disque distant configurable), avec rétention (7 quotidiennes, 4 hebdomadaires). Ajoute une commande documentée pour restaurer une sauvegarde et teste-la.
- **Scheduler** : un seul cron (`schedule:run` chaque minute) ou le conteneur scheduler de Coolify. Pas de file d'attente nécessaire en V1.
- **Sécurité applicative** : limitation du nombre de tentatives de connexion, pas de route d'inscription, en-têtes de sécurité basiques. La sécurité du serveur (SSH par clé, pare-feu, mises à jour) est hors code, mais liste-la dans le README sous forme de checklist.
- **Healthcheck** : route `/up` (fournie par Laravel) utilisée par Coolify.
