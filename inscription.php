<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
include 'bd.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $prenom = $_POST['first_name'];
    $nom = $_POST['last_name'];
    $email = $_POST['email'];
    $telephone = $_POST['phone'];
    $ville = $_POST['ville'];
    $mot_de_passe = $_POST['password'];
    $est_organisateur = isset($_POST['is_organizer']) ? 1 : 0;
    
    try {
        // Vérifier si l'email existe déjà
        $requete_verif = $connexion->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $requete_verif->execute([$email]);
        
        if ($requete_verif->fetch()) {
            $erreur = "Cet email est déjà utilisé";
        } else {
            // En production, utilisez password_hash()
            // $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            
            // Insérer le nouvel utilisateur
            $requete_inscription = $connexion->prepare("
                INSERT INTO utilisateurs (prenom, nom, email, telephone, ville, mot_de_passe, est_organisateur) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $requete_inscription->execute([$prenom, $nom, $email, $telephone, $ville, $mot_de_passe_hash, $est_organisateur]);
            
            // Récupérer l'ID du nouvel utilisateur
            $utilisateur_id = $connexion->lastInsertId();
            
            // Créer la session
            $_SESSION['utilisateur'] = [
                'id' => $utilisateur_id,
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'ville' => $ville,
                'est_organisateur' => $est_organisateur,
                'est_admin' => false
            ];
            
            // Rediriger vers la page appropriée
            if ($est_organisateur) {
                header('Location: page_organisateur_dashboard.php');
            } else {
                header('Location: page_utilisateur.php');
            }
            exit;
        }
        
    } catch (PDOException $e) {
        $erreur = "Erreur lors de l'inscription : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - FOSHA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="register-container py-5" style="background: var(--light);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="auth-card p-4 p-md-5">
                        <div class="text-center mb-5">
                            <h2 class="fw-bold mb-3">Rejoignez FOSHA Maroc</h2>
                            <p class="text-muted">Créez votre compte et commencez à vivre des expériences uniques au Maroc</p>
                        </div>
 
                        <!-- Afficher les erreurs -->
                        <?php if (isset($erreur)): ?>
                            <div class="alert alert-danger"><?php echo $erreur; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="+212 6 XX XX XX XX">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="ville" class="form-label">Ville *</label>
                                    <select class="form-select" id="ville" name="ville" required>
                                        <option value="">Choisissez votre ville</option>
                                        <option value="Casablanca">Casablanca</option>
                                        <option value="Marrakech">Marrakech</option>
                                        <option value="Rabat">Rabat</option>
                                        <option value="Fès">Fès</option>
                                        <option value="Tanger">Tanger</option>
                                        <option value="Agadir">Agadir</option>
                                        <option value="Meknès">Meknès</option>
                                        <option value="Oujda">Oujda</option>
                                        <option value="Tétouan">Tétouan</option>
                                        <option value="Tamsna">Tamsna</option>
                                        <option value="Kénitra">Kénitra</option>
                                        <option value="Taza">Taza</option>
                                        <option value="Essaouira">Essaouira</option>
                                        <option value="Autre">Autre</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_organizer" name="is_organizer">
                                    <label class="form-check-label" for="is_organizer">
                                        Je souhaite devenir organisateur d'activités
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold mb-4">
                                Créer mon compte
                            </button>

                            <div class="text-center">
                                <p class="text-muted">Vous avez déjà un compte ? 
                                    <a href="connexion.php" class="text-primary fw-semibold text-decoration-none">Se connecter</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>