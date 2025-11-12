<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['utilisateur']) || !$_SESSION['utilisateur']['est_admin']) {
    header('Location: connexion.php');
    exit;
}

// Inclure la connexion à la base de données
include 'bd.php';

// Récupérer les statistiques générales
try {
    // Compter les utilisateurs
    $requete_utilisateurs = $connexion->query("SELECT COUNT(*) as total FROM utilisateurs");
    $total_utilisateurs = $requete_utilisateurs->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les activités
    $requete_activites = $connexion->query("SELECT COUNT(*) as total FROM activites");
    $total_activites = $requete_activites->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les organisateurs
    $requete_organisateurs = $connexion->query("SELECT COUNT(*) as total FROM utilisateurs WHERE est_organisateur = 1");
    $total_organisateurs = $requete_organisateurs->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Compter les participations totales
    $requete_participations = $connexion->query("SELECT COUNT(*) as total FROM participations");
    $total_participations = $requete_participations->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupérer les utilisateurs récents (limité à 5)
    $requete_utilisateurs_recents = $connexion->query("
        SELECT id, prenom, nom, email, est_organisateur, est_admin, date_inscription 
        FROM utilisateurs 
        ORDER BY date_inscription DESC 
        LIMIT 5
    ");
    $utilisateurs_recents = $requete_utilisateurs_recents->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les activités récentes (limité à 5)
    $requete_activites_recentes = $connexion->prepare("
        SELECT a.*, u.prenom, u.nom, c.nom as categorie_nom,
               COUNT(p.id) as participants_count
        FROM activites a
        LEFT JOIN utilisateurs u ON a.organisateur_id = u.id
        LEFT JOIN categories c ON a.categorie_id = c.id
        LEFT JOIN participations p ON a.id = p.activite_id
        GROUP BY a.id
        ORDER BY a.date_creation DESC 
        LIMIT 5
    ");
    $requete_activites_recentes->execute();
    $activites_recentes = $requete_activites_recentes->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer toutes les catégories
    $requete_categories = $connexion->query("SELECT * FROM categories ORDER BY nom");
    $categories = $requete_categories->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques mensuelles
    $requete_activites_mensuelles = $connexion->query("
        SELECT 
            MONTH(date_creation) as mois,
            COUNT(*) as total_activites
        FROM activites 
        WHERE YEAR(date_creation) = YEAR(CURDATE())
        GROUP BY MONTH(date_creation)
        ORDER BY mois
    ");
    $activites_mensuelles = $requete_activites_mensuelles->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $erreur = "Erreur lors de la récupération des données : " . $e->getMessage();
    $total_utilisateurs = 0;
    $total_activites = 0;
    $total_organisateurs = 0;
    $total_participations = 0;
    $utilisateurs_recents = [];
    $activites_recentes = [];
    $categories = [];
    $activites_mensuelles = [];
}

// Traitement de l'ajout de catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_categorie'])) {
    $nom_categorie = trim($_POST['nom_categorie']);
    $icone_categorie = trim($_POST['icone_categorie']);
    $couleur_categorie = $_POST['couleur_categorie'];
    
    if (!empty($nom_categorie) && !empty($icone_categorie)) {
        try {
            $requete_ajout_categorie = $connexion->prepare("
                INSERT INTO categories (nom, icone, couleur) 
                VALUES (?, ?, ?)
            ");
            $requete_ajout_categorie->execute([$nom_categorie, $icone_categorie, $couleur_categorie]);
            
            $succes = "Catégorie ajoutée avec succès !";
            
            // Recharger les catégories
            $requete_categories = $connexion->query("SELECT * FROM categories ORDER BY nom");
            $categories = $requete_categories->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $erreur = "Erreur lors de l'ajout de la catégorie : " . $e->getMessage();
        }
    } else {
        $erreur = "Veuillez remplir tous les champs obligatoires";
    }
}

// Traitement de la suppression de catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_categorie'])) {
    $categorie_id = $_POST['categorie_id'];
    
    try {
        // Vérifier si la catégorie est utilisée dans des activités
        $requete_verif = $connexion->prepare("SELECT COUNT(*) as total FROM activites WHERE categorie_id = ?");
        $requete_verif->execute([$categorie_id]);
        $utilisation = $requete_verif->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($utilisation > 0) {
            $erreur = "Impossible de supprimer cette catégorie : elle est utilisée dans " . $utilisation . " activité(s)";
        } else {
            $requete_suppression = $connexion->prepare("DELETE FROM categories WHERE id = ?");
            $requete_suppression->execute([$categorie_id]);
            
            $succes = "Catégorie supprimée avec succès !";
            
            // Recharger les catégories
            $requete_categories = $connexion->query("SELECT * FROM categories ORDER BY nom");
            $categories = $requete_categories->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $erreur = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Traitement de la suppression d'utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_utilisateur'])) {
    $utilisateur_id = $_POST['utilisateur_id'];
    
    try {
        // Empêcher la suppression de l'admin principal
        if ($utilisateur_id == 1) {
            $erreur = "Impossible de supprimer le compte administrateur principal";
        } else {
            // Commencer une transaction pour assurer l'intégrité des données
            $connexion->beginTransaction();
            
            // Supprimer les participations de l'utilisateur
            $connexion->prepare("DELETE FROM participations WHERE utilisateur_id = ?")->execute([$utilisateur_id]);
            
            // Supprimer les favoris de l'utilisateur
            $connexion->prepare("DELETE FROM favoris WHERE utilisateur_id = ?")->execute([$utilisateur_id]);
            
            // Supprimer les commentaires de l'utilisateur
            $connexion->prepare("DELETE FROM commentaires WHERE utilisateur_id = ?")->execute([$utilisateur_id]);
            
            // Supprimer les activités organisées par l'utilisateur (et leurs participations)
            $requete_activites = $connexion->prepare("SELECT id FROM activites WHERE organisateur_id = ?");
            $requete_activites->execute([$utilisateur_id]);
            $activites_organisees = $requete_activites->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($activites_organisees)) {
                // Supprimer les participations aux activités organisées
                $placeholders = str_repeat('?,', count($activites_organisees) - 1) . '?';
                $connexion->prepare("DELETE FROM participations WHERE activite_id IN ($placeholders)")->execute($activites_organisees);
                
                // Supprimer les favoris des activités organisées
                $connexion->prepare("DELETE FROM favoris WHERE activite_id IN ($placeholders)")->execute($activites_organisees);
                
                // Supprimer les commentaires des activités organisées
                $connexion->prepare("DELETE FROM commentaires WHERE activite_id IN ($placeholders)")->execute($activites_organisees);
                
                // Supprimer les activités organisées
                $connexion->prepare("DELETE FROM activites WHERE organisateur_id = ?")->execute([$utilisateur_id]);
            }
            
            // Finalement supprimer l'utilisateur
            $connexion->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$utilisateur_id]);
            
            $connexion->commit();
            $succes = "Utilisateur supprimé avec succès !";
            
            // Recharger les données
            header('Location: page_admin.php');
            exit;
        }
        
    } catch (PDOException $e) {
        $connexion->rollBack();
        $erreur = "Erreur lors de la suppression de l'utilisateur : " . $e->getMessage();
    }
}

