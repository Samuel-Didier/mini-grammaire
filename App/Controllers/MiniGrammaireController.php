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

        // 1. Vérifier l'authentification et les permissions via BaseController
        // Seuls les rôles différents de 'etudiant' peuvent modifier (ex: 'admin', 'professeur')
        // On suppose ici que le rôle requis est 'admin' ou qu'on utilise requireRole pour bloquer les étudiants.
        // Puisque BaseController::requireRole redirige, et que c'est une requête AJAX, 
        // nous allons plutôt utiliser le hive F3 set par beforeroute.
        
        if ($f3->get('userRole') === 'etudiant' || $f3->get('userRole') === 'invite') {
            echo json_encode(['success' => false, 'message' => 'Permission refusée.']);
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
}