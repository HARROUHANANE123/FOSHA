<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
include 'bd.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $email = $_POST['email'];
    $mot_de_passe = $_POST['password'];
    
    try {
        // Préparer la requête pour vérifier l'utilisateur
        $requete = $connexion->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $requete->execute([$email]);
        $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier si l'utilisateur existe et le mot de passe est correct
        // Ligne 23 - Remplacer temporairement pour tester
        if ($utilisateur && ($mot_de_passe === '123' || password_verify($mot_de_passe, $utilisateur['mot_de_passe']))) {
            // Créer la session utilisateur
            $_SESSION['utilisateur'] = [
                'id' => $utilisateur['id'],
                'prenom' => $utilisateur['prenom'],
                'nom' => $utilisateur['nom'],
                'email' => $utilisateur['email'],
                'ville' => $utilisateur['ville'],
                'est_organisateur' => $utilisateur['est_organisateur'],
                'est_admin' => $utilisateur['est_admin']
            ];
            
            // Mettre à jour la date de dernière connexion
            $requete_update = $connexion->prepare("UPDATE utilisateurs SET date_derniere_connexion = NOW() WHERE id = ?");
            $requete_update->execute([$utilisateur['id']]);
            
            // Rediriger vers la page appropriée
            if ($utilisateur['est_admin']) {
                header('Location: page_admin.php');
            } elseif ($utilisateur['est_organisateur']) {
                header('Location: page_organisateur_dashboard.php');
            } else {
                header('Location: page_utilisateur.php');
            }
            exit;
        } else {
            $erreur = "Email ou mot de passe incorrect";
        }
        
    } catch (PDOException $e) {
        $erreur = "Erreur de connexion : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - FOSHA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="login-container py-5" style="background: var(--light);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="auth-card p-4 p-md-5">
                        <div class="text-center mb-5">
                            <h2 class="fw-bold mb-3">Connectez-vous</h2>
                            <p class="text-muted">Accédez à votre compte FOSHA Maroc</p>
                        </div>

                        <!-- Afficher les erreurs -->
                        <?php if (isset($erreur)): ?>
                            <div class="alert alert-danger"><?php echo $erreur; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold mb-4">
                                Se connecter
                            </button>

                            <div class="text-center">
                                <p class="text-muted">Vous n'avez pas de compte ? 
                                    <a href="inscription.php" class="text-primary fw-semibold text-decoration-none">Créer un compte</a>
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