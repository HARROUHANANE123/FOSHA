<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté et est organisateur
if (!isset($_SESSION['utilisateur']) || !$_SESSION['utilisateur']['est_organisateur']) {
    header('Location: page_organisateur.php');
    exit;
}

// Inclure la connexion à la base de données
include 'bd.php';

$utilisateur = $_SESSION['utilisateur'];

// Récupérer les statistiques de l'organisateur
try {
    // Compter les activités créées
    $requete_activites_crees = $connexion->prepare("
        SELECT COUNT(*) as total 
        FROM activites 
        WHERE organisateur_id = ?
    ");
    $requete_activites_crees->execute([$utilisateur['id']]);
    $activites_crees = $requete_activites_crees->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les participants totaux
    $requete_participants_totaux = $connexion->prepare("
        SELECT COUNT(*) as total 
        FROM participations p
        JOIN activites a ON p.activite_id = a.id
        WHERE a.organisateur_id = ?
    ");
    $requete_participants_totaux->execute([$utilisateur['id']]);
    $participants_totaux = $requete_participants_totaux->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les activités à venir
    $requete_activites_avenir = $connexion->prepare("
        SELECT COUNT(*) as total 
        FROM activites 
        WHERE organisateur_id = ? AND date_activite > NOW() AND statut = 'active'
    ");
    $requete_activites_avenir->execute([$utilisateur['id']]);
    $activites_avenir = $requete_activites_avenir->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupérer les revenus totaux
    $requete_revenus = $connexion->prepare("
        SELECT SUM(a.prix * pcount.nb_participants) as total_revenus
        FROM activites a
        JOIN (
            SELECT activite_id, COUNT(*) as nb_participants
            FROM participations
            GROUP BY activite_id
        ) pcount ON a.id = pcount.activite_id
        WHERE a.organisateur_id = ? AND a.prix > 0
    ");
    $requete_revenus->execute([$utilisateur['id']]);
    $revenus_totaux = $requete_revenus->fetch(PDO::FETCH_ASSOC)['total_revenus'] ?? 0;
    
    // Récupérer les activités de l'organisateur
    $requete_mes_activites = $connexion->prepare("
        SELECT a.*, c.nom as categorie_nom, c.icone as categorie_icone,
               COUNT(p.id) as participants_actuels
        FROM activites a
        LEFT JOIN categories c ON a.categorie_id = c.id
        LEFT JOIN participations p ON a.id = p.activite_id
        WHERE a.organisateur_id = ?
        GROUP BY a.id
        ORDER BY a.date_activite DESC
    ");
    $requete_mes_activites->execute([$utilisateur['id']]);
    $mes_activites = $requete_mes_activites->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les prochaines activités
    $requete_prochaines_activites = $connexion->prepare("
        SELECT a.*, c.nom as categorie_nom,
               COUNT(p.id) as participants_actuels
        FROM activites a
        LEFT JOIN categories c ON a.categorie_id = c.id
        LEFT JOIN participations p ON a.id = p.activite_id
        WHERE a.organisateur_id = ? AND a.date_activite > NOW() AND a.statut = 'active'
        GROUP BY a.id
        ORDER BY a.date_activite ASC
        LIMIT 5
    ");
    $requete_prochaines_activites->execute([$utilisateur['id']]);
    $prochaines_activites = $requete_prochaines_activites->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $erreur = "Erreur lors de la récupération des données : " . $e->getMessage();
    $activites_crees = 0;
    $participants_totaux = 0;
    $activites_avenir = 0;
    $revenus_totaux = 0;
    $mes_activites = [];
    $prochaines_activites = [];
}

// Traitement de la suppression d'activité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_activite'])) {
    $activite_id = $_POST['activite_id'];
    
    try {
        // Vérifier que l'activité appartient bien à l'organisateur
        $requete_verif = $connexion->prepare("SELECT id FROM activites WHERE id = ? AND organisateur_id = ?");
        $requete_verif->execute([$activite_id, $utilisateur['id']]);
        
        if ($requete_verif->fetch()) {
            // Supprimer d'abord les participations et favoris liés
            $connexion->prepare("DELETE FROM participations WHERE activite_id = ?")->execute([$activite_id]);
            $connexion->prepare("DELETE FROM favoris WHERE activite_id = ?")->execute([$activite_id]);
            $connexion->prepare("DELETE FROM commentaires WHERE activite_id = ?")->execute([$activite_id]);
            
            // Puis supprimer l'activité
            $requete_suppression = $connexion->prepare("DELETE FROM activites WHERE id = ?");
            $requete_suppression->execute([$activite_id]);
            
            $succes = "Activité supprimée avec succès";
            
            // Recharger la page pour actualiser les données
            header('Location: page_organisateur_dashboard.php');
            exit;
        } else {
            $erreur = "Vous n'êtes pas autorisé à supprimer cette activité";
        }
        
    } catch (PDOException $e) {
        $erreur = "Erreur lors de la suppression : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Organisateur - FOSHA Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Tableau de bord Organisateur</h1>
            <p class="lead">Bienvenue, <?php echo $utilisateur['prenom']; ?> ! Gérez vos activités et votre communauté.</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <!-- Messages -->
            <?php if (isset($succes)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $succes; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($erreur)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $erreur; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistiques organisateur -->
            <div class="row mb-5">
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon activities">
                            <i class="fas fa-hiking"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $activites_crees; ?></h3>
                        <p class="stats-label">Activités créées</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $participants_totaux; ?></h3>
                        <p class="stats-label">Participants totaux</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon registrations">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $activites_avenir; ?></h3>
                        <p class="stats-label">Activités à venir</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon organizers">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <h3 class="stats-number"><?php echo number_format($revenus_totaux, 0, ',', ' '); ?> MAD</h3>
                        <p class="stats-label">Revenus totaux</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Mes activités -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Mes activités</h5>
                            <a href="create_activity.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-2"></i>Nouvelle activité
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($mes_activites)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-hiking fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Aucune activité créée</h5>
                                    <p class="text-muted mb-3">Commencez par créer votre première activité</p>
                                    <a href="create_activity.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Créer une activité
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Titre</th>
                                                <th>Date</th>
                                                <th>Participants</th>
                                                <th>Prix</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mes_activites as $activite): 
                                                $date_activite = new DateTime($activite['date_activite']);
                                                $maintenant = new DateTime();
                                                $statut = ($date_activite < $maintenant) ? 'Terminée' : 'À venir';
                                                $statut_class = ($statut == 'À venir') ? 'bg-success' : 'bg-secondary';
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($activite['titre']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($activite['categorie_nom']); ?></small>
                                                    </td>
                                                    <td>
                                                        <small><?php echo $date_activite->format('d/m/Y'); ?></small>
                                                        <br>
                                                        <small class="text-muted"><?php echo $date_activite->format('H:i'); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo $activite['participants_actuels']; ?>/<?php echo $activite['participants_max']; ?>
                                                    </td>
                                                    <td>
                                                        <strong class="<?php echo ($activite['prix'] > 0) ? 'text-success' : 'text-muted'; ?>">
                                                            <?php echo ($activite['prix'] > 0) ? $activite['prix'] . ' MAD' : 'Gratuit'; ?>
                                                        </strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $statut_class; ?>">
                                                            <?php echo $statut; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="activity_details.php?id=<?php echo $activite['id']; ?>" 
                                                               class="btn btn-outline-primary" title="Voir">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="create_activity.php?edit=<?php echo $activite['id']; ?>" 
                                                               class="btn btn-outline-warning" title="Modifier">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette activité ? Cette action est irréversible.');">
                                                                <input type="hidden" name="activite_id" value="<?php echo $activite['id']; ?>">
                                                                <button type="submit" name="supprimer_activite" class="btn btn-outline-danger" title="Supprimer">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Actions rapides -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Actions rapides</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="create_activity.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Créer une activité
                                </a>
                                <a href="activite.php" class="btn btn-outline-primary">
                                    <i class="fas fa-search me-2"></i>Explorer les activités
                                </a>
                                <a href="page_utilisateur.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-user me-2"></i>Mon compte
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Prochaines activités -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Prochaines activités</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($prochaines_activites)): ?>
                                <p class="text-muted text-center">Aucune activité à venir</p>
                                <div class="text-center">
                                    <a href="create_activity.php" class="btn btn-sm btn-outline-primary">
                                        Créer une activité
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($prochaines_activites as $activite): 
                                    $date_activite = new DateTime($activite['date_activite']);
                                ?>
                                    <div class="mb-3 pb-3 border-bottom">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($activite['titre']); ?></h6>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo $date_activite->format('d/m/Y'); ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $activite['participants_actuels']; ?> participants
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-tag me-1"></i>
                                            <?php echo ($activite['prix'] > 0) ? $activite['prix'] . ' MAD' : 'Gratuit'; ?>
                                        </small>
                                        <div class="mt-2">
                                            <a href="activity_details.php?id=<?php echo $activite['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Voir détails
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center mt-3">
                                    <a href="activite.php?organisateur=<?php echo $utilisateur['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        Voir toutes mes activités
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Statistiques rapides -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Statistiques</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Taux de remplissage moyen
                                    <span class="badge bg-info">
                                        <?php 
                                        if (!empty($mes_activites)) {
                                            $total_remplissage = 0;
                                            foreach ($mes_activites as $activite) {
                                                if ($activite['participants_max'] > 0) {
                                                    $total_remplissage += ($activite['participants_actuels'] / $activite['participants_max']) * 100;
                                                }
                                            }
                                            echo round($total_remplissage / count($mes_activites), 1) . '%';
                                        } else {
                                            echo '0%';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Activités gratuites
                                    <span class="badge bg-primary">
                                        <?php 
                                        $gratuites = array_filter($mes_activites, function($a) { return $a['prix'] == 0; });
                                        echo count($gratuites);
                                        ?>
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Revenus mensuels
                                    <span class="badge bg-success">
                                        <?php echo number_format($revenus_totaux / 12, 0, ',', ' '); ?> MAD
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>