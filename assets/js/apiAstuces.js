export async function getAstuces() {
    try {
        // Appel de la nouvelle route API pour les astuces
        const response = await fetch('/api/astuces');

        // Vérifier si la réponse est OK (statut 200)
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        // Conversion du texte JSON en tableau d'objets JS
        const data = await response.json();

        // Utilisation des données (ex: affichage dans la console)
        console.log("Données reçues de l'API Astuces :", data);

        // Retourne les données pour qu'elles puissent être utilisées par l'appelant
        return data;

    } catch (error) {
        console.error("Erreur lors de la récupération des astuces via l'API :", error);
        return []; // Retourne un tableau vide en cas d'erreur
    }
}