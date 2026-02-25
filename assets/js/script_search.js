/* ─────────────────────────────────────────────────
  TOGGLE DU PANNEAU DÉTAIL au clic sur la flèche ▶
  - Stoppe la propagation pour ne pas déclencher
    d'autres événements sur la ligne parente
  - Bascule la classe .expanded sur la .code-row
  - CSS affiche/cache le .code-detail suivant via
    le sélecteur adjacent : .code-row.expanded + .code-detail
───────────────────────────────────────────────── */
function toggleDetail(e, arrow) {
    e.stopPropagation();
    const row = arrow.closest('.code-row');
    row.classList.toggle('expanded');
}

/* Ouvrir l'édition du panneau détail */
function startDetailEdit(e, btn) {
    e.stopPropagation();
    const panel    = btn.closest('.code-detail');
    const textSpan = panel.querySelector('.detail-text');
    const input    = panel.querySelector('.detail-input');
    input.value = textSpan.textContent.replace('📌 ', '').trim();
    panel.classList.add('editing-detail');
    input.focus();
    input.select();
}

/* Sauvegarder le contenu du panneau détail */
function saveDetail(e, btn) {
    e.stopPropagation();
    const panel    = btn.closest('.code-detail');
    const textSpan = panel.querySelector('.detail-text');
    const input    = panel.querySelector('.detail-input');
    const newVal   = input.value.trim();
    if (newVal) {
        textSpan.textContent = '📌 ' + newVal;
    }
    panel.classList.remove('editing-detail');
}

/* Raccourcis clavier dans le champ détail : Entrée = sauv., Échap = annuler */
function handleDetailKey(e, input) {
    if (e.key === 'Enter') {
        saveDetail(e, input);
    } else if (e.key === 'Escape') {
        e.stopPropagation();
        input.closest('.code-detail').classList.remove('editing-detail');
    }
}


/* ─────────────────────────────────────────────────
   2a. DÉMARRER LE MODE ÉDITION — startEdit(e, btn)
   Appelée quand l'utilisateur clique sur ✏️.
   Paramètres :
     e   → l'événement clic (pour stopPropagation)
     btn → le bouton ✏️ cliqué (pour retrouver la ligne)

   Actions :
   1. Stoppe la propagation → évite de toggler la ligne
   2. Récupère la description actuelle du span
   3. Pré-remplit le champ input avec cette valeur
   4. Ajoute .editing et .open à la ligne
      (.editing → CSS cache desc/flèche/crayon, affiche input/sauv.)
   5. Focus + sélection du texte pour édition rapide
───────────────────────────────────────────────── */
function startEdit(e, btn) {
    e.stopPropagation(); // empêche le clic de toggler la ligne parente

    const row      = btn.closest('.code-row');    // remonter au conteneur parent
    const descSpan = row.querySelector('.code-desc');
    const input    = row.querySelector('.code-edit-input');

    // Pré-remplir avec la description actuellement affichée
    input.value = descSpan.textContent.trim();

    // Activer le mode édition via la classe CSS
    row.classList.add('editing', 'open');

    // Mettre le curseur dans le champ et tout sélectionner
    input.focus();
    input.select();
}


/* ─────────────────────────────────────────────────
   2b. SAUVEGARDER L'ÉDITION — saveEdit(e, btn)
   Appelée au clic sur "✓ Sauv." ou via Entrée.
   Paramètres :
     e   → événement (pour stopPropagation)
     btn → bouton sauvegarder ou input (pour retrouver la ligne)

   Actions :
   1. Stoppe la propagation
   2. Lit et nettoie la nouvelle valeur de l'input
   3. Si non vide : met à jour le .textContent du span
      ET l'attribut data-desc (utilisé par la recherche)
   4. Retire .editing pour quitter le mode édition
───────────────────────────────────────────────── */
function saveEdit(e, btn) {
    e.stopPropagation(); // empêche de toggler la ligne

    const row      = btn.closest('.code-row');
    const descSpan = row.querySelector('.code-desc');
    const input    = row.querySelector('.code-edit-input');
    const newVal   = input.value.trim();

    if (newVal) {
        descSpan.textContent = newVal; // mise à jour visuelle du span
        row.dataset.desc     = newVal; // mise à jour pour la recherche JS
    }

    // Quitter le mode édition (CSS masque input, réaffiche desc/flèche/crayon)
    row.classList.remove('editing');
}


/* ─────────────────────────────────────────────────
   2c. RACCOURCIS CLAVIER — handleEditKey(e, input)
   Écoute les touches dans le champ d'édition.
     Entrée → appelle saveEdit pour sauvegarder
     Échap  → annule sans modifier (retire juste .editing)
───────────────────────────────────────────────── */
function handleEditKey(e, input) {
    if (e.key === 'Enter') {
        saveEdit(e, input); // sauvegarder et quitter le mode édition
    } else if (e.key === 'Escape') {
        e.stopPropagation();
        input.closest('.code-row').classList.remove('editing'); // annuler
    }
}


/* ─────────────────────────────────────────────────
   3. RECHERCHE EN TEMPS RÉEL — searchFunction()
   Appelée à chaque frappe dans #searchInput (oninput).

   Algorithme :
   Pour chaque .category-card :
     Pour chaque .code-row de la carte :
       - Comparer le filtre avec data-code ET data-desc
       - Si correspondance → visible + .highlight
         (CSS ouvre automatiquement la description via .highlight)
       - Sinon → .hidden (masqué)
     Si aucune ligne visible dans la carte → masquer la carte
   Si aucune ligne visible au total → afficher #noResults

   Cas particulier : filtre vide → tout réafficher + fermer
   les lignes ouvertes (retrait de .open et .highlight).
───────────────────────────────────────────────── */
function searchFunction() {
    const filter = document.getElementById("searchInput").value.toLowerCase().trim();
    const cards  = document.querySelectorAll(".category-card");
    let totalVisible = 0;

    cards.forEach(card => {
        const rows       = card.querySelectorAll(".code-row");
        // Lire le texte du bandeau titre (ex: "Grammaire (G)")
        const headerText = card.querySelector(".card-header").textContent.toLowerCase();

        // Si le filtre correspond au titre de la catégorie → afficher TOUTES les lignes
        const headerMatch = filter !== "" && headerText.includes(filter);

        let cardVisible = 0;

        rows.forEach(row => {
            const code = row.dataset.code.toLowerCase();
            const desc = row.dataset.desc.toLowerCase();

            // Match si : filtre vide, header correspond, code correspond, ou desc correspond
            const match = filter === "" || headerMatch || code.includes(filter) || desc.includes(filter);

            if (match) {
                row.classList.remove("hidden");

                if (filter !== "" && !headerMatch) {
                    // Surbrillance jaune seulement si c'est code/desc qui a matché
                    row.classList.add("highlight");
                } else {
                    // Pas de surbrillance si c'est le header ou filtre vide
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
        totalVisible === 0 ? "block" : "none";
}
