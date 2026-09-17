/**
 * GESTION DE LA MINI-GRAMMAIRE
 * Ce script gère :
 * - La recherche en temps réel des codes (page liste)
 * - L'édition des exemples fautifs/corrigés (page détail, enseignants/admins)
 */

/* ─────────────────────────────────────────────────
   1. ÉDITION DES EXEMPLES (page détail)
   Chaque exemple (fautif ✗ ou corrigé ✓) est un .example-item
   suivi d'un .detail-editor masqué (data-field + data-id).
───────────────────────────────────────────────── */

/**
 * Démarre le mode édition : masque l'exemple affiché, affiche son éditeur.
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} btn - Le bouton "Modifier" cliqué.
 */
function startDetailEdit(e, btn) {
    e.stopPropagation();
    const exampleItem = btn.closest('.example-item');
    const editor = exampleItem.nextElementSibling;
    const input = editor.querySelector('.detail-input');

    input.value = exampleItem.querySelector('.example-text').textContent.trim();
    editor.classList.add('editing');
    input.focus();
    input.select();
    autoResize(input);
}

/**
 * Ajuste la hauteur du textarea selon son contenu.
 * @param {HTMLTextAreaElement} input - Le textarea à redimensionner.
 */
function autoResize(input) {
    input.style.height = 'auto';
    input.style.height = input.scrollHeight + 'px';
}

/**
 * Sauvegarde les modifications de l'exemple en envoyant une requête AJAX.
 * Met à jour le texte affiché et quitte le mode édition.
 * @param {Event} e - L'événement (clic ou combinaison clavier).
 * @param {HTMLElement} element - Le bouton "Sauvegarder" ou le textarea.
 */
async function saveDetail(e, element) {
    e.stopPropagation();
    const editor = element.closest('.detail-editor');
    const input = editor.querySelector('.detail-input');
    const newVal = input.value.trim();

    const codeId = editor.dataset.id;
    const fieldType = editor.dataset.field;

    if (!codeId || !fieldType) {
        alert("Erreur: Impossible d'identifier l'élément à sauvegarder.");
        editor.classList.remove('editing');
        return;
    }

    try {
        const response = await fetch('/update-field', {
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
            const exampleItem = editor.previousElementSibling;
            const textSpan = exampleItem.querySelector('.example-text');
            textSpan.textContent = newVal;
            editor.classList.remove('editing');
        } else {
            alert('Erreur lors de la sauvegarde : ' + (data.message || ''));
        }
    } catch (error) {
        console.error('Erreur réseau ou serveur:', error);
        alert('Erreur de communication avec le serveur.');
    }
}

/**
 * Annule l'édition en cours et restaure l'affichage de l'exemple.
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} element - Le bouton "Annuler" ou le textarea.
 */
function cancelDetailEdit(e, element) {
    e.stopPropagation();
    element.closest('.detail-editor').classList.remove('editing');
}

/**
 * Gère les raccourcis clavier dans le textarea.
 * Ctrl+Entrée ou Cmd+Entrée = Sauvegarder
 * Échap = Annuler
 * @param {Event} e - L'événement clavier.
 * @param {HTMLTextAreaElement} input - Le textarea.
 */
function handleDetailKey(e, input) {
    const editor = input.closest('.detail-editor');

    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        saveDetail(e, input);
    } else if (e.key === 'Escape') {
        e.stopPropagation();
        editor.classList.remove('editing');
    }
}

/* ─────────────────────────────────────────────────
   2. ÉDITION DU CODE / DE LA CATÉGORIE (page détail)
   L'en-tête .detail-header contient un bouton ✏️ et
   un éditeur .header-editor (code + catégorie).
───────────────────────────────────────────────── */

/**
 * Bascule l'éditeur d'en-tête (code/catégorie).
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} btn - Le bouton "Modifier" cliqué.
 */
function toggleHeaderEdit(e, btn) {
    e.stopPropagation();
    const header = btn.closest('.detail-header');
    header.classList.toggle('editing');
    if (header.classList.contains('editing')) {
        header.querySelector('.header-input-code').focus();
    }
}

/**
 * Sauvegarde le code et/ou la catégorie d'une famille (AJAX).
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} element - Le bouton "Sauvegarder".
 */
