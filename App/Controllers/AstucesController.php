<?php

namespace App\Controllers;

use App\Models\Astuces;
use App\Models\Favori;
use App\Models\User;

/**
 * Contrôleur Astuces
 * Gère l'affichage, l'ajout, l'édition et la sauvegarde des astuces en utilisant le RBAC centralisé.
 */
class AstucesController extends BaseController {

    /**
     * Récupère toutes les astuces et les affiche.
     *
     * @param \Base $f3 Instance du framework
     */
    public function getAstuces(\Base $f3) {
        $tpl = \Template::instance();

        $user = $f3->get('user');
        $userRole = $f3->get('userRole');

        // 1. Récupérer toutes les astuces
        $astucesModel = new Astuces($f3->get('DB'));
        $allAstuces = $astucesModel->getAll();

        // 2. Marquer les favoris si l'utilisateur existe
        $favoriIds = [];
        if ($user) {
            $favoriModel = new Favori($f3->get('DB'));
            if (method_exists($favoriModel, 'getAstucesIdsByUser')) {
                // Optimisé : une seule requête (nécessite Favori modèle à jour)
                $favoriIds = $favoriModel->getAstucesIdsByUser($user['id']);
            } else {
                // Repli : version modèle ancienne (une requête par astuce)
                foreach ($allAstuces as $astuce) {
                    if ($favoriModel->isFavori($user['id'], (int)$astuce['id'])) {
                        $favoriIds[] = (int)$astuce['id'];
                    }
                }
            }
        }
        foreach ($allAstuces as &$astuce) {
            $astuce['is_favori'] = in_array((int)$astuce['id'], $favoriIds, true);
        }
        unset($astuce);

        // 3. Passer les données à la vue (le message flash est consommé par BaseController)
        $f3->set('astuces', $allAstuces);
        $f3->set('userRole', $userRole);
        $f3->set('canEdit', in_array($userRole, BaseController::EDITOR_ROLES, true));
        $f3->set('title', 'Astuces de Français');
        $f3->set('content', $tpl->render('pages/astuces/astuces.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Affiche le formulaire d'ajout d'une nouvelle astuce.
     * Accès restreint aux administrateurs et aux enseignants.
     *
     * @param \Base $f3 L'instance du framework.
     */
    public function addAstuces(\Base $f3) {
        $tpl = \Template::instance();

        // Vérification de l'authentification : rediriger si non connecté
        if (!$f3->exists('SESSION.user') && !$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }
        // Utilisation de la logique RBAC centralisée
        $this->requireRole($f3, BaseController::EDITOR_ROLES);

        // Préparation de la vue
        $f3->set('pageTitle', 'Ajouter une Nouvelle Astuce');
        $f3->set('pageSubtitle', 'Remplissez les champs pour créer une nouvelle astuce.');
        $f3->set('formAction', '/astuces/save');
        $f3->set('submitLabel', 'Ajouter l\'astuce');
        $f3->set('old_titre', '');
        $f3->set('old_description', '');
        $f3->set('title', 'Ajouter une Astuce');
        $f3->set('content', $tpl->render('pages/astuces/astuces_form.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Enregistre une nouvelle astuce.
     * Accès restreint aux administrateurs et aux enseignants.
     *
     * @param \Base $f3 L'instance du framework.
     */
    public function save(\Base $f3) {
        $tpl = \Template::instance();

        // Utilisation de la logique RBAC centralisée
        $this->requireRole($f3, BaseController::EDITOR_ROLES);

        if ($f3->get('VERB') === 'POST') {
            // Protection CSRF
            if (!$this->csrfValidate($f3)) {
                $f3->set('SESSION.flash', [
                    'type' => 'error',
                    'message' => 'Jeton de sécurité invalide. Veuillez réessayer.'
                ]);
                $f3->reroute('/astuces');
                return;
            }

            $titre = trim((string)$f3->get('POST.titre'));
            $description = trim((string)$f3->get('POST.description'));
            $errors = [];

            if ($titre === '') $errors[] = 'Le titre est requis.';
            if ($description === '') $errors[] = 'La description est requise.';

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

        $f3->set('pageTitle', 'Ajouter une Nouvelle Astuce');
        $f3->set('pageSubtitle', 'Remplissez les champs pour créer une nouvelle astuce.');
        $f3->set('formAction', '/astuces/save');
        $f3->set('submitLabel', 'Ajouter l\'astuce');
        $f3->set('title', 'Ajouter une Astuce');
        $f3->set('content', $tpl->render('pages/astuces/astuces_form.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Affiche le formulaire d'édition d'une astuce.
     * Accès restreint aux administrateurs et aux enseignants.
     *
     * @param \Base $f3 L'instance du framework.
     * @param array $args Les paramètres de la route (@id).
     */
    public function editAstuce(\Base $f3, array $args) {
        $tpl = \Template::instance();

        if (!$f3->exists('SESSION.user') && !$f3->exists('SESSION.user_id')) {
            $f3->reroute('/login');
            return;
        }
        $this->requireRole($f3, BaseController::EDITOR_ROLES);

        $id = (int)($args['id'] ?? 0);
        $astuce = (new Astuces($f3->get('DB')))->getAstuce($id);
        if (!$astuce) {
            $f3->set('SESSION.flash', ['type' => 'error', 'message' => 'Astuce introuvable.']);
            $f3->reroute('/astuces');
            return;
        }

        $f3->set('pageTitle', 'Modifier l\'Astuce');
        $f3->set('pageSubtitle', 'Modifiez le titre et la description, puis enregistrez.');
        $f3->set('formAction', '/astuces/update/' . $id);
        $f3->set('submitLabel', 'Enregistrer les modifications');
        $f3->set('old_titre', $astuce['titre']);
        $f3->set('old_description', $astuce['description']);
        $f3->set('title', 'Modifier une Astuce');
        $f3->set('content', $tpl->render('pages/astuces/astuces_form.html'));

        echo $tpl->render('layout.html');
    }

    /**
     * Met à jour une astuce existante.
     * Accès restreint aux administrateurs et aux enseignants.
     *
     * @param \Base $f3 L'instance du framework.
     * @param array $args Les paramètres de la route (@id).
     */
    public function update(\Base $f3, array $args) {
        $tpl = \Template::instance();

        $this->requireRole($f3, BaseController::EDITOR_ROLES);
        $id = (int)($args['id'] ?? 0);

        if ($f3->get('VERB') === 'POST') {
            // Protection CSRF
            if (!$this->csrfValidate($f3)) {
                $f3->set('SESSION.flash', [
                    'type' => 'error',
                    'message' => 'Jeton de sécurité invalide. Veuillez réessayer.'
                ]);
                $f3->reroute('/astuces');
                return;
            }

            $titre = trim((string)$f3->get('POST.titre'));
            $description = trim((string)$f3->get('POST.description'));
            $errors = [];

            if ($titre === '') $errors[] = 'Le titre est requis.';
            if ($description === '') $errors[] = 'La description est requise.';

            $astucesModel = new Astuces($f3->get('DB'));
            if (empty($errors)) {
                try {
                    if ($astucesModel->updateAstuce($id, $titre, $description)) {
                        $f3->set('SESSION.flash', ['type' => 'success', 'message' => 'Astuce modifiée avec succès !']);
                        $f3->reroute('/astuces');
                        return;
                    }
                    $errors[] = 'Astuce introuvable.';
                } catch (\PDOException $e) {
                    $errors[] = 'Erreur lors de la modification de l\'astuce en base de données.';
                    error_log('Erreur DB modification astuce: ' . $e->getMessage());
                }
            }

            // En cas d'erreur, on ré-affiche le formulaire avec les valeurs saisies
            $f3->set('errors', $errors);
            $f3->set('old_titre', $titre);
            $f3->set('old_description', $description);
        }

        // Recharger l'astuce existante si les valeurs ne sont pas déjà en session de formulaire
        $f3->set('pageTitle', 'Modifier l\'Astuce');
        $f3->set('pageSubtitle', 'Modifiez le titre et la description, puis enregistrez.');
        $f3->set('formAction', '/astuces/update/' . $id);
        $f3->set('submitLabel', 'Enregistrer les modifications');
        $f3->set('title', 'Modifier une Astuce');
        $f3->set('content', $tpl->render('pages/astuces/astuces_form.html'));

        echo $tpl->render('layout.html');
    }
}