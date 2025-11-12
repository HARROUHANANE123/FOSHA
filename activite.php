<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
include 'bd.php';

// Récupérer les filtres de recherche
$recherche = $_GET['recherche'] ?? '';
$categorie_id = $_GET['categorie'] ?? '';
$date = $_GET['date'] ?? '';
$ville = $_GET['ville'] ?? '';
$prix_min = $_GET['prix_min'] ?? '';
$prix_max = $_GET['prix_max'] ?? '';
$tri = $_GET['tri'] ?? 'date_asc';

// Construire la requête de base
$requete_sql = "
    SELECT a.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur,
           u.prenom as organisateur_prenom, u.nom as organisateur_nom,
           COUNT(p.id) as participants_actuels
    FROM activites a
    LEFT JOIN categories c ON a.categorie_id = c.id
    LEFT JOIN utilisateurs u ON a.organisateur_id = u.id
    LEFT JOIN participations p ON a.id = p.activite_id
    WHERE a.date_activite > NOW() AND a.statut = 'active'
";

$parametres = [];

// Appliquer les filtres
if (!empty($recherche)) {
    $requete_sql .= " AND (a.titre LIKE ? OR a.description LIKE ? OR a.lieu LIKE ?)";
    $parametres[] = "%$recherche%";
    $parametres[] = "%$recherche%";
    $parametres[] = "%$recherche%";
}

if (!empty($categorie_id)) {
    $requete_sql .= " AND a.categorie_id = ?";
    $parametres[] = $categorie_id;
}

if (!empty($date)) {
    $requete_sql .= " AND DATE(a.date_activite) = ?";
    $parametres[] = $date;
}

if (!empty($ville)) {
    $requete_sql .= " AND a.ville = ?";
    $parametres[] = $ville;
}

if (!empty($prix_min)) {
    $requete_sql .= " AND a.prix >= ?";
    $parametres[] = $prix_min;
}

if (!empty($prix_max)) {
    $requete_sql .= " AND a.prix <= ?";
    $parametres[] = $prix_max;
}

// Grouper par activité
$requete_sql .= " GROUP BY a.id";

// Appliquer le tri
switch ($tri) {
    case 'date_desc':
        $requete_sql .= " ORDER BY a.date_activite DESC";
        break;
    case 'prix_asc':
        $requete_sql .= " ORDER BY a.prix ASC";
        break;
    case 'prix_desc':
        $requete_sql .= " ORDER BY a.prix DESC";
        break;
    case 'participants_asc':
        $requete_sql .= " ORDER BY participants_actuels ASC";
        break;
    case 'participants_desc':
        $requete_sql .= " ORDER BY participants_actuels DESC";
        break;
    default:
        $requete_sql .= " ORDER BY a.date_activite ASC";
}

try {
    // Exécuter la requête
    $requete = $connexion->prepare($requete_sql);
    $requete->execute($parametres);
    $activites = $requete->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les catégories pour le filtre
    $requete_categories = $connexion->query("SELECT * FROM categories WHERE statut = 'active' ORDER BY nom");
    $categories = $requete_categories->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les villes disponibles
    $requete_villes = $connexion->query("SELECT DISTINCT ville FROM activites WHERE statut = 'active' ORDER BY ville");
    $villes = $requete_villes->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $erreur = "Erreur lors de la récupération des activités : " . $e->getMessage();
    $activites = [];
    $categories = [];
    $villes = [];
}