async function saveParentEdit(e, element) {
    e.stopPropagation();
    const header = element.closest('.detail-header');
    const editor = header.querySelector('.header-editor');
    const oldParent = editor.dataset.parent;
    const newParent = header.querySelector('.header-input-code').value.trim().toUpperCase();
    const newCategory = header.querySelector('.header-input-category').value;

    if (!newParent || !newCategory) {
        alert('Le code et la catégorie sont requis.');
        return;
    }

    try {
        const response = await fetch('/update-parent-code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ oldParent, newParent, newCategory })
        });
        const data = await response.json();

        if (data.success) {
            // Rechargement pour regrouper/recatégoriser correctement
            location.reload();
        } else {
            alert('Erreur : ' + (data.message || ''));
        }
    } catch (error) {
        console.error('Erreur réseau ou serveur:', error);
        alert('Erreur de communication avec le serveur.');
    }
}

/**
 * Annule l'édition de l'en-tête.
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} element - Le bouton "Annuler".
 */
function cancelParentEdit(e, element) {
    e.stopPropagation();
    element.closest('.detail-header').classList.remove('editing');
}

/* ─────────────────────────────────────────────────
   3. ÉDITION D'UN SOUS-CODE (code + description, page détail)
   Chaque règle .rule-card a un .entry-editor (code + description).
───────────────────────────────────────────────── */

/**
 * Bascule l'éditeur du sous-code de la règle.
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} btn - Le bouton "Modifier" cliqué.
 */
function toggleEntryEdit(e, btn) {
    e.stopPropagation();
    const card = btn.closest('.rule-card');
    card.classList.toggle('editing');
    if (card.classList.contains('editing')) {
        card.querySelector('.entry-input-code').focus();
        autoResize(card.querySelector('.entry-input-desc'));
    }
}

/**
 * Sauvegarde le sous-code et sa description (AJAX).
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} element - Le bouton "Sauvegarder".
 */
async function saveEntryEdit(e, element) {
    e.stopPropagation();
    const card = element.closest('.rule-card');
    const editor = card.querySelector('.entry-editor');
    const id = editor.dataset.id;
    const code = card.querySelector('.entry-input-code').value.trim().toUpperCase();
    const description = card.querySelector('.entry-input-desc').value.trim();

    if (!code) {
        alert('Le code est requis.');
        return;
    }

    try {
        const response = await fetch('/update-code-entry', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, code, description })
        });
        const data = await response.json();

        if (data.success) {
            // Mise à jour locale des libellés puis rechargement
            card.querySelector('.rule-code').textContent = code;
            card.querySelector('.rule-title').textContent = description;
            card.querySelector('.entry-input-code').value = code;
            card.querySelector('.entry-input-desc').value = description;
            alert('Mise à jour réussie.');
            location.reload();
        } else {
            alert('Erreur : ' + (data.message || ''));
        }
    } catch (error) {
        console.error('Erreur réseau ou serveur:', error);
        alert('Erreur de communication avec le serveur.');
    }
}

/**
 * Annule l'édition du sous-code.
 * @param {Event} e - L'événement clic.
 * @param {HTMLElement} element - Le bouton "Annuler".
 */
function cancelEntryEdit(e, element) {
    e.stopPropagation();
    element.closest('.rule-card').classList.remove('editing');
}

/* ─────────────────────────────────────────────────
   3. RECHERCHE EN TEMPS RÉEL — searchFunction() (page liste)
   Filtre les puces .code-chip à chaque frappe dans #searchInput.
───────────────────────────────────────────────── */
function searchFunction() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;
    const filter = searchInput.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.code-category-card');
    let totalVisible = 0;

    cards.forEach(card => {
        const chips = card.querySelectorAll('.code-chip');
        const headerText = card.querySelector('.card-header').textContent.toLowerCase();
        const headerMatch = filter !== '' && headerText.includes(filter);

        let cardVisible = 0;

        chips.forEach(chip => {
            const code = chip.dataset.code.toLowerCase();
            const match = filter === '' || headerMatch || code.includes(filter);

            if (match) {
                chip.classList.remove('hidden');
                if (filter !== '' && !headerMatch) {
                    chip.classList.add('highlight');
                } else {
                    chip.classList.remove('highlight');
                }
                cardVisible++;
                totalVisible++;
            } else {
                chip.classList.add('hidden');
                chip.classList.remove('highlight');
            }
        });

        card.style.display = cardVisible > 0 ? '' : 'none';
    });

    const noResults = document.getElementById('noResults');
    if (noResults) {
        noResults.style.display = totalVisible === 0 && filter !== '' ? 'block' : 'none';
    }
}