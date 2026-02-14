/* =========================================
   DONNÉES : ASTUCES
   ========================================= */
const tips = [
    "Astuce : 'a' sans accent est le verbe avoir (il a). 'à' avec accent est une préposition (je vais à Paris).",
    "Astuce : On écrit 'cauchemar' sans 'd' à la fin, même si on dit 'cauchemardesque'.",
    "Astuce : 'Parmi' ne prend jamais de 's', contrairement à 'hormis'.",
    "Astuce : Le verbe 'mourir' ne prend qu'un seul 'r', mais 'nourrir' en prend deux. On meurt une fois, on se nourrit plusieurs fois.",
    "Astuce : 'Appeler' prend deux 'l' devant un 'e' muet (j'appelle), mais un seul sinon (nous appelons).",
    "Astuce : 'Mille' est invariable, sauf s'il s'agit de l'unité de mesure (des milles marins).",
    "Astuce : 'Quelque' s'écrit en un seul mot devant un nom (quelque temps) et en deux mots devant un verbe (quel que soit).",
    "Astuce : 'Davantage' (plus de) s'écrit en un mot. 'D'avantage' (bénéfice) s'écrit en deux mots."
];

/* =========================================
   FONCTION : GÉNÉRER UNE NOUVELLE ASTUCE
   ========================================= */
function newTip() {
    const tipElement = document.getElementById('tip-text');
    const refreshBtn = document.querySelector('.refresh-tip');
    
    // Vérifier si l'élément existe sur la page actuelle
    if (!tipElement) return;

    // Animation du bouton de rafraîchissement
    if(refreshBtn) {
        refreshBtn.style.transform = "rotate(360deg)";
        setTimeout(() => {
            refreshBtn.style.transform = "rotate(0deg)";
        }, 300);
    }

    // Animation de fondu du texte
    tipElement.style.opacity = 0;
    
    setTimeout(() => {
        const randomIndex = Math.floor(Math.random() * tips.length);
        tipElement.textContent = tips[randomIndex];
        tipElement.style.opacity = 1;
    }, 200);
}

/* =========================================
   FONCTION : RECHERCHE DANS LE TABLEAU
   ========================================= */
function searchFunction() {
    // Déclaration des variables
    var input, filter, table, tr, td, i, j, txtValue, rowMatch;
    
    input = document.getElementById("searchInput");
    // Si pas d'input de recherche sur cette page, on arrête
    if (!input) return;

    filter = input.value.toUpperCase();
    table = document.getElementById("infoTable");
    tr = table.getElementsByTagName("tr");

    // Boucle à travers toutes les lignes du tableau (sauf l'en-tête)
    for (i = 0; i < tr.length; i++) {
        // Récupère toutes les cellules de la ligne
        td = tr[i].getElementsByTagName("td");
        
        // Si la ligne contient des données (n'est pas un en-tête)
        if (td.length > 0) {
            rowMatch = false;
            
            // Vérifie chaque cellule de la ligne
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    // Si le texte est trouvé dans une des cellules
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        rowMatch = true;
                        break; // Trouvé ! Pas besoin de vérifier le reste de la ligne
                    }
                }
            }
            
            // Affiche ou cache la ligne
            if (rowMatch) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

/* =========================================
   INITIALISATION AU CHARGEMENT
   ========================================= */
document.addEventListener('DOMContentLoaded', () => {
    // Charge une astuce si on est sur la page d'accueil
    newTip();
});