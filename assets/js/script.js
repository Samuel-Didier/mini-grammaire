/* =========================================
   DONNÉES : ASTUCES
   ========================================= */
import { getAstuces } from "./apiAstuces.js";
const tips = await getAstuces();
// const tips = [
//     {
//         titre: "Distinction a / à",
//         description: "'a' sans accent est le verbe avoir (il a). 'à' avec accent est une préposition (je vais à Paris)."
//     },
//     {
//         titre: "Orthographe : Cauchemar",
//         description: "On écrit 'cauchemar' sans 'd' à la fin, même si on dit 'cauchemardesque'."
//     },
//     {
//         titre: "Parmi vs Hormis",
//         description: "'Parmi' ne prend jamais de 's', contrairement à 'hormis'."
//     },
//     {
//         titre: "Mourir vs Nourrir",
//         description: "Le verbe 'mourir' ne prend qu'un seul 'r', mais 'nourrir' en prend deux. On meurt une fois, on se nourrit plusieurs fois."
//     },
//     {
//         titre: "Règle des 'l' : Appeler",
//         description: "'Appeler' prend deux 'l' devant un 'e' muet (j'appelle), mais un seul sinon (nous appelons)."
//     },
//     {
//         titre: "Invariabilité de Mille",
//         description: "'Mille' est invariable, sauf s'il s'agit de l'unité de mesure (des milles marins)."
//     },
//     {
//         titre: "Quelque ou Quel que",
//         description: "'Quelque' s'écrit en un seul mot devant un nom (quelque temps) et en deux mots devant un verbe (quel que soit)."
//     },
//     {
//         titre: "Davantage vs D'avantage",
//         description: "'Davantage' (plus de) s'écrit en un mot. 'D'avantage' (profit, bénéfice) s'écrit en deux mots (ex: Je n'y vois pas d'avantage)."
//     },
//     {
//         titre: "Objectifs concrets",
//         description: "Fixez des objectifs mesurables — par exemple, 5 nouveaux mots par semaine (plus de 250 par an)."
//     },
//     {
//         titre: "Tableau de suivi",
//         description: "Tenez un suivi hebdomadaire : nouveaux mots, grammaire, heures d'écoute et pratique orale."
//     },
//     {
//         titre: "Immersion numérique",
//         description: "Passez la langue de vos appareils (téléphone, ordinateur) en français."
//     },
//     {
//         titre: "Consommation média",
//         description: "Regardez des séries ou films sur Netflix avec des sous-titres en français."
//     },
//     {
//         titre: "Écoute active",
//         description: "Écoutez des podcasts comme RFI Français Facile pour la compréhension."
//     },
//     {
//         titre: "Étiquetage",
//         description: "Étiquetez les objets de votre maison avec leur nom en français."
//     },
//     {
//         titre: "Shadowing",
//         description: "Répétez à haute voix des phrases entendues dans des podcasts ou vidéos."
//     },
//     {
//         titre: "Phonétique spécifique",
//         description: "Concentrez-vous sur les sons : voyelles nasales (on, an, in), le 'r' guttural et les liaisons."
//     },
//     {
//         titre: "Auto-correction",
//         description: "Enregistrez-vous et comparez votre prononciation à celle d'un locuteur natif."
//     },
//     {
//         titre: "Flashcards",
//         description: "Utilisez des outils comme Anki ou Quizlet pour mémoriser les mots fréquents."
//     },
//     {
//         titre: "Loi de Pareto (80/20)",
//         description: "Apprenez d'abord les 2000 mots les plus courants — ils couvrent 80% de la langue."
//     },
//     {
//         titre: "Journal de bord",
//         description: "Rédigez un journal quotidien en français, même avec des phrases très simples."
//     },
//     {
//         titre: "Structure simple",
//         description: "Structurez vos phrases avec la formule de base : Sujet – Verbe – Complément."
//     },
//     {
//         titre: "Vérification",
//         description: "Relisez-vous systématiquement et utilisez un conjugueur en ligne."
//     },
//     {
//         titre: "Dictées",
//         description: "Entraînez-vous avec des dictées en ligne pour améliorer votre orthographe."
//     },
//     {
//         titre: "Simulation IA",
//         description: "Utilisez ChatGPT pour simuler des conversations réelles en français."
//     },
//     {
//         titre: "État d'esprit",
//         description: "Acceptez de faire des erreurs — c'est une étape obligatoire pour progresser."
//     }
// ];

/* =========================================
   FONCTION : GÉNÉRER UNE NOUVELLE ASTUCE
   ========================================= */
async function newTip() {
    const tips = await getAstuces();

    const tipElement = document.getElementById('tip-text');
    const tipTitle = document.querySelector('.tip-content h3');
    const refreshBtn = document.querySelector('.refresh-tip');

    // Vérifier si l'élément existe sur la page actuelle
    if (!tipElement) return;

    // Animation du bouton de rafraîchissement
    if (refreshBtn) {
        refreshBtn.style.transform = "rotate(360deg)";
        setTimeout(() => {
            refreshBtn.style.transform = "rotate(0deg)";
        }, 300);
    }

    // Animation de fondu du texte
    tipElement.style.opacity = 0;

    setTimeout(() => {
        const randomIndex = Math.floor(Math.random() * tips.length);
        tipTitle.textContent = `💡 Astuce du jour : ${tips[randomIndex].titre}`;
        tipElement.textContent = tips[randomIndex].description;
        tipElement.style.opacity = 1;
        console.log(tips[randomIndex])
    }, 200);
}

newTip();

window.newTip = newTip;
window.getAstuces = getAstuces;

// document.addEventListener('DOMContentLoaded', () => {
//     const bouton = document.getElementById('btn-refresh-tip');
//     if (bouton) {
//         bouton.addEventListener('click', newTip);
//     }
// });

/* =========================================
   FONCTION : RECHERCHE DANS LE TABLEAU
   ========================================= */
// function searchFunction() {
//     // Déclaration des variables
//     var input, filter, table, tr, td, i, j, txtValue, rowMatch;
//
//     input = document.getElementById("searchInput");
//     // Si pas d'input de recherche sur cette page, on arrête
//     if (!input) return;
//
//     filter = input.value.toUpperCase();
//     table = document.getElementById("infoTable");
//     tr = table.getElementsByTagName("tr");
//
//     // Boucle à travers toutes les lignes du tableau (sauf l'en-tête)
//     for (i = 0; i < tr.length; i++) {
//         // Récupère toutes les cellules de la ligne
//         td = tr[i].getElementsByTagName("td");
//
//         // Si la ligne contient des données (n'est pas un en-tête)
//         if (td.length > 0) {
//             rowMatch = false;
//
//             // Vérifie chaque cellule de la ligne
//             for (j = 0; j < td.length; j++) {
//                 if (td[j]) {
//                     txtValue = td[j].textContent || td[j].innerText;
//                     // Si le texte est trouvé dans une des cellules
//                     if (txtValue.toUpperCase().indexOf(filter) > -1) {
//                         rowMatch = true;
//                         break; // Trouvé ! Pas besoin de vérifier le reste de la ligne
//                     }
//                 }
//             }
//
//             // Affiche ou cache la ligne
//             if (rowMatch) {
//                 tr[i].style.display = "";
//             } else {
//                 tr[i].style.display = "none";
//             }
//         }
//     }
// }

/* =========================================
   INITIALISATION AU CHARGEMENT
   ========================================= */
// document.addEventListener('DOMContentLoaded', () => {
//     // Charge une astuce si on est sur la page d'accueil
//     newTip();
// });

