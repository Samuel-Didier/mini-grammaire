<?php

namespace App\Controllers;

use App\Models\User;

/**
 * Contrôleur de base pour l'application.
 * Contient des méthodes utilitaires partagées par tous les contrôleurs, notamment pour la gestion des rôles (RBAC).
 */
abstract class BaseController {

    /**
     * Méthode exécutée automatiquement par F3 avant chaque route.
     * Récupère l'utilisateur en session et définit son rôle globalement.
     *
     * @param \Base $f3 Instance du framework
     */
    public function beforeroute(\Base $f3) {
        $user = null;
        $userRole = 'invite'; // Rôle par défaut pour les utilisateurs non connectés

        // Recherche de l'utilisateur basé sur l'ID ou le nom d'utilisateur en session
        if ($f3->exists('SESSION.user_id')) {
            $userModel = new User($f3->get('DB'));
            $user = $userModel->getById($f3->get('SESSION.user_id'));
        } elseif ($f3->exists('SESSION.user')) {
            $userModel = new User($f3->get('DB'));
            $user = $userModel->findByUsername($f3->get('SESSION.user'));
        }

        if ($user) {
            $userRole = $user['role'] ?? 'etudiant';
            $f3->set('user', $user);
        }

        // Met le rôle à disposition de tous les contrôleurs et vues
        $f3->set('userRole', $userRole);
    }

    /**
     * Valide si l'utilisateur connecté possède le rôle requis.
     * Redirige vers la page de connexion ou les astuces en cas d'échec.
     *
     * @param \Base $f3 Instance du framework
     * @param string $role Rôle requis (ex: 'admin')
     */
    protected function requireRole(\Base $f3, string $role) {
        // Redirection vers login si aucune session n'est active
        if (!$f3->exists('SESSION.user_id') && !$f3->exists('SESSION.user')) {
            $f3->reroute('/login');
            return;
        }

        // Vérification du rôle stocké dans le hive de F3
        if ($f3->get('userRole') !== $role) {
            $f3->set('SESSION.flash', [
                'type' => 'error', 
                'message' => 'Accès refusé. Vous n\'avez pas les permissions nécessaires pour effectuer cette action.'
            ]);
            $f3->reroute('/astuces');
        }
    }
}
