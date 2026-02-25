export async function getAstuces() {
    try {
        // 1. Appel de l'URL PHP (ajuste l'adresse selon ton dossier Laragon)
        const response = await fetch('http://mini-grammaire.test/astuces/api/astuces');

        // 2. Conversion du texte JSON en tableau d'objets JS
        const data = await response.json();

        // 3. Utilisation des données (ex: affichage dans la console)
        console.log("Données reçues :", data);

        // Exemple : Accéder au titre du premier élément
        if(data.length > 0) {
            console.log("Premier titre :", data[0].titre);
        }

    } catch (error) {
        console.error("Erreur lors de la récupération :", error);
    }
}

getAstuces();