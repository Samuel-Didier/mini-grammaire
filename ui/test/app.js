// Récupération asynchrone du fichier JSON
async function loadRules() {
    try {
        const response = await fetch('data.json');
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        const rulesData = await response.json();
        renderRules(rulesData);
    } catch (error) {
        console.error('Impossible de charger les règles :', error);
        document.getElementById('rules-container').innerHTML =
            '<p class="error-msg">Erreur lors du chargement des données.</p>';
    }
}

// Génération dynamique du DOM
function renderRules(data) {
    const container = document.getElementById('rules-container');
    container.innerHTML = '';

    data.forEach(item => {
        const card = document.createElement('article');
        card.className = 'rule-card';

        let sublistHTML = '';
        if (item.sublist && item.sublist.length > 0) {
            sublistHTML = `
        <ul class="rule-sublist">
          ${item.sublist.map(sub => `<li>${sub}</li>`).join('')}
        </ul>
      `;
        }

        let examplesHTML = '';
        if (item.exemples && item.exemples.length > 0) {
            examplesHTML = item.exemples.map(ex => `
        <div class="example-item incorrect">
          <span class="example-mark">✗</span>
          <div class="example-text">${ex.incorrect}</div>
        </div>
        <div class="example-item correct">
          <span class="example-mark">✓</span>
          <div class="example-text">${ex.correct}</div>
        </div>
      `).join('');
        }

        card.innerHTML = `
      <header class="rule-header">
        <span class="rule-code">${item.code}</span>
        <h2 class="rule-title">${item.titre}</h2>
      </header>
      <div class="rule-body">
        ${item.regle ? `<p class="rule-text">${item.regle}</p>` : ''}
        ${sublistHTML}
        ${item.astuce ? `<div class="rule-tip"><strong>N. B. :</strong> ${item.astuce}</div>` : ''}
        <div class="examples-list">
          ${examplesHTML}
        </div>
      </div>
    `;

        container.appendChild(card);
    });
}

document.addEventListener('DOMContentLoaded', loadRules);