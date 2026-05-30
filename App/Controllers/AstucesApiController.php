<?php

namespace App\Controllers;

use App\Models\Astuces;

/**
 * Contrôleur AstucesApiController
 * Gère les requêtes API pour les astuces.
 * Renvoie les données au format JSON.
 */
class AstucesApiController
{
    /**
     * Récupère toutes les astuces et les renvoie au format JSON.
     * Route: GET /mini-grammaire/api/astuces
     * @param \Base $f3 Instance du framework.
     * @return void Retourne une réponse JSON.
     */
    public function getJsonAstuces(\Base $f3)
    {
        // --- DÉBOGAGE TEMPORAIRE ---
        // Vérifie si cette méthode est appelée
//        var_dump("Requête reçue par AstucesApiController->getJsonAstuces!");
        // --- FIN DÉBOGAGE ---

        header('Content-Type: application/json'); // Indique que la réponse est du JSON

        $astucesModel = new Astuces($f3->get('DB'));
        $allAstuces = $astucesModel->getAll();

        echo json_encode($allAstuces); // Encode les données en JSON
        exit; // Arrête l'exécution pour éviter tout affichage HTML parasite
    }
}