// Fonction pour déterminer le statut d'une activité
function getStatutActivite($activite) {
    $maintenant = new DateTime();
    $date_activite = new DateTime($activite['date_activite']);
    
    if ($date_activite < $maintenant) {
        return 'terminee';
    } elseif ($activite['participants_actuels'] >= $activite['participants_max']) {
        return 'complet';
    } elseif ($date_activite->format('Y-m-d') === $maintenant->format('Y-m-d')) {
        return 'aujourdhui';
    } else {
        return 'disponible';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activités - FOSHA Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- En-tête -->
    <?php include 'header.php'; ?>

    <!-- Contenu principal -->
    <main class="container my-4">
        <!-- En-tête de page -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1">
                            <i class="fas fa-list text-primary me-2"></i>
                            Découvrez le Maroc
                        </h1>
                        <p class="text-muted mb-0">
                            <?php echo count($activites); ?> expérience(s) unique(s) vous attendent
                        </p>
                    </div>
                    <?php if (isset($_SESSION['utilisateur']) && $_SESSION['utilisateur']['est_organisateur']): ?>
                        <a href="create_activity.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Créer une activité
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar des filtres -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter text-primary me-2"></i>
                            Filtres
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" id="filterForm">
                            <!-- Recherche texte -->
                            <div class="mb-3">
                                <label for="recherche" class="form-label small fw-bold">Recherche</label>
                                <input type="text" 
                                       class="form-control form-control-sm" 
                                       id="recherche" 
                                       name="recherche" 
                                       value="<?php echo htmlspecialchars($recherche); ?>" 
                                       placeholder="Randonnée, cuisine, musique...">
                            </div>

                            <!-- Catégorie -->
                            <div class="mb-3">
                                <label for="categorie" class="form-label small fw-bold">Catégorie</label>
                                <select class="form-select form-select-sm" id="categorie" name="categorie">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $categorie): ?>
                                        <option value="<?php echo $categorie['id']; ?>" 
                                                <?php echo ($categorie_id == $categorie['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($categorie['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Ville -->
                            <div class="mb-3">
                                <label for="ville" class="form-label small fw-bold">Ville</label>
                                <select class="form-select form-select-sm" id="ville" name="ville">
                                    <option value="">Toutes les villes</option>
                                    <?php foreach ($villes as $ville_option): ?>
                                        <option value="<?php echo htmlspecialchars($ville_option); ?>" 
                                                <?php echo ($ville == $ville_option) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ville_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Date -->
                            <div class="mb-3">
                                <label for="date" class="form-label small fw-bold">Date</label>
                                <input type="date" 
                                       class="form-control form-control-sm" 
                                       id="date" 
                                       name="date" 
                                       value="<?php echo htmlspecialchars($date); ?>">
                            </div>

                            <!-- Prix -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Prix (MAD)</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="prix_min" 
                                               placeholder="Min" 
                                               min="0" 
                                               step="10"
                                               value="<?php echo htmlspecialchars($prix_min); ?>">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="prix_max" 
                                               placeholder="Max" 
                                               min="0" 
                                               step="10"
                                               value="<?php echo htmlspecialchars($prix_max); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Tri -->
                            <div class="mb-3">
                                <label for="tri" class="form-label small fw-bold">Trier par</label>
                                <select class="form-select form-select-sm" id="tri" name="tri">
                                    <option value="date_asc" <?php echo ($tri == 'date_asc') ? 'selected' : ''; ?>>Date (plus proche)</option>
                                    <option value="date_desc" <?php echo ($tri == 'date_desc') ? 'selected' : ''; ?>>Date (plus lointaine)</option>
                                    <option value="prix_asc" <?php echo ($tri == 'prix_asc') ? 'selected' : ''; ?>>Prix (croissant)</option>
                                    <option value="prix_desc" <?php echo ($tri == 'prix_desc') ? 'selected' : ''; ?>>Prix (décroissant)</option>
                                    <option value="participants_asc" <?php echo ($tri == 'participants_asc') ? 'selected' : ''; ?>>Participants (croissant)</option>
                                    <option value="participants_desc" <?php echo ($tri == 'participants_desc') ? 'selected' : ''; ?>>Participants (décroissant)</option>
                                </select>
                            </div>

                            <!-- Boutons d'action -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Appliquer
                                </button>
                                <a href="activite.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times me-1"></i>Réinitialiser
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistiques rapides -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-chart-pie text-info me-2"></i>
                            Statistiques
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Activités trouvées</span>
                                <strong><?php echo count($activites); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Gratuites</span>
                                <strong>
                                    <?php 
                                    $gratuites = array_filter($activites, function($a) { return $a['prix'] == 0; });
                                    echo count($gratuites);
                                    ?>
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Cette semaine</span>
                                <strong>
                                    <?php
                                    $cette_semaine = array_filter($activites, function($a) {
                                        $date_activite = new DateTime($a['date_activite']);
                                        $maintenant = new DateTime();
                                        $interval = $maintenant->diff($date_activite);
                                        return $interval->days <= 7;
                                    });
                                    echo count($cette_semaine);
                                    ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="col-lg-9">
                <!-- Barre d'outils -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3"><?php echo count($activites); ?> activité(s) trouvée(s)</span>
                    </div>
                </div>

                <!-- Liste des activités -->
                <?php if (empty($activites)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h3 class="text-muted">Aucune activité trouvée</h3>
                        <p class="text-muted mb-4">Essayez de modifier vos critères de recherche</p>
                        <a href="activite.php" class="btn btn-primary">
                            <i class="fas fa-times me-1"></i>Réinitialiser les filtres
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row" id="activitiesView">
                        <?php foreach ($activites as $activite): 
                            $statut = getStatutActivite($activite);
                            $date_activite = new DateTime($activite['date_activite']);
                        ?>
                            <div class="col-xl-4 col-md-6 mb-4 activity-item">
                                <div class="card h-100 activity-card shadow-sm">
                                    <!-- En-tête avec badge de statut -->
                                    <div class="card-header position-relative px-3 py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge" style="background-color: <?php echo $activite['categorie_couleur']; ?>">
                                                <?php echo $activite['categorie_icone']; ?> <?php echo htmlspecialchars($activite['categorie_nom']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <i class="fas fa-users me-1"></i>
                                                <?php echo $activite['participants_actuels']; ?>/<?php echo $activite['participants_max']; ?>
                                            </small>
                                        </div>
                                        
                                        <!-- Badges de statut -->
                                        <div class="position-absolute top-0 end-0 mt-2 me-2">
                                            <?php if ($statut == 'terminee'): ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-history me-1"></i>Terminée
                                                </span>
                                            <?php elseif ($statut == 'complet'): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times me-1"></i>Complet
                                                </span>
                                            <?php elseif ($statut == 'aujourdhui'): ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-bolt me-1"></i>Aujourd'hui
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Disponible
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Corps de la carte -->
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($activite['titre']); ?></h5>
                                        
                                        <p class="card-text flex-grow-1 small text-muted">
                                            <?php echo nl2br(htmlspecialchars(substr($activite['description'], 0, 100) . '...')); ?>
                                        </p>

                                        <!-- Informations de localisation -->
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($activite['ville']); ?> - <?php echo htmlspecialchars($activite['lieu']); ?>
                                            </small>
                                        </div>

                                        <!-- Informations organisateur -->
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                Organisé par <?php echo htmlspecialchars($activite['organisateur_prenom'] . ' ' . $activite['organisateur_nom']); ?>
                                            </small>
                                        </div>

                                        <!-- Informations date et heure -->
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo $date_activite->format('d/m/Y'); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $date_activite->format('H:i'); ?>
                                            </small>
                                        </div>

                                        <!-- Pied de carte -->
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="h5 text-primary mb-0">
                                                    <?php echo ($activite['prix'] > 0) ? $activite['prix'] . ' MAD' : 'Gratuit'; ?>
                                                </span>
                                            </div>

                                            <!-- Bouton d'action principal -->
                                            <div class="d-grid">
                                                <?php if ($statut == 'terminee'): ?>
                                                    <span class="btn btn-secondary btn-sm disabled">
                                                        <i class="fas fa-history me-1"></i>Activité terminée
                                                    </span>
                                                <?php elseif ($statut == 'complet'): ?>
                                                    <span class="btn btn-danger btn-sm disabled">
                                                        <i class="fas fa-times me-1"></i>Complet
                                                    </span>
                                                <?php elseif (isset($_SESSION['utilisateur'])): ?>
                                                    <a href="activity_details.php?id=<?php echo $activite['id']; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-eye me-1"></i>Voir détails
                                                    </a>
                                                <?php else: ?>
                                                    <a href="connexion.php?redirect=activite.php" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-sign-in-alt me-1"></i>Se connecter
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <?php include 'footer.php'; ?>
</body>
</html>