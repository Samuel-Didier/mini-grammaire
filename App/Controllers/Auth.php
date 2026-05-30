<?php
namespace App\Controllers;
use App\Models\User;
use App\Models\Favori;
use App\Models\Progression; // Import du modèle Progression

/**
 * Classe Auth
 *
 * Cette classe gère l'authentification et la gestion des comptes utilisateurs,
 * incluant la connexion, la déconnexion, l'inscription et l'affichage du profil.
 *
 * @package App\Controllers
 */
class Auth {

    /**
     * Gère la connexion des utilisateurs.
     *
     * Vérifie les identifiants (nom d'utilisateur ou adresse courriel et mot de passe)
     * soumis via la méthode POST. En cas de succès, initialise la session utilisateur
     * et redirige vers le profil. Sinon, affiche les erreurs sur la page de connexion.
     *
     * @param \Base $f3 L'instance du framework Fat-Free contenant les requêtes et variables globales.
     * @return void
     */
    public function login(\Base $f3)
    {
        $errors = [];
        if ($f3->get('VERB') === 'POST') {
            $username = trim($f3->get('POST.username'));
//            $email = trim($f3->get('POST.email'));
            $password = $f3->get('POST.password');
            $userModel = new User($f3->get('DB'));

            $user = $userModel->findByUsername($username);
            $email= $userModel->findByMail($username);
            if ($user == null && $email == null) {
                $errors[] = "Le nom d'utilisateur et l'adresse courriel sont incorrects";
            }else {
                if ($user!=null)
                {
                    if (password_verify($password, $user['password'])) {

                        // Stockage en session
                        $f3->set('SESSION.user', $user['username']);
                        $f3->set('SESSION.user_id', $user['id']); // Ajout de l'ID en session
                        $f3->reroute('/profile');
                        return;
                    } else {
                        $f3->set('error', 'Identifiants invalides');
                        $errors[] = ' Mot de passe incorrect';
                    }
                }
                if ($email!=null){
                    if (password_verify($password, $email['password'])) {

                        // Stockage en session
                        $f3->set('SESSION.user', $email['username']);
                        $f3->set('SESSION.user_id', $email['id']); // Ajout de l'ID en session
                        $f3->reroute('/profile');
                        return;
                    } else {
                        $f3->set('error', 'Identifiants invalides');
                        $errors[] = ' Mot de passe incorrect';
                    }
                }
            }


        }
        $f3->set('errors', $errors);

        $tpl = \Template::instance();
        $content = $tpl->render('pages/auth/login.html');
        $f3->set('title', 'Login');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    /**
     * Gère la déconnexion de l'utilisateur.
     *
     * Détruit toutes les données de la session en cours et
     * redirige l'utilisateur vers la page d'accueil.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function logout($f3) {
        $f3->clear('SESSION');
        $f3->reroute('/');
    }

    /**
     * Gère l'inscription de nouveaux utilisateurs.
     *
     * Traite les données soumises via la méthode POST (nom, prénom, nom d'utilisateur,
     * courriel, mot de passe). Valide les données (unicité du nom d'utilisateur,
     * longueur du mot de passe) et, en cas de succès, crée le compte,
     * connecte automatiquement l'utilisateur et le redirige vers son profil.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function register(\Base $f3)
    {
        $tpl = \Template::instance();
        $usersModel = new User($f3->get('DB'));
        $users = [];
        $errors = [];
        $errors_password = [];

        // --- Traitement POST (création) ---
        if ($f3->get('VERB') === 'POST') {
            $name = trim($f3->get('POST.name'));
            $name2 = trim($f3->get('POST.name2'));
            $username = trim((string)$f3->get('POST.username'));
            $email = trim($f3->get('POST.email'));
            $password = trim((string)$f3->get('POST.password'));
            $confirm_password = trim((string)$f3->get('POST.confirm_password'));

            // Validation
            if ($name == null) $errors[] = 'Le nom est requis.';
            if ($name2 == null) $errors[] = 'Le prénom est requis.';
            if ($username === '') {
                $errors[] = 'Le nom d\'utilisateur est requis.';
            } else {
                // Vérification de l'unicité du nom d'utilisateur dans la base de données
                $existingUser = $usersModel->findByUsername($username);

                // Si findByUsername retourne un résultat (l'utilisateur existe déjà)
                if ($existingUser) {
                    $errors[] = 'Le nom d\'utilisateur "' . htmlspecialchars($username) . ' existe déjà.';
                }
            }


            if ( strlen ( $password )<8)
            {
                $errors_password[] = "Le mot de passe doit cntenir minimum 8 caractères le votre en a ".strlen ($password);
            }
            if ($password === '') $errors_password[] = 'Le mot de passe est requis.';
            if ($password !== $confirm_password) $errors_password[] = 'Le mot de passe n\'èst pas correct' ;
            $hachage = password_hash($password, PASSWORD_DEFAULT);


            if (!$errors && !$errors_password) {
                try {
                    $usersModel->register($name,$name2,$hachage,$email,$username);



                    $user = $usersModel->findByUsername($username);

                    $f3->set('SESSION.user', $user['username']);
                    $f3->set('SESSION.user_id', $user['id']); // Ajout de l'ID en session
                    $f3->reroute('/profile');
                    return; // sécurité
                } catch (\PDOException $e) {
                    $errors[] = 'Erreur DB : ' . $e->getMessage();
                }
            }
//            var_dump($name,$name2,$email,$tel,$password);
        }
        $f3->set('errors', $errors);
        $f3->set('errors_password', $errors_password);
//        var_dump($errors,"\n");
//        var_dump($errors_password);

        // --- Données pour la vue ---
        $f3->set('users', $users);
        $f3->set('title', "S'inscrire");
        $content = $tpl->render('pages/auth/register.html');
        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    /**
     * Affiche le profil de l'utilisateur connecté.
     *
     * Vérifie si un utilisateur est bien connecté, sinon le redirige vers la page de connexion.
     * Récupère ensuite ses données personnelles, ses favoris et sa progression
     * (niveau et score) afin de les afficher sur la vue correspondante.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function profil(\Base $f3) {
        // 1. Vérification de l’authentification
        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }

        $userModel = new User($f3->get('DB'));
        $userSession = $f3->get('SESSION.user');

        // 2. Récupération des données complètes du client
        $data = $userModel->findData($userSession);

        // 3. Récupération du nombre de favoris
        $favoriModel = new Favori($f3->get('DB'));
        $favoris = $favoriModel->getFavorisByUser($data['id']);
        $favorisCount = count($favoris);

        // 4. Récupération de la progression (Niveau et Score)
        $progressionModel = new Progression($f3->get('DB'));
        $progression = $progressionModel->getByUser($data['id']);

        $niveau = $progression ? $progression['niveau_global'] : 'Non évalué';
        $score = $progression ? $progression['score_test_initial'] . '/10' : '-';

        // 5. Préparation des variables pour le template
        $f3->set('user', $userSession);
        $f3->set('client', $data);
        $f3->set('favorisCount', $favorisCount);
        $f3->set('niveau', $niveau);
        $f3->set('score', $score);

        // Gestion des messages flash (succès/erreur)
        if ($f3->exists('SESSION.flash')) {
            $f3->set('flash', $f3->get('SESSION.flash'));
            $f3->clear('SESSION.flash');
        }

        // 6. Rendu de la page
        $tpl = \Template::instance();
        $f3->set('title', 'Mon Profil');
        $content = $tpl->render('pages/auth/profile.html'); // Utilisation de profile.html

        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }
    /**
     * Affiche le formulaire d'édition du profil de l'utilisateur connecté.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function editProfile(\Base $f3) {
        // 1. Vérification de l’authentification
        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/mini-grammaire/login');
            return;
        }

        $userModel = new User($f3->get('DB'));
        $userSession = $f3->get('SESSION.user');

        // 2. Récupération des données complètes du client pour pré-remplir le formulaire
        $clientData = $userModel->findData($userSession);

        if (!$clientData) {
            $f3->reroute('/mini-grammaire/logout'); // Si l'utilisateur n'est pas trouvé en DB, déconnecter
            return;
        }

        // 3. Préparation des variables pour le template
        $f3->set('client', $clientData);
        $f3->set('title', 'Modifier mon Profil');

        $tpl = \Template::instance();

        $content = $tpl->render('pages/auth/profile_edit.html'); // Utilisation de profile.html

        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }

    /**
     * Traite la soumission du formulaire de modification du profil.
     * Met à jour les informations de l'utilisateur en base de données.
     *
     * @param \Base $f3 L'instance du framework Fat-Free.
     * @return void
     */
    public function updateProfile(\Base $f3) {
        // 1. Vérification de l’authentification
        if (!$f3->exists('SESSION.user_id')) { // Utiliser user_id pour plus de robustesse
            $f3->reroute('/mini-grammaire/login');
            return;
        }

        $userModel = new User($f3->get('DB'));
        $userId = $f3->get('SESSION.user_id');
        $errors = [];
        $successMessage = '';

        if ($f3->get('VERB') === 'POST') {
            $nom = trim($f3->get('POST.nom'));
            $prenom = trim($f3->get('POST.prenom'));
            $username = trim($f3->get('POST.username'));
            $email = trim($f3->get('POST.email'));
            $password = $f3->get('POST.password');
            $confirm_password = $f3->get('POST.confirm_password');

            $updateData = [
                'nom' => $nom,
                'prenom' => $prenom,
                'username' => $username,
                'email' => $email
            ];

            // Validation simple
            if (empty($nom)) $errors[] = 'Le nom est requis.';
            if (empty($prenom)) $errors[] = 'Le prénom est requis.';
            if (empty($username)) $errors[] = 'Le nom d\'utilisateur est requis.';
            if (empty($email)) $errors[] = 'L\'adresse email est requise.';

            // Vérifier l'unicité du nom d'utilisateur et de l'email si modifiés
            $currentUser = $userModel->getById($userId);
            if ($currentUser['username'] !== $username) {
                if ($userModel->findByUsername($username)) {
                    $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
                }
            }
            if ($currentUser['email'] !== $email) {
                if ($userModel->findByMail($email)) {
                    $errors[] = 'Cette adresse email est déjà utilisée.';
                }
            }

            // Gestion du mot de passe
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
                } elseif ($password !== $confirm_password) {
                    $errors[] = 'Les mots de passe ne correspondent pas.';
                } else {
                    $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
            }

            if (empty($errors)) {
                $updateSuccess = $userModel->updateUser($userId, $updateData);

                if ($updateSuccess) {
                    // Mettre à jour le nom d'utilisateur en session si modifié
                    if ($f3->get('SESSION.user') !== $username) {
                        $f3->set('SESSION.user', $username);
                    }
                    $successMessage = 'Votre profil a été mis à jour avec succès.';
                    $f3->set('SESSION.flash', ['type' => 'success', 'message' => $successMessage]);
                    $f3->reroute('/profile');
                    return;
                } else {
                    $errors[] = 'Une erreur est survenue lors de la mise à jour.';
                }
            }
        }

        // Si des erreurs ou pas de POST, réafficher le formulaire avec les données et erreurs
        $clientData = $userModel->getById($userId); // Récupérer les données à jour ou les dernières valides
        $f3->set('client', $clientData);
        $f3->set('errors', $errors);
        $f3->set('successMessage', $successMessage); // Pour afficher un message si besoin
        $f3->set('title', 'Modifier mon Profil');
        $f3->set('content', \Template::instance()->render('pages/auth/profile_edit.html'));
        $tpl = \Template::instance();

        $content = $tpl->render('profile_edit.html'); // Utilisation de profile.html

        $f3->set('content', $content);
        echo $tpl->render('layout.html');
    }


}