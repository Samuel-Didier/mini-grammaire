/**
 * TABLEAU DE BORD DES QUIZ
 * Gère le nombre de propositions d'une question : le nombre est choisi à la
 * création (de 2 à 6) et reste modifiable. Chaque proposition est identifiée par
 * une lettre (A, B, C...) et la bonne réponse est cochée par une radio button.
 */
(function () {
    'use strict';

    const LETTRES = ['A', 'B', 'C', 'D', 'E', 'F'];

    /**
     * Lit les valeurs saisies dans un conteneur de propositions.
     * @param {HTMLElement} conteneur Conteneur '.quiz-props'
     * @returns {{valeurs: string[], index: number}} Valeurs et index coché
     */
    function lireLignes(conteneur) {
        const inputs = conteneur.querySelectorAll('input[name="propositions[]"]');
        const valeurs = Array.prototype.map.call(inputs, input => input.value);
        const coche = conteneur.querySelector('input[name="correct"]:checked');
        const index = coche ? parseInt(coche.value, 10) : 0;

        return { valeurs: valeurs, index: isNaN(index) ? 0 : index };
    }

    /**
     * (Re)construit les lignes de propositions d'un conteneur.
     * @param {HTMLElement} conteneur Conteneur '.quiz-props'
     * @param {number} nb Nombre de propositions (2 à 6)
     * @param {string[]} valeurs Valeurs à conserver
     * @param {number} index Index de la bonne réponse à cocher
     */
    function construire(conteneur, nb, valeurs, index) {
        let html = '';
        for (let i = 0; i < nb; i++) {
            const lettre = LETTRES[i];
            const valeur = valeurs[i] || '';
            html += `
                <div class="quiz-prop">
                    <label class="quiz-prop-reponse" title="Cocher si c'est la bonne réponse">
                        <input type="radio" name="correct" value="${i}"${i === index ? ' checked' : ''}${i === 0 ? ' required' : ''}>
                        <span>${lettre}</span>
                    </label>
                    <input type="text" name="propositions[]" value="${valeur.replace(/"/g, '&quot;')}"
                           maxlength="255" placeholder="Proposition ${lettre}" aria-label="Proposition ${lettre}">
                </div>
            `;
        }
        conteneur.innerHTML = html;
    }

    /**
     * Branche chaque sélecteur de nombre de propositions sur son conteneur.
     */
    function initialiser() {
        document.querySelectorAll('.js-nb-props').forEach(select => {
            const conteneur = document.getElementById(select.dataset.cible);
            if (!conteneur) return;

            const nb = parseInt(select.value, 10) || 4;
            const etat = lireLignes(conteneur);
            construire(conteneur, nb, etat.valeurs, Math.min(etat.index, nb - 1));

            select.addEventListener('change', () => {
                const courant = lireLignes(conteneur);
                const nombre = parseInt(select.value, 10) || 4;
                construire(conteneur, nombre, courant.valeurs, Math.min(courant.index, nombre - 1));
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }
})();
