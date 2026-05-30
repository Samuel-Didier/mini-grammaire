<?php

namespace App\Controllers;

use App\Models\Astuces;
use App\Models\Favori;
use App\Models\User;

/**
 * Contrôleur Astuces
 * Gère l'affichage de la liste des astuces et l'ajout de nouvelles astuces.
 */
class AstucesController {

    /**
     * Récupère toutes les astuces et les affiche.
     * Vérifie également si chaque astuce est en favori pour l'utilisateur connecté.
     *
     * @param \Base $f3 Instance du framework
     */
    public function getAstuces(\Base $f3) {
        // --- Vérification de l'authentification ---
        // Si l'utilisateur n'est pas connecté, rediriger vers la page de connexion
        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return; // Arrêter l'exécution du contrôleur
        }
        // --- Fin de la vérification ---

        $tpl = \Template::instance();
        $userModel = new User($f3->get('DB'));
        $user = $userModel->findByUsername($f3->get('SESSION.user'));
        $userRole = $user['role'] ?? 'etudiant'; // Récupérer le rôle de l'utilisateur

        // 1. Récupérer toutes les astuces depuis le modèle
        $astucesModel = new Astuces($f3->get('DB'));
        $allAstuces = $astucesModel->getAll();

        // 2. Initialiser la propriété 'is_favori' pour toutes les astuces à false par défaut
        foreach ($allAstuces as &$astuce) {
            $astuce['is_favori'] = false;
        }
        unset($astuce); // Détruire la référence pour éviter des modifications inattendues

        // 3. Si l'utilisateur est connecté, mettre à jour 'is_favori' pour les astuces favorites
        // (Cette partie est maintenant garantie d'être exécutée car on a redirigé si non connecté)
        if ($user) { // Double vérification, au cas où la session.user existerait mais pas l'utilisateur en DB
            $favoriModel = new Favori($f3->get('DB'));
            foreach ($allAstuces as &$astuce) {
                $astuce['is_favori'] = $favoriModel->isFavori($user['id'], $astuce['id']);
            }
            unset($astuce); // Détruire la référence
        }

        // 4. Passer les données à la vue
        $f3->set('astuces', $allAstuces);
        $f3->set('userRole', $userRole); // Passer le rôle à la vue
        $f3->set('title', 'Astuces de Français');
        $f3->set('content', $tpl->render('pages/astuces/astuces.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Affiche le formulaire d'ajout d'une nouvelle astuce.
     * Seuls les administrateurs peuvent accéder à cette page.
     *
     * @param \Base $f3 L'instance du framework.
     * @return void
     */
    public function addAstuces(\Base $f3) {
        // 1. Vérification de l'authentification et du rôle (Admin uniquement)
        if (!$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }

        $tpl = \Template::instance();

        $userModel = new User($f3->get('DB'));
        $user = $userModel->getById($f3->get('SESSION.user_id'));

        if (!$user || $user['role'] !== 'admin') {
            $f3->set('SESSION.flash', ['type' => 'error', 'message' => 'Accès refusé. Seuls les administrateurs peuvent ajouter des astuces.']);
            $f3->reroute('/astuces');
            return;
        }

        // 2. Préparation de la vue
        $f3->set('title', 'Ajouter une Astuce');
        $f3->set('old_titre', '');
        $f3->set('old_description', '');
        $f3->set('content', $tpl->render('pages/astuces/astuces_add.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Traite la soumission du formulaire d'ajout d'astuce et l'enregistre en base de données.
     * Seuls les administrateurs peuvent effectuer cette action.
     *
     * @param \Base $f3 L'instance du framework.
     * @return void
     */
    public function save(\Base $f3) {
        // 1. Vérification de l'authentification et du rôle (Admin uniquement)
        if (!$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }
        $tpl = \Template::instance();

        $userModel = new User($f3->get('DB'));
        $user = $userModel->getById($f3->get('SESSION.user_id'));

        if (!$user || $user['role'] !== 'admin') {
            $f3->set('SESSION.flash', ['type' => 'error', 'message' => 'Accès refusé. Seuls les administrateurs peuvent ajouter des astuces.']);
            $f3->reroute('/astuces');
            return;
        }

        // 2. Traitement du formulaire POST
        if ($f3->get('VERB') === 'POST') {
            $titre = trim($f3->get('POST.titre'));
            $description = trim($f3->get('POST.description'));
            $errors = [];

            if (empty($titre)) $errors[] = 'Le titre est requis.';
            if (empty($description)) $errors[] = 'La description est requise.';

            if (empty($errors)) {
                $astucesModel = new Astuces($f3->get('DB'));
                try {
                    $astucesModel->addAstuce($titre, $description);
                    $f3->set('SESSION.flash', ['type' => 'success', 'message' => 'Astuce ajoutée avec succès !']);
                    $f3->reroute('/astuces');
                    return;
                } catch (\PDOException $e) {
                    $errors[] = 'Erreur lors de l\'ajout de l\'astuce en base de données.';
                    error_log('Erreur DB ajout astuce: ' . $e->getMessage());
                }
            }
            // Si erreurs, les passer à la vue pour réafficher le formulaire
            $f3->set('errors', $errors);
            $f3->set('old_titre', $titre);
            $f3->set('old_description', $description);
        }

        // 3. Rendu du formulaire (en cas d'erreur ou de premier affichage)
        $f3->set('title', 'Ajouter une Astuce');
        $f3->set('content', $tpl->render('pages/astuces/astuces_add.html'));

        echo $tpl->render('layout.html');
    }
}