// Traitement de la suppression d'activité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_activite'])) {
    $activite_id = $_POST['activite_id'];
    
    try {
        $connexion->beginTransaction();
        
        // Supprimer les participations
        $connexion->prepare("DELETE FROM participations WHERE activite_id = ?")->execute([$activite_id]);
        
        // Supprimer les favoris
        $connexion->prepare("DELETE FROM favoris WHERE activite_id = ?")->execute([$activite_id]);
        
        // Supprimer les commentaires
        $connexion->prepare("DELETE FROM commentaires WHERE activite_id = ?")->execute([$activite_id]);
        
        // Supprimer l'activité
        $connexion->prepare("DELETE FROM activites WHERE id = ?")->execute([$activite_id]);
        
        $connexion->commit();
        $succes = "Activité supprimée avec succès !";
        
        // Recharger les données
        header('Location: page_admin.php');
        exit;
        
    } catch (PDOException $e) {
        $connexion->rollBack();
        $erreur = "Erreur lors de la suppression de l'activité : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - FOSHA Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        
        .admin-stats-card:hover {
            transform: translateY(-5px);
        }
        
        .admin-stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .admin-stats-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .category-color-preview {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .admin-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Administration <?php echo $_SESSION['utilisateur']['prenom'] . ' ' . $_SESSION['utilisateur']['nom']; ?></h1>
            <p class="lead">Gestion complète de la plateforme</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <!-- Messages de statut -->
            <?php if (isset($succes)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $succes; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($erreur)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $erreur; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistiques générales -->
            <div class="row mb-5">
                <div class="col-md-3 mb-4">
                    <div class="admin-stats-card">
                        <div class="admin-stats-number"><?php echo $total_utilisateurs; ?></div>
                        <div class="admin-stats-label">
                            <i class="fas fa-users me-2"></i>Utilisateurs
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="admin-stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="admin-stats-number"><?php echo $total_activites; ?></div>
                        <div class="admin-stats-label">
                            <i class="fas fa-hiking me-2"></i>Activités
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="admin-stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="admin-stats-number"><?php echo $total_organisateurs; ?></div>
                        <div class="admin-stats-label">
                            <i class="fas fa-user-tie me-2"></i>Organisateurs
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="admin-stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <div class="admin-stats-number"><?php echo $total_participations; ?></div>
                        <div class="admin-stats-label">
                            <i class="fas fa-calendar-check me-2"></i>Participations
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Utilisateurs récents -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users me-2"></i>Utilisateurs récents
                            </h5>
                            <span class="badge bg-light text-primary"><?php echo count($utilisateurs_recents); ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($utilisateurs_recents)): ?>
                                <p class="text-muted text-center py-3">Aucun utilisateur inscrit</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Email</th>
                                                <th>Statut</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($utilisateurs_recents as $utilisateur): 
                                                $date_inscription = new DateTime($utilisateur['date_inscription']);
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($utilisateur['email']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($utilisateur['est_admin']): ?>
                                                            <span class="badge bg-warning admin-badge">Admin</span>
                                                        <?php elseif ($utilisateur['est_organisateur']): ?>
                                                            <span class="badge bg-success admin-badge">Organisateur</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary admin-badge">Participant</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?php echo $date_inscription->format('d/m/Y'); ?></small>
                                                    </td>
                                                    <td class="table-actions">
                                                        <?php if ($utilisateur['id'] != 1): // Ne pas permettre de supprimer l'admin principal ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.');">
                                                                <input type="hidden" name="utilisateur_id" value="<?php echo $utilisateur['id']; ?>">
                                                                <button type="submit" name="supprimer_utilisateur" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Principal</span>
                                                        <?php endif; ?>
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

                <!-- Activités récentes -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-hiking me-2"></i>Activités récentes
                            </h5>
                            <span class="badge bg-light text-success"><?php echo count($activites_recentes); ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($activites_recentes)): ?>
                                <p class="text-muted text-center py-3">Aucune activité créée</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Titre</th>
                                                <th>Organisateur</th>
                                                <th>Date</th>
                                                <th>Participants</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activites_recentes as $activite): 
                                                $date_activite = new DateTime($activite['date_activite']);
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($activite['titre']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($activite['categorie_nom']); ?></small>
                                                    </td>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($activite['prenom'] . ' ' . $activite['nom']); ?></small>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?php echo $date_activite->format('d/m/Y'); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $activite['participants_count']; ?></span>
                                                    </td>
                                                    <td class="table-actions">
                                                        <a href="activity_details.php?id=<?php echo $activite['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Voir">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette activité ?');">
                                                            <input type="hidden" name="activite_id" value="<?php echo $activite['id']; ?>">
                                                            <button type="submit" name="supprimer_activite" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
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
            </div>

            <!-- Gestion des catégories -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-tags me-2"></i>Gestion des catégories
                            </h5>
                            <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fas fa-plus me-2"></i>Ajouter une catégorie
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categories)): ?>
                                <p class="text-muted text-center py-4">Aucune catégorie disponible.</p>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($categories as $categorie): ?>
                                        <div class="col-md-4 col-lg-3">
                                            <div class="category-admin-card">
                                                <div class="category-icon-large" style="font-size: 3rem; color: <?php echo $categorie['couleur']; ?>">
                                                    <?php echo $categorie['icone']; ?>
                                                </div>
                                                <h6 class="mt-2 text-center"><?php echo htmlspecialchars($categorie['nom']); ?></h6>
                                                <div class="text-center">
                                                    <span class="category-color-preview" style="background-color: <?php echo $categorie['couleur']; ?>"></span>
                                                    <small class="text-muted"><?php echo $categorie['couleur']; ?></small>
                                                </div>
                                                <div class="btn-group btn-group-sm w-100 mt-2">
                                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?php echo $categorie['id']; ?>">
                                                        Modifier
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="categorie_id" value="<?php echo $categorie['id']; ?>">
                                                        <button type="submit" name="supprimer_categorie" class="btn btn-outline-danger"
                                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')">
                                                            Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiques avancées -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Activités par mois (<?php echo date('Y'); ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($activites_mensuelles)): ?>
                                <p class="text-muted text-center py-3">Aucune donnée disponible</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php 
                                    $mois = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                                    foreach ($activites_mensuelles as $stat): 
                                    ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><?php echo $mois[$stat['mois']]; ?></span>
                                            <span class="badge bg-primary rounded-pill"><?php echo $stat['total_activites']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cog me-2"></i>Actions rapides
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="#" class="btn btn-outline-primary">
                                    <i class="fas fa-download me-2"></i>Exporter les données
                                </a>
                                <a href="#" class="btn btn-outline-success">
                                    <i class="fas fa-envelope me-2"></i>Envoyer une newsletter
                                </a>
                                <a href="#" class="btn btn-outline-warning">
                                    <i class="fas fa-bell me-2"></i>Notifications système
                                </a>
                                <a href="#" class="btn btn-outline-info">
                                    <i class="fas fa-chart-line me-2"></i>Rapports détaillés
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Ajout Catégorie -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom de la catégorie *</label>
                            <input type="text" class="form-control" name="nom_categorie" required 
                                   placeholder="Ex: Musique, Sport, Art...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icône (emoji) *</label>
                            <input type="text" class="form-control" name="icone_categorie" required 
                                   placeholder="🎵, ⚽, 🎨...">
                            <div class="form-text">Utilisez un emoji représentatif de la catégorie</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Couleur *</label>
                            <input type="color" class="form-control" name="couleur_categorie" value="#1a1a2e" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="ajouter_categorie" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>