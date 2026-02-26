// Quiz Data
const levelTestQuestions = [
    // A1 Level (Questions 1-2)
    {
        level: 'a1',
        question: "Comment dit-on 'Hello' en français ?",
        options: ["Bonjour", "Au revoir", "Merci", "Bonsoir"],
        correct: 0
    },
    {
        level: 'a1',
        question: "Complètez : 'Je ___ français.'",
        options: ["suis", "es", "sommes", "êtes"],
        correct: 0
    },
    // A2 Level (Questions 3-4)
    {
        level: 'a2',
        question: "Quel temps fait-il ? 'Il ___ beaucoup.'",
        options: ["fait", "fait beau", "pleut", "y a"],
        correct: 2
    },
    {
        level: 'a2',
        question: "Complétez : 'Nous ___ aller au cinéma ce soir.'",
        options: ["allons", "aller", "allez", "va"],
        correct: 0
    },
    // B1 Level (Questions 5-6)
    {
        level: 'b1',
        question: "Complétez : 'Si tu ___ riche, tu voyagerais.'",
        options: ["es", "serais", "étais", "seras"],
        correct: 2
    },
    {
        level: 'b1',
        question: "Quel est le participe passé de 'finir' ?",
        options: ["finissant", "fini", "finie", "finirez"],
        correct: 1
    },
    // B2 Level (Questions 7-8)
    {
        level: 'b2',
        question: "Choisissez le subjonctif : 'Il faut que tu ___ ton travail.'",
        options: ["fais", "fasses", "fait", "faire"],
        correct: 1
    },
    {
        level: 'b2',
        question: "Identifiez le mode verbal : 'Pourvu qu'il vienne!'",
        options: ["Indicatif", "Subjonctif", "Conditionnel", "Impératif"],
        correct: 1
    },
    // C1 Level (Questions 9-10)
    {
        level: 'c1',
        question: "Identifiez la figure de style : 'La nuit, les chats sont gris.'",
        options: ["Métaphore", "Personnification", "Oxymore", "Euphémisme"],
        correct: 2
    },
    {
        level: 'c1',
        question: "Choisissez le bon registre : 'Je t'aime' (registre littéraire)",
        options: ["Je t'affectionne", "Je t'adore", "Mon cœur palpite pour toi", "Je te suis attaché"],
        correct: 2
    }
];

// State
let currentQuestion = 0;
let answers = [];
let startTime = null;

// DOM Elements
const levelTest = document.getElementById('level-test');
const questionsContainer = document.getElementById('questions-container');
const progressFill = document.getElementById('progress-fill');
const progressText = document.getElementById('progress-text');
const nextBtn = document.getElementById('next-btn');
const results = document.getElementById('results');

// Initialize
function initQuiz() {
    if (!levelTest) return; // Ne rien faire si on n'est pas sur la page du quiz

    startTime = Date.now();
    answers = new Array(levelTestQuestions.length).fill(null);
    showQuestion();

    nextBtn.addEventListener('click', () => {
        if (currentQuestion < levelTestQuestions.length - 1) {
            currentQuestion++;
            showQuestion();
            nextBtn.disabled = true;
        } else {
            showResults();
        }
    });
}

// Show current question
function showQuestion() {
    const q = levelTestQuestions[currentQuestion];
    const letters = ['A', 'B', 'C', 'D'];

    let html = `
        <div class="question-container active">
            <div class="question-number">Question ${currentQuestion + 1}</div>
            <div class="question-text">${q.question}</div>
            <div class="options">
    `;

    q.options.forEach((option, index) => {
        html += `
            <div class="option" data-index="${index}" onclick="selectOption(${index})">
                <span class="option-letter">${letters[index]}</span>
                <span>${option}</span>
            </div>
        `;
    });

    html += `</div></div>`;
    questionsContainer.innerHTML = html;
    updateProgress();
}

// Update progress bar
function updateProgress() {
    const progress = ((currentQuestion + 1) / levelTestQuestions.length) * 100;
    progressFill.style.width = `${progress}%`;
    progressText.textContent = `Question ${currentQuestion + 1} sur ${levelTestQuestions.length}`;
}

// Select option
function selectOption(index) {
    const options = document.querySelectorAll('.option');
    options.forEach(opt => opt.classList.remove('selected'));
    options[index].classList.add('selected');
    nextBtn.disabled = false;
    answers[currentQuestion] = index;
}

// Show results and save to DB
function showResults() {
    const endTime = Date.now();
    const timeTaken = Math.round((endTime - startTime) / 1000);

    let correctCount = 0;
    let levelScores = { a1: 0, a2: 0, b1: 0, b2: 0, c1: 0 };

    levelTestQuestions.forEach((q, i) => {
        if (answers[i] === q.correct) {
            correctCount++;
            levelScores[q.level]++;
        }
    });

    // Determine level
    let determinedLevel = 'A1';
    const maxScore = Math.max(...Object.values(levelScores));

    if (levelScores.c1 >= 2 && maxScore === levelScores.c1) determinedLevel = 'C1';
    else if (levelScores.b2 >= 2 && maxScore >= levelScores.b2) determinedLevel = 'B2';
    else if (levelScores.b1 >= 2 && maxScore >= levelScores.b1) determinedLevel = 'B1';
    else if (levelScores.a2 >= 2 && maxScore >= levelScores.a2) determinedLevel = 'A2';

    const questions = levelTestQuestions.length;

    // Afficher les résultats à l'utilisateur
    document.getElementById('result-level').textContent = `Niveau ${determinedLevel}`;
    document.getElementById('result-score').textContent = `Tu as obtenu ${correctCount}/${questions}`;
    document.getElementById('total-answered').textContent = questions;
    document.getElementById('correct-count').textContent = correctCount;
    document.getElementById('total-time').textContent = timeTaken;
    document.getElementById('determined-level').textContent = determinedLevel;

    // Modifier le bouton pour enregistrer et continuer
    const resultsButton = document.querySelector('#results .btn-primary');
    resultsButton.textContent = 'Enregistrer et voir les quiz';
    resultsButton.onclick = () => saveAndContinue(determinedLevel, correctCount);

    levelTest.classList.add('hidden');
    results.classList.remove('hidden');
    results.classList.add('active');
}

// Fonction pour sauvegarder et rediriger
function saveAndContinue(level, score) {
    const resultsButton = document.querySelector('#results .btn-primary');
    resultsButton.disabled = true;
    resultsButton.textContent = 'Enregistrement...';

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
            // Redirection vers la page des quiz
            window.location.href = '/quiz';
        } else {
            alert(data.message || 'Une erreur est survenue.');
            resultsButton.disabled = false;
            resultsButton.textContent = 'Enregistrer et voir les quiz';
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur de communication avec le serveur.');
        resultsButton.disabled = false;
        resultsButton.textContent = 'Enregistrer et voir les quiz';
    });
}

// Initialize on load
document.addEventListener('DOMContentLoaded', initQuiz);
