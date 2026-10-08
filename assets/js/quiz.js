/**
 * GESTION DU JEU DES QUIZ
 * Ce script charge les questions depuis l'API (/api/quiz/questions), affiche les
 * propositions, puis calcule le score. Les questions ne sont plus écrites ici :
 * elles sont gérées depuis le tableau de bord (/dashboard/quiz).
 *
 * La page décrit le quiz à jouer avec les attributs data-* de #level-test :
 *   data-categorie : slug de la catégorie (ex : grammaire)
 *   data-niveau    : niveau du quiz (a1, a2, b1, b2, c1, c2 ou tous)
 *   data-test      : 1 pour le test de niveau (le résultat détermine un niveau CECRL)
 */

// ==========================================
// DONNÉES DU QUIZ
// ==========================================

let currentQuestion = 0;
let answers = [];
let startTime = null;
let quizQuestions = [];
let quizTest = false;
let quizLabel = 'ce quiz';

// ==========================================
// ÉLÉMENTS DU DOM
// ==========================================

const levelTest = document.getElementById('level-test');
const questionsContainer = document.getElementById('questions-container');
const progressFill = document.getElementById('progress-fill');
const progressText = document.getElementById('progress-text');
const nextBtn = document.getElementById('next-btn');
const results = document.getElementById('results');
const quizLoading = document.getElementById('quiz-loading');
const quizError = document.getElementById('quiz-error');
const quizErrorMessage = document.getElementById('quiz-error-message');
const quizRecap = document.getElementById('quiz-recap');
const resultsPrimary = document.getElementById('results-primary');

/**
 * Affiche un message à la place du quiz (quiz vide, erreur réseau, non connecté).
 * @param {string} message Message affiché à l'utilisateur.
 */
function showQuizError(message) {
    if (quizLoading) quizLoading.classList.add('hidden');
    if (questionsContainer) questionsContainer.innerHTML = '';
    if (nextBtn) nextBtn.disabled = true;
    if (!quizError) return;
    quizErrorMessage.textContent = message;
    quizError.classList.remove('hidden');
}

/**
 * Demande les questions du quiz à l'API puis lance le jeu.
 */
function loadQuestions() {
    const categorie = levelTest.dataset.categorie;
    const niveau = levelTest.dataset.niveau;
    quizTest = levelTest.dataset.test === '1';

    const url = `/api/quiz/questions?categorie=${encodeURIComponent(categorie)}&niveau=${encodeURIComponent(niveau)}`;

    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(response => response.json()
            .catch(() => ({ success: false, message: 'Réponse illisible du serveur (' + response.status + ').' })))
        .then(data => {
            if (!data.success) {
                showQuizError(data.message || 'Impossible de charger ce quiz.');
                return;
            }
            if (!data.questions || data.questions.length === 0) {
                showQuizError('Ce quiz ne contient aucune question pour le moment.');
                return;
            }

            quizQuestions = data.questions;
            quizLabel = data.quiz || 'ce quiz';
            startQuiz();
        })
        .catch(() => showQuizError('Erreur de communication avec le serveur.'));
}

/**
 * Initialise le jeu une fois les questions chargées.
 */
function startQuiz() {
    if (quizLoading) quizLoading.classList.add('hidden');

    startTime = Date.now();
    answers = new Array(quizQuestions.length).fill(null);
    showQuestion();

    nextBtn.addEventListener('click', () => {
        if (currentQuestion < quizQuestions.length - 1) {
            currentQuestion++;
            showQuestion();
            nextBtn.disabled = true;
        } else {
            showResults();
        }
    });
}

/**
 * Échappe un texte avant de l'injecter en HTML.
 * @param {string} texte Texte à afficher.
 * @returns {string}
 */
function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte == null ? '' : String(texte);
    return div.innerHTML;
}

/**
 * Affiche la question actuelle et ses propositions.
 */
function showQuestion() {
    const q = quizQuestions[currentQuestion];
    const lettres = ['A', 'B', 'C', 'D', 'E', 'F'];

    let html = `
        <div class="question-container active">
            <div class="question-number">Question ${currentQuestion + 1}</div>
            <div class="question-text">${escapeHtml(q.question)}</div>
            <div class="options">
    `;

    q.options.forEach((option, index) => {
        html += `
            <div class="option" data-index="${index}" onclick="selectOption(${index})">
                <span class="option-letter">${lettres[index]}</span>
                <span>${escapeHtml(option)}</span>
            </div>
        `;
    });

    html += `</div></div>`;
    questionsContainer.innerHTML = html;
    updateProgress();
}

/**
 * Met à jour la barre de progression visuelle.
 */
function updateProgress() {
    const total = quizQuestions.length;
    progressFill.style.width = `${((currentQuestion + 1) / total) * 100}%`;
    progressText.textContent = `Question ${currentQuestion + 1} sur ${total}`;
}

