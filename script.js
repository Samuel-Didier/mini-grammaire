function searchFunction() {
    // Déclaration des variables
    var input, filter, table, tr, td, i, j, txtValue, rowMatch;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("infoTable");
    tr = table.getElementsByTagName("tr");

    // Boucle à travers toutes les lignes du tableau
    for (i = 0; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td");
        
        // Si la ligne contient des cellules de données (td)
        if (td.length > 0) {
            rowMatch = false;
            // Boucle à travers toutes les cellules de la ligne
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    // Si une des cellules correspond à la recherche
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        rowMatch = true;
                        break; // Pas besoin de vérifier les autres cellules de cette ligne
                    }
                }
            }
            
            // Afficher ou masquer la ligne en fonction du résultat
            if (rowMatch) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}