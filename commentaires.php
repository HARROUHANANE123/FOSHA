<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['utilisateur'])) {
    header('Location: connexion.php');
    exit;
}

// Inclure la connexion à la base de données
include 'bd.php';

$utilisateur = $_SESSION['utilisateur'];

// Récupérer les commentaires de l'utilisateur
try {
    $requete_commentaires = $connexion->prepare("
        SELECT c.*, a.titre as activite_titre, a.date_activite, a.ville,
               u.prenom, u.nom, u.email,
               c.statut as commentaire_statut
        FROM commentaires c
        JOIN activites a ON c.activite_id = a.id
        JOIN utilisateurs u ON c.utilisateur_id = u.id
        WHERE c.utilisateur_id = ?
        ORDER BY c.date_creation DESC
    ");
    $requete_commentaires->execute([$utilisateur['id']]);
    $commentaires = $requete_commentaires->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les statistiques
    $total_commentaires = count($commentaires);
    $commentaires_approuves = array_filter($commentaires, function($comment) {
        return $comment['commentaire_statut'] === 'approuve';
    });
    $commentaires_en_attente = array_filter($commentaires, function($comment) {
        return $comment['commentaire_statut'] === 'en_attente';
    });
    $commentaires_rejetes = array_filter($commentaires, function($comment) {
        return $comment['commentaire_statut'] === 'rejete';
    });
    
    // Calculer la note moyenne de l'utilisateur
    $note_moyenne = 0;
    if (!empty($commentaires_approuves)) {
        $total_notes = 0;
        foreach ($commentaires_approuves as $comment) {
            $total_notes += $comment['note'];
        }
        $note_moyenne = round($total_notes / count($commentaires_approuves), 1);
    }
    
} catch (PDOException $e) {
    $erreur = "Erreur lors de la récupération des commentaires : " . $e->getMessage();
    $commentaires = [];
    $total_commentaires = 0;
    $commentaires_approuves = [];
    $commentaires_en_attente = [];
    $commentaires_rejetes = [];
    $note_moyenne = 0;
}

// Traitement de l'ajout de commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajouter_commentaire'])) {
        $activite_id = $_POST['activite_id'];
        $commentaire = trim($_POST['commentaire']);
        $note = $_POST['note'];
        
        // Validation
        $erreurs = [];
        
        if (empty($commentaire)) {
            $erreurs[] = "Le commentaire est obligatoire";
        }
        
        if (strlen($commentaire) < 10) {
            $erreurs[] = "Le commentaire doit contenir au moins 10 caractères";
        }
        
        if (empty($note) || $note < 1 || $note > 5) {
            $erreurs[] = "La note doit être entre 1 et 5 étoiles";
        }
        
        // Vérifier que l'utilisateur a participé à l'activité
        try {
            $requete_participation = $connexion->prepare("
                SELECT id FROM participations 
                WHERE utilisateur_id = ? AND activite_id = ?
            ");
            $requete_participation->execute([$utilisateur['id'], $activite_id]);
            $a_participe = $requete_participation->fetch();
            
            if (!$a_participe) {
                $erreurs[] = "Vous devez avoir participé à cette activité pour la commenter";
            }
            
            // Vérifier si l'utilisateur a déjà commenté cette activité
            $requete_existe = $connexion->prepare("
                SELECT id FROM commentaires 
                WHERE utilisateur_id = ? AND activite_id = ?
            ");
            $requete_existe->execute([$utilisateur['id'], $activite_id]);
            $commentaire_existe = $requete_existe->fetch();
            
            if ($commentaire_existe) {
                $erreurs[] = "Vous avez déjà commenté cette activité";
            }
            
        } catch (PDOException $e) {
            $erreurs[] = "Erreur de vérification : " . $e->getMessage();
        }
        
        if (empty($erreurs)) {
            try {
                $requete_ajout = $connexion->prepare("
                    INSERT INTO commentaires (utilisateur_id, activite_id, commentaire, note, statut)
                    VALUES (?, ?, ?, ?, 'en_attente')
                ");
                $requete_ajout->execute([
                    $utilisateur['id'],
                    $activite_id,
                    $commentaire,
                    $note
                ]);
                
                $succes = "Votre commentaire a été soumis avec succès ! Il sera visible après modération.";
                
                // Recharger la page
                header('Location: commentaires.php');
                exit;
                
            } catch (PDOException $e) {
                $erreur = "Erreur lors de l'ajout du commentaire : " . $e->getMessage();
            }
        } else {
            $erreur = implode("<br>", $erreurs);
        }
    }
    elseif (isset($_POST['modifier_commentaire'])) {
        $commentaire_id = $_POST['commentaire_id'];
        $nouveau_commentaire = trim($_POST['commentaire']);
        $nouvelle_note = $_POST['note'];
        
        // Validation
        $erreurs = [];
        
        if (empty($nouveau_commentaire)) {
            $erreurs[] = "Le commentaire est obligatoire";
        }
        
        if (strlen($nouveau_commentaire) < 10) {
            $erreurs[] = "Le commentaire doit contenir au moins 10 caractères";
        }
        
        if (empty($nouvelle_note) || $nouvelle_note < 1 || $nouvelle_note > 5) {
            $erreurs[] = "La note doit être entre 1 et 5 étoiles";
        }
        
        if (empty($erreurs)) {
            try {
                // Vérifier que le commentaire appartient à l'utilisateur
                $requete_verif = $connexion->prepare("
                    SELECT id FROM commentaires 
                    WHERE id = ? AND utilisateur_id = ?
                ");
                $requete_verif->execute([$commentaire_id, $utilisateur['id']]);
                $commentaire_appartient = $requete_verif->fetch();
                
                if (!$commentaire_appartient) {
                    $erreur = "Vous n'êtes pas autorisé à modifier ce commentaire";
                } else {
                    $requete_modification = $connexion->prepare("
                        UPDATE commentaires 
                        SET commentaire = ?, note = ?, statut = 'en_attente', date_creation = NOW()
                        WHERE id = ?
                    ");
                    $requete_modification->execute([
                        $nouveau_commentaire,
                        $nouvelle_note,
                        $commentaire_id
                    ]);
                    
                    $succes = "Commentaire modifié avec succès ! Il sera à nouveau soumis à modération.";
                    
                    // Recharger la page
                    header('Location: commentaires.php');
                    exit;
                }
                
            } catch (PDOException $e) {
                $erreur = "Erreur lors de la modification : " . $e->getMessage();
            }
        } else {
            $erreur = implode("<br>", $erreurs);
        }
    }
    elseif (isset($_POST['supprimer_commentaire'])) {
        $commentaire_id = $_POST['commentaire_id'];
        
        try {
            // Vérifier que le commentaire appartient à l'utilisateur
            $requete_verif = $connexion->prepare("
                SELECT id FROM commentaires 
                WHERE id = ? AND utilisateur_id = ?
            ");
            $requete_verif->execute([$commentaire_id, $utilisateur['id']]);
            $commentaire_appartient = $requete_verif->fetch();
            
            if (!$commentaire_appartient) {
                $erreur = "Vous n'êtes pas autorisé à supprimer ce commentaire";
            } else {
                $requete_suppression = $connexion->prepare("
                    DELETE FROM commentaires WHERE id = ?
                ");
                $requete_suppression->execute([$commentaire_id]);
                
                $succes = "Commentaire supprimé avec succès";
                
                // Recharger la page
                header('Location: commentaires.php');
                exit;
            }
            
        } catch (PDOException $e) {
            $erreur = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Récupérer les activités pouvant être commentées
try {
    $requete_activites_a_commenter = $connexion->prepare("
        SELECT DISTINCT a.id, a.titre, a.date_activite, a.ville, a.image_url,
               c.nom as categorie_nom, c.icone as categorie_icone
        FROM participations p
        JOIN activites a ON p.activite_id = a.id
        LEFT JOIN categories c ON a.categorie_id = c.id
        LEFT JOIN commentaires com ON (a.id = com.activite_id AND com.utilisateur_id = ?)
        WHERE p.utilisateur_id = ? 
        AND a.date_activite < NOW()
        AND com.id IS NULL
        ORDER BY a.date_activite DESC
        LIMIT 10
    ");
    $requete_activites_a_commenter->execute([$utilisateur['id'], $utilisateur['id']]);
    $activites_a_commenter = $requete_activites_a_commenter->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activites_a_commenter = [];
}

// Récupérer le filtre actuel
$filtre_actuel = $_GET['filtre'] ?? 'tous';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commentaires - FOSHA Maroc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Mes Commentaires</h1>
            <p class="lead">Gérez vos avis et retours d'expérience</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <!-- Messages -->
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

            <!-- Statistiques -->
            <div class="row mb-5">
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon users">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $total_commentaires; ?></h3>
                        <p class="stats-label">Commentaires totaux</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon activities">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="stats-number"><?php echo count($commentaires_approuves); ?></h3>
                        <p class="stats-label">Commentaires approuvés</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon organizers">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3 class="stats-number"><?php echo $note_moyenne; ?>/5</h3>
                        <p class="stats-label">Note moyenne</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stats-card text-center">
                        <div class="stats-icon registrations">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="stats-number"><?php echo count($commentaires_en_attente); ?></h3>
                        <p class="stats-label">En attente</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Liste des commentaires -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list me-2"></i>Historique de mes commentaires
                            </h5>
                            <div>
                                <span class="badge bg-primary"><?php echo $total_commentaires; ?> total</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($commentaires)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                                    <h3 class="text-muted">Aucun commentaire</h3>
                                    <p class="text-muted mb-4">Vous n'avez pas encore commenté d'activités.</p>
                                    <a href="mes_participations.php" class="btn btn-primary">
                                        <i class="fas fa-calendar-check me-2"></i>Voir mes participations
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- Filtres -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="?filtre=tous" class="btn btn-outline-primary <?php echo $filtre_actuel === 'tous' ? 'filtre-actif' : ''; ?>">
                                                Tous (<?php echo $total_commentaires; ?>)
                                            </a>
                                            <a href="?filtre=approuve" class="btn btn-outline-success <?php echo $filtre_actuel === 'approuve' ? 'filtre-actif' : ''; ?>">
                                                Approuvés (<?php echo count($commentaires_approuves); ?>)
                                            </a>
                                            <a href="?filtre=en_attente" class="btn btn-outline-warning <?php echo $filtre_actuel === 'en_attente' ? 'filtre-actif' : ''; ?>">
                                                En attente (<?php echo count($commentaires_en_attente); ?>)
                                            </a>
                                            <a href="?filtre=rejete" class="btn btn-outline-danger <?php echo $filtre_actuel === 'rejete' ? 'filtre-actif' : ''; ?>">
                                                Rejetés (<?php echo count($commentaires_rejetes); ?>)
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Liste des commentaires -->
                                <div id="commentairesListe">
                                    <?php 
                                    // Filtrer les commentaires selon le filtre actuel
                                    $commentaires_filtres = $commentaires;
                                    if ($filtre_actuel !== 'tous') {
                                        $commentaires_filtres = array_filter($commentaires, function($comment) use ($filtre_actuel) {
                                            return $comment['commentaire_statut'] === $filtre_actuel;
                                        });
                                    }
                                    
                                    if (empty($commentaires_filtres)): 
                                    ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">Aucun commentaire trouvé</h5>
                                            <p class="text-muted">Aucun commentaire ne correspond à ce filtre.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($commentaires_filtres as $commentaire): 
                                            $date_commentaire = new DateTime($commentaire['date_creation']);
                                            $date_activite = new DateTime($commentaire['date_activite']);
                                        ?>
                                            <div class="comment-card <?php echo $commentaire['commentaire_statut']; ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="flex-grow-1">
                                                        <h5 class="mb-1">
                                                            <i class="fas fa-hiking me-2 text-primary"></i>
                                                            <?php echo htmlspecialchars($commentaire['activite_titre']); ?>
                                                        </h5>
                                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Participé le <?php echo $date_activite->format('d/m/Y'); ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                                <?php echo htmlspecialchars($commentaire['ville']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge 
                                                            <?php 
                                                            switch($commentaire['commentaire_statut']) {
                                                                case 'approuve': echo 'bg-success'; break;
                                                                case 'en_attente': echo 'bg-warning'; break;
                                                                case 'rejete': echo 'bg-danger'; break;
                                                                default: echo 'bg-secondary';
                                                            }
                                                            ?>">
                                                            <?php 
                                                            switch($commentaire['commentaire_statut']) {
                                                                case 'approuve': echo '✓ Approuvé'; break;
                                                                case 'en_attente': echo '⏳ En attente'; break;
                                                                case 'rejete': echo '✗ Rejeté'; break;
                                                                default: echo $commentaire['commentaire_statut'];
                                                            }
                                                            ?>
                                                        </span>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo $date_commentaire->format('d/m/Y à H:i'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <!-- Note -->
                                                <div class="mb-3">
                                                    <div class="stars-display">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $commentaire['note'] ? '' : 'text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="ms-2 fw-bold text-primary"><?php echo $commentaire['note']; ?>/5</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Commentaire -->
                                                <div class="mb-3">
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($commentaire['commentaire'])); ?></p>
                                                </div>
                                                
                                                <!-- Actions -->
                                                <div class="comment-actions d-flex gap-2 justify-content-end">
                                                    <?php if ($commentaire['commentaire_statut'] !== 'approuve'): ?>
                                                        <button class="btn btn-sm btn-outline-warning" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#modifierCommentaireModal<?php echo $commentaire['id']; ?>">
                                                            <i class="fas fa-edit me-1"></i>Modifier
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="commentaire_id" value="<?php echo $commentaire['id']; ?>">
                                                        <button type="submit" name="supprimer_commentaire" 
                                                                class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')">
                                                            <i class="fas fa-trash me-1"></i>Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <!-- Modal de modification pour chaque commentaire -->
                                            <div class="modal fade" id="modifierCommentaireModal<?php echo $commentaire['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-warning text-dark">
                                                            <h5 class="modal-title">
                                                                <i class="fas fa-edit me-2"></i>Modifier le commentaire
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <input type="hidden" name="commentaire_id" value="<?php echo $commentaire['id']; ?>">
                                                            
                                                            <div class="modal-body">
                                                                <div class="alert alert-warning">
                                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                                    Après modification, votre commentaire sera à nouveau soumis à modération.
                                                                </div>
                                                                
                                                                <div class="mb-4">
                                                                    <label class="form-label fw-bold">Nouvelle note *</label>
                                                                    <div class="rating-container mb-2">
                                                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                                                            <input type="radio" id="editStar<?php echo $commentaire['id']; ?>_<?php echo $i; ?>" 
                                                                                   name="note" value="<?php echo $i; ?>" class="rating-input" 
                                                                                   <?php echo $i == $commentaire['note'] ? 'checked' : ''; ?> required>
                                                                            <label for="editStar<?php echo $commentaire['id']; ?>_<?php echo $i; ?>" class="rating-label">
                                                                                <i class="fas fa-star"></i>
                                                                            </label>
                                                                        <?php endfor; ?>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label for="editCommentaire<?php echo $commentaire['id']; ?>" class="form-label fw-bold">Nouveau commentaire *</label>
                                                                    <textarea class="form-control" id="editCommentaire<?php echo $commentaire['id']; ?>" 
                                                                              name="commentaire" rows="6" minlength="10" required><?php echo htmlspecialchars($commentaire['commentaire']); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                    <i class="fas fa-times me-1"></i>Annuler
                                                                </button>
                                                                <button type="submit" name="modifier_commentaire" class="btn btn-warning">
                                                                    <i class="fas fa-save me-1"></i>Modifier le commentaire
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Activités pouvant être commentées -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-edit me-2"></i>Activités à commenter
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($activites_a_commenter)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-check-circle fa-2x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">Aucune activité à commenter</p>
                                    <small class="text-muted">Toutes vos activités ont été commentées</small>
                                </div>
                            <?php else: ?>
                                <div class="activites-list">
                                    <?php foreach ($activites_a_commenter as $activite): 
                                        $date_activite = new DateTime($activite['date_activite']);
                                    ?>
                                        <div class="activity-mini-card">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($activite['titre']); ?></h6>
                                                <span class="badge bg-light text-dark small">
                                                    <?php echo $activite['categorie_icone']; ?>
                                                </span>
                                            </div>
                                            <div class="mb-2">
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo $date_activite->format('d/m/Y'); ?>
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($activite['ville']); ?>
                                                </small>
                                            </div>
                                            <button class="btn btn-sm btn-success w-100"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#ajouterCommentaireModal<?php echo $activite['id']; ?>">
                                                <i class="fas fa-plus me-1"></i>Donner mon avis
                                            </button>
                                        </div>

                                        <!-- Modal d'ajout pour chaque activité -->
                                        <div class="modal fade" id="ajouterCommentaireModal<?php echo $activite['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-plus me-2"></i>Ajouter un commentaire
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <input type="hidden" name="activite_id" value="<?php echo $activite['id']; ?>">
                                                        
                                                        <div class="modal-body">
                                                            <div class="mb-4">
                                                                <label class="form-label fw-bold">Activité</label>
                                                                <div class="alert alert-info">
                                                                    <i class="fas fa-info-circle me-2"></i>
                                                                    Vous commentez : <strong><?php echo htmlspecialchars($activite['titre']); ?></strong>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-4">
                                                                <label class="form-label fw-bold">Note *</label>
                                                                <div class="rating-container mb-2">
                                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                                        <input type="radio" id="star<?php echo $activite['id']; ?>_<?php echo $i; ?>" 
                                                                               name="note" value="<?php echo $i; ?>" class="rating-input" required>
                                                                        <label for="star<?php echo $activite['id']; ?>_<?php echo $i; ?>" class="rating-label">
                                                                            <i class="fas fa-star"></i>
                                                                        </label>
                                                                    <?php endfor; ?>
                                                                </div>
                                                                <div class="text-center">
                                                                    <small class="text-muted">Cliquez sur les étoiles pour attribuer une note de 1 à 5</small>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label for="commentaire<?php echo $activite['id']; ?>" class="form-label fw-bold">Commentaire *</label>
                                                                <textarea class="form-control" id="commentaire<?php echo $activite['id']; ?>" name="commentaire" rows="6" 
                                                                          placeholder="Partagez votre expérience en détail... 
        - Qu'avez-vous particulièrement apprécié ?
        - Y a-t-il des points à améliorer ?
        - Recommanderiez-vous cette activité ?" 
                                                                          minlength="10" required></textarea>
                                                                <div class="form-text">
                                                                    <i class="fas fa-info-circle me-1"></i>
                                                                    Votre commentaire doit contenir au moins 10 caractères et sera soumis à modération avant publication.
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="fas fa-times me-1"></i>Annuler
                                                            </button>
                                                            <button type="submit" name="ajouter_commentaire" class="btn btn-success">
                                                                <i class="fas fa-paper-plane me-1"></i>Soumettre mon avis
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="mes_participations.php" class="btn btn-outline-success btn-sm">
                                        Voir toutes mes participations
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Guide de notation -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-star me-2"></i>Guide de notation
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="small">
                                <div class="d-flex align-items-center mb-2 p-2 rounded bg-light">
                                    <div class="stars-display me-3">
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                    </div>
                                    <div>
                                        <strong>Exceptionnel</strong>
                                        <div class="text-muted">Expérience parfaite</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-2 p-2 rounded">
                                    <div class="stars-display me-3">
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star text-muted"></i>
                                    </div>
                                    <div>
                                        <strong>Très bien</strong>
                                        <div class="text-muted">Très satisfait</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-2 p-2 rounded">
                                    <div class="stars-display me-3">
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star text-muted"></i><i class="far fa-star text-muted"></i>
                                    </div>
                                    <div>
                                        <strong>Bien</strong>
                                        <div class="text-muted">Satisfait</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mb-2 p-2 rounded">
                                    <div class="stars-display me-3">
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star text-muted"></i><i class="far fa-star text-muted"></i><i class="far fa-star text-muted"></i>
                                    </div>
                                    <div>
                                        <strong>Moyen</strong>
                                        <div class="text-muted">Peut mieux faire</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center p-2 rounded">
                                    <div class="stars-display me-3">
                                        <i class="fas fa-star"></i><i class="far fa-star text-muted"></i><i class="far fa-star text-muted"></i><i class="far fa-star text-muted"></i><i class="far fa-star text-muted"></i>
                                    </div>
                                    <div>
                                        <strong>Décevant</strong>
                                        <div class="text-muted">Ne correspond pas</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques rapides -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Mes statistiques
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Note moyenne</span>
                                    <span class="badge bg-primary rounded-pill"><?php echo $note_moyenne; ?>/5</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Total commentaires</span>
                                    <span class="badge bg-success rounded-pill"><?php echo $total_commentaires; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Taux d'approbation</span>
                                    <span class="badge bg-info rounded-pill">
                                        <?php echo $total_commentaires > 0 ? round((count($commentaires_approuves) / $total_commentaires) * 100) : 0; ?>%
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>En attente</span>
                                    <span class="badge bg-warning rounded-pill"><?php echo count($commentaires_en_attente); ?></span>
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