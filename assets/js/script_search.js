/**
 * GESTION DE LA MINI-GRAMMAIRE
 * Ce script gère l'interactivité du tableau des codes de correction :
 * - Ouverture/Fermeture des détails
 * - Édition des détails (pour les enseignants/admins)
 * - Recherche en temps réel
 */

/* ─────────────────────────────────────────────────
   1. TOGGLE DU PANNEAU DÉTAIL au clic sur la flèche ▶
   Ouvre ou ferme le panneau de détail au clic sur la flèche.
───────────────────────────────────────────────── */
function toggleDetail(e, arrow) {
    e.stopPropagation();
    const row = arrow.closest('.code-row');
    row.classList.toggle('expanded');
}

/* ─────────────────────────────────────────────────
   2. ÉDITION DES DÉTAILS
   Fonctionnalités pour modifier le texte des détails.
───────────────────────────────────────────────── */

/**
 * Démarre le mode édition pour un panneau de détail.
 * Remplace le texte par un champ input pré-rempli.
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} btn - Le bouton "Modifier" cliqué.
 */
function startDetailEdit(e, btn) {
    e.stopPropagation();
    const panel    = btn.closest('.code-detail');
    const textSpan = panel.querySelector('.detail-text');
    const input    = panel.querySelector('.detail-input');
    
    // Pré-remplir l'input en retirant l'emoji et les espaces
    input.value = textSpan.textContent.replace(/^[📌💡]\s*/, '').trim();
    
    panel.classList.add('editing-detail');
    input.focus();
    input.select();
}

/**
 * Sauvegarde les modifications du détail en envoyant une requête AJAX.
 * Met à jour le texte et quitte le mode édition.
 * @param {Event} e - L'événement (clic ou touche Entrée).
 * @param {HTMLElement} element - Le bouton "Sauvegarder" ou le champ input.
 */
async function saveDetail(e, element) {
    e.stopPropagation();
    const panel    = element.closest('.code-detail');
    const textSpan = panel.querySelector('.detail-text');
    const input    = panel.querySelector('.detail-input');
    const newVal   = input.value.trim();
    
    // Récupérer l'ID du code et le type de champ (detail ou example)
    const codeRow = panel.previousElementSibling.classList.contains('code-row') ? panel.previousElementSibling : panel.previousElementSibling.previousElementSibling;
    const codeId = codeRow.dataset.id; // Assurez-vous que le code-row a un data-id
    
    let fieldType = '';
    if (textSpan.textContent.startsWith('📌')) {
        fieldType = 'detail';
    } else if (textSpan.textContent.startsWith('💡')) {
        fieldType = 'example';
    }

    if (!codeId || !fieldType) {
        alert("Erreur: Impossible d'identifier l'élément à sauvegarder.");
        panel.classList.remove('editing-detail');
        return;
    }

    // Envoyer la requête AJAX
    try {
        const response = await fetch('/update-field', { // Chemin corrigé
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: codeId,
                field: fieldType,
                value: newVal
            })
        });
        const data = await response.json();

        if (data.success) {
            // Mise à jour visuelle après succès
            textSpan.textContent = (fieldType === 'detail' ? '📌 ' : '💡 ') + newVal;
            panel.classList.remove('editing-detail');
        } else {
            alert('Erreur lors de la sauvegarde : ' + (data.message || ''));
            panel.classList.remove('editing-detail'); // Quitter le mode édition même en cas d'erreur
        }
    } catch (error) {
        console.error('Erreur réseau ou serveur:', error);
        alert('Erreur de communication avec le serveur.');
        panel.classList.remove('editing-detail'); // Quitter le mode édition
    }
}

/**
 * Gère les raccourcis clavier dans le champ d'édition.
 * Entrée = Sauvegarder
 * Échap = Annuler
 * @param {Event} e - L'événement clavier.
 * @param {HTMLElement} input - Le champ input.
 */
function handleDetailKey(e, input) {
    if (e.key === 'Enter') {
        saveDetail(e, input);
    } else if (e.key === 'Escape') {
        e.stopPropagation();
        input.closest('.code-detail').classList.remove('editing-detail');
    }
}

/* ─────────────────────────────────────────────────
   3. RECHERCHE EN TEMPS RÉEL — searchFunction()
   Appelée à chaque frappe dans #searchInput (oninput).
───────────────────────────────────────────────── */
function searchFunction() {
    const filter = document.getElementById("searchInput").value.toLowerCase().trim();
    const cards  = document.querySelectorAll(".category-card");
    let totalVisible = 0;

    cards.forEach(card => {
        const rows       = card.querySelectorAll(".code-row");
        const headerText = card.querySelector(".card-header").textContent.toLowerCase();
        const headerMatch = filter !== "" && headerText.includes(filter);

        let cardVisible = 0;

        rows.forEach(row => {
            const code = row.dataset.code.toLowerCase();
            const desc = row.dataset.desc.toLowerCase();

            const match = filter === "" || headerMatch || code.includes(filter) || desc.includes(filter);

            if (match) {
                row.classList.remove("hidden");

                if (filter !== "" && !headerMatch) {
                    row.classList.add("highlight");
                } else {
                    row.classList.remove("highlight");
                }

                cardVisible++;
                totalVisible++;
            } else {
                row.classList.add("hidden");
                row.classList.remove("highlight");
            }
        });

        card.style.display = cardVisible > 0 ? "" : "none";
    });

    document.getElementById("noResults").style.display =
        totalVisible === 0 && filter !== "" ? "block" : "none";
}