/**
 * Gère la sélection d'une option par l'utilisateur.
 * @param {number} index - L'index de l'option choisie.
 */
function selectOption(index) {
    const options = document.querySelectorAll('.option');
    options.forEach(opt => opt.classList.remove('selected'));
    options[index].classList.add('selected');
    nextBtn.disabled = false;
    answers[currentQuestion] = index;
}

/**
 * Détermine le niveau CECRL à partir du nombre de bonnes réponses par niveau.
 * Utilisé uniquement par le test de niveau.
 * @param {object} scores Répartition des bonnes réponses par niveau.
 * @returns {string} Niveau déterminé (ex : 'B1').
 */
function determineLevel(scores) {
    const maxScore = Math.max(...Object.values(scores));
    const niveaux = ['c1', 'b2', 'b1', 'a2'];

    for (const niveau of niveaux) {
        if ((scores[niveau] || 0) >= 2 && maxScore >= scores[niveau]) {
            return niveau.toUpperCase();
        }
    }
    return 'A1';
}

/**
 * Construit le récapitulatif question par question.
 * @param {number} correctCount Nombre total de bonnes réponses.
 * @returns {string} HTML du récapitulatif.
 */
function buildRecap(correctCount) {
    let html = '<div class="recap"><h3>📝 Tes réponses</h3>';

    quizQuestions.forEach((q, i) => {
        const choisi = answers[i];
        const juste = choisi === q.correct;
        const bonne = q.options[q.correct];

        html += `
            <div class="recap-item ${juste ? 'ok' : 'ko'}">
                <div class="recap-question">${i + 1}. ${escapeHtml(q.question)}</div>
                <div class="recap-reponse">Ta réponse : ${choisi === null || choisi === undefined ? 'aucune' : escapeHtml(q.options[choisi])}</div>
                <div class="recap-bonne">Bonne réponse : ${escapeHtml(bonne)}</div>
                ${q.explication ? `<div class="recap-explication">💡 ${escapeHtml(q.explication)}</div>` : ''}
            </div>
        `;
    });

    html += `</div>`;
    return html;
}

/**
 * Calcule les résultats, affiche le résumé et prépare l'envoi au serveur.
 */
function showResults() {
    const endTime = Date.now();
    const timeTaken = Math.round((endTime - startTime) / 1000);

    let correctCount = 0;
    const scores = {};

    quizQuestions.forEach((q, i) => {
        if (answers[i] === q.correct) {
            correctCount++;
            scores[q.level] = (scores[q.level] || 0) + 1;
        }
    });

    const total = quizQuestions.length;
    const niveau = quizTest ? determineLevel(scores) : null;

    document.getElementById('result-score').textContent = `Tu as obtenu ${correctCount}/${total}`;
    document.getElementById('total-answered').textContent = total;
    document.getElementById('correct-count').textContent = correctCount;
    document.getElementById('total-time').textContent = timeTaken;

    if (quizTest) {
        document.getElementById('result-level').textContent = `Niveau ${niveau}`;
        document.getElementById('result-level').classList.remove('hidden');
        document.getElementById('determined-level').textContent = niveau;
        document.getElementById('determined-level-row').classList.remove('hidden');
        resultsPrimary.textContent = 'Enregistrer et voir les quiz';
        resultsPrimary.onclick = () => saveAndContinue(niveau, correctCount);
    } else {
        document.getElementById('result-level').classList.add('hidden');
        document.getElementById('determined-level-row').classList.add('hidden');
        resultsPrimary.textContent = 'Voir les quiz';
        resultsPrimary.onclick = () => { window.location.href = '/quiz'; };
    }

    quizRecap.innerHTML = buildRecap(correctCount);

    levelTest.classList.add('hidden');
    results.classList.remove('hidden');
    results.classList.add('active');
    results.scrollIntoView({ behavior: 'smooth' });
}

/**
 * Envoie le résultat du test de niveau au serveur via AJAX.
 * @param {string} level - Le niveau déterminé (ex : 'B1').
 * @param {number} score - Le nombre de bonnes réponses.
 */
function saveAndContinue(level, score) {
    resultsPrimary.disabled = true;
    resultsPrimary.textContent = 'Enregistrement...';

    fetch('/quiz/save-level', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ level: level, score: score })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/quiz';
        } else {
            alert(data.message || 'Une erreur est survenue.');
            resultsPrimary.disabled = false;
            resultsPrimary.textContent = 'Enregistrer et voir les quiz';
        }
    })
    .catch(() => {
        alert('Erreur de communication avec le serveur.');
        resultsPrimary.disabled = false;
        resultsPrimary.textContent = 'Enregistrer et voir les quiz';
    });
}

// Lancement au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    if (!levelTest) return; // Ne rien faire si on n'est pas sur la page d'un quiz
    loadQuestions();
});
