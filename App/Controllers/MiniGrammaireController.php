<?php

namespace App\Controllers;

use App\Models\MiniGrammaire;
use App\Models\User; // Pour vérifier le rôle de l'utilisateur

/**
 * Contrôleur MiniGrammaireController
 * Gère les opérations CRUD pour les codes de mini-grammaire.
 */
class MiniGrammaireController extends BaseController
{
    /**
     * Met à jour un champ (détail ou exemple) d'un code de mini-grammaire.
     * Accessible via une requête AJAX (POST).
     *
     * @param \Base $f3 Instance du framework.
     * @return void Retourne une réponse JSON.
     */
    public function updateCodeField(\Base $f3)
    {
        header('Content-Type: application/json');

        // 1. Accès réservé aux rôles éditeurs (admin, enseignant) — liste blanche stricte
        // Un rôle inconnu ou erroné (ex: 'enseignent') est refusé.
        if (!in_array($f3->get('userRole'), BaseController::EDITOR_ROLES, true)) {
            echo json_encode(['success' => false, 'message' => 'Permission refusée.']);
            exit;
        }

        // 1bis. Protection CSRF (jeton envoyé par le JavaScript dans l'en-tête X-CSRF-Token)
        if (!$this->csrfValidate($f3)) {
            echo json_encode(['success' => false, 'error' => 'CSRF_INVALID', 'message' => 'Jeton de sécurité invalide.']);
            exit;
        }

        // 2. Récupérer les données de la requête
        $data = json_decode($f3->get('BODY'), true);
        $id = $data['id'] ?? null;
        $field = $data['field'] ?? null; // 'detail' ou 'example'
        $value = $data['value'] ?? null;

        if (!$id || !$field || $value === null) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
            exit;
        }

        // 3. Mettre à jour en base de données
        $miniGrammaireModel = new MiniGrammaire($f3->get('DB'));
        $success = $miniGrammaireModel->updateField($id, $field, $value);

        // 4. Retourner la réponse
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Mise à jour réussie.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour.']);
        }
        exit;
    }

    /**
     * Renomme un code parent et/ou change sa catégorie (toute la famille).
     * Accessible via une requête AJAX (POST).
     *
     * @param \Base $f3 Instance du framework.
     * @return void Retourne une réponse JSON.
     */
    public function updateParentCode(\Base $f3)
    {
        header('Content-Type: application/json');

        if (!in_array($f3->get('userRole'), BaseController::EDITOR_ROLES, true)) {
            echo json_encode(['success' => false, 'message' => 'Permission refusée.']);
            exit;
        }

        if (!$this->csrfValidate($f3)) {
            echo json_encode(['success' => false, 'error' => 'CSRF_INVALID', 'message' => 'Jeton de sécurité invalide.']);
            exit;
        }

        $data = json_decode($f3->get('BODY'), true);
        $oldParent = $data['oldParent'] ?? null;
        $newParent = $data['newParent'] ?? null;
        $newCategory = $data['newCategory'] ?? null;

        if (!$oldParent || !$newParent || !$newCategory) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
            exit;
        }

        $miniGrammaireModel = new MiniGrammaire($f3->get('DB'));
        $result = $miniGrammaireModel->updateParentGroup($oldParent, $newParent, $newCategory);

        if (isset($result['success'])) {
            echo json_encode(['success' => true, 'message' => 'Mise à jour réussie.']);
        } else {
            echo json_encode(['success' => false, 'message' => implode(' ', $result['errors'] ?? ['Échec de la mise à jour.'])]);
        }
        exit;
    }

    /**
     * Modifie un sous-code (code + description/règle). Accès AJAX (POST).
     *
     * @param \Base $f3 Instance du framework.
     * @return void Retourne une réponse JSON.
     */
    public function updateCodeEntry(\Base $f3)
    {
        header('Content-Type: application/json');

        if (!in_array($f3->get('userRole'), BaseController::EDITOR_ROLES, true)) {
            echo json_encode(['success' => false, 'message' => 'Permission refusée.']);
            exit;
        }

        if (!$this->csrfValidate($f3)) {
            echo json_encode(['success' => false, 'error' => 'CSRF_INVALID', 'message' => 'Jeton de sécurité invalide.']);
            exit;
        }

        $data = json_decode($f3->get('BODY'), true);
        $id = (int)($data['id'] ?? 0);
        $code = $data['code'] ?? null;
        $description = $data['description'] ?? null;

        if (!$id || !$code) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
            exit;
        }

        $miniGrammaireModel = new MiniGrammaire($f3->get('DB'));
        $result = $miniGrammaireModel->updateCodeEntry($id, $code, $description ?? '');

        if (isset($result['success'])) {
            echo json_encode(['success' => true, 'message' => 'Mise à jour réussie.']);
        } else {
            echo json_encode(['success' => false, 'message' => implode(' ', $result['errors'] ?? ['Échec de la mise à jour.'])]);
        }
        exit;
    }
}