<?php

namespace App\Controllers;

use App\Models\Astuces;
use App\Models\Favori;
use App\Models\User;

/**
 * Contrôleur Astuces
 * Gère l'affichage, l'ajout et l'enregistrement des astuces en utilisant le RBAC centralisé.
 */
class AstucesController extends BaseController {

    /**
     * Récupère toutes les astuces et les affiche.
     *
     * @param \Base $f3 Instance du framework
     */
    public function getAstuces(\Base $f3) {
        $tpl = \Template::instance();

        // Vérification de l'authentification : rediriger si non connecté
        if (!$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }

        $user = $f3->get('user');
        $userRole = $f3->get('userRole');

        // 1. Récupérer toutes les astuces
        $astucesModel = new Astuces($f3->get('DB'));
        $allAstuces = $astucesModel->getAll();

        // 2. Initialiser et vérifier les favoris si l'utilisateur existe
        foreach ($allAstuces as &$astuce) {
            $astuce['is_favori'] = false;
        }
        unset($astuce);

        if ($user) {
            $favoriModel = new Favori($f3->get('DB'));
            foreach ($allAstuces as &$astuce) {
                $astuce['is_favori'] = $favoriModel->isFavori($user['id'], $astuce['id']);
            }
            unset($astuce);
        }

        // 3. Passer les données à la vue
        $f3->set('astuces', $allAstuces);
        $f3->set('userRole', $userRole);
        $f3->set('title', 'Astuces de Français');
        $f3->set('content', $tpl->render('pages/astuces/astuces.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Affiche le formulaire d'ajout d'une nouvelle astuce.
     * Accès restreint aux administrateurs.
     *
     * @param \Base $f3 L'instance du framework.
     */
    public function addAstuces(\Base $f3) {
        $tpl = \Template::instance();

        // Utilisation de la logique RBAC centralisée
        $this->requireRole($f3, 'admin');

        // Préparation de la vue
        $f3->set('title', 'Ajouter une Astuce');
        $f3->set('old_titre', '');
        $f3->set('old_description', '');
        $f3->set('content', $tpl->render('pages/astuces/astuces_add.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Enregistre une nouvelle astuce.
     * Accès restreint aux administrateurs.
     *
     * @param \Base $f3 L'instance du framework.
     */
    public function save(\Base $f3) {
        $tpl = \Template::instance();

        // Utilisation de la logique RBAC centralisée
        $this->requireRole($f3, 'admin');

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
            $f3->set('errors', $errors);
            $f3->set('old_titre', $titre);
            $f3->set('old_description', $description);
        }

        $f3->set('title', 'Ajouter une Astuce');
        $f3->set('content', $tpl->render('pages/astuces/astuces_add.html'));

        echo $tpl->render('layout.html');
    }
}
