/**
 * Password Utilities - Vanilla JavaScript
 * Provides password generation and strength checking without jQuery dependencies
 */

// Word list for passphrase generation
const WORD_LIST = [
  'able', 'acid', 'aged', 'also', 'area', 'army', 'away', 'baby', 'back', 'ball',
  'band', 'bank', 'base', 'bath', 'bear', 'beat', 'been', 'beer', 'bell', 'belt',
  'best', 'bill', 'bird', 'blow', 'blue', 'boat', 'body', 'bomb', 'bond', 'bone',
  'book', 'boom', 'born', 'boss', 'both', 'bowl', 'bulk', 'burn', 'bush', 'busy',
  'call', 'calm', 'came', 'camp', 'card', 'care', 'case', 'cash', 'cast', 'cell',
  'chat', 'chip', 'city', 'club', 'coal', 'coat', 'code', 'cold', 'come', 'cook',
  'cool', 'cope', 'copy', 'core', 'cost', 'crew', 'crop', 'dark', 'data', 'date',
  'dawn', 'days', 'dead', 'deal', 'dean', 'dear', 'debt', 'deep', 'deny', 'desk',
  'dial', 'dick', 'diet', 'disc', 'disk', 'does', 'done', 'door', 'dose', 'down',
  'draw', 'drew', 'drop', 'drug', 'dual', 'duke', 'dust', 'duty', 'each', 'earn',
  'ease', 'east', 'easy', 'edge', 'else', 'even', 'ever', 'evil', 'exit', 'face',
  'fact', 'fail', 'fair', 'fall', 'farm', 'fast', 'fate', 'fear', 'feed', 'feel',
  'feet', 'fell', 'felt', 'file', 'fill', 'film', 'find', 'fine', 'fire', 'firm',
  'fish', 'five', 'flat', 'flow', 'folk', 'food', 'foot', 'ford', 'form', 'fort',
  'four', 'free', 'from', 'fuel', 'full', 'fund', 'gain', 'game', 'gate', 'gave',
  'gear', 'gene', 'gift', 'girl', 'give', 'glad', 'glen', 'gold', 'golf', 'gone',
  'good', 'gray', 'grew', 'grey', 'grow', 'gulf', 'hair', 'half', 'hall', 'hand',
  'hang', 'hard', 'harm', 'hate', 'have', 'head', 'hear', 'heat', 'held', 'hell',
  'help', 'here', 'hero', 'high', 'hill', 'hire', 'hold', 'hole', 'holy', 'home',
  'hope', 'host', 'hour', 'huge', 'hung', 'hunt', 'hurt', 'idea', 'inch', 'into',
  'iron', 'item', 'jack', 'jane', 'jean', 'john', 'join', 'jump', 'jury', 'just',
  'keen', 'keep', 'kent', 'kept', 'kick', 'kill', 'kind', 'king', 'knee', 'knew',
  'know', 'lack', 'lady', 'laid', 'lake', 'land', 'lane', 'last', 'late', 'lead',
  'left', 'less', 'life', 'lift', 'like', 'line', 'link', 'list', 'live', 'load',
  'loan', 'lock', 'long', 'look', 'lord', 'lose', 'loss', 'lost', 'love', 'luck',
  'made', 'mail', 'main', 'make', 'male', 'mall', 'many', 'mark', 'mass', 'mate',
  'math', 'meal', 'mean', 'meat', 'meet', 'menu', 'mere', 'mike', 'mile', 'milk',
  'mill', 'mind', 'mine', 'miss', 'mode', 'mood', 'moon', 'more', 'most', 'move',
  'much', 'must', 'name', 'navy', 'near', 'neck', 'need', 'news', 'next', 'nice',
  'nick', 'nine', 'none', 'nose', 'note', 'once', 'only', 'onto', 'open', 'oral',
  'over', 'pace', 'pack', 'page', 'paid', 'pain', 'pair', 'palm', 'park', 'part',
  'pass', 'past', 'path', 'peak', 'pick', 'pink', 'pipe', 'plan', 'play', 'plot',
  'plug', 'plus', 'poem', 'poet', 'poll', 'pool', 'poor', 'port', 'pose', 'post',
  'pour', 'pray', 'pull', 'pure', 'push', 'race', 'rail', 'rain', 'rank', 'rare',
  'rate', 'read', 'real', 'rear', 'rely', 'rent', 'rest', 'rice', 'rich', 'ride',
  'ring', 'rise', 'risk', 'road', 'rock', 'role', 'roll', 'roof', 'room', 'root',
  'rose', 'rule', 'rush', 'ruth', 'safe', 'said', 'sake', 'sale', 'salt', 'same',
  'sand', 'save', 'seat', 'seed', 'seek', 'seem', 'seen', 'self', 'sell', 'send',
  'sent', 'sept', 'ship', 'shop', 'shot', 'show', 'shut', 'sick', 'side', 'sign',
  'sing', 'sink', 'site', 'size', 'skin', 'slip', 'slow', 'snow', 'soft', 'soil',
  'sold', 'sole', 'some', 'song', 'soon', 'sort', 'soul', 'spot', 'star', 'stay',
  'step', 'stop', 'such', 'suit', 'sure', 'take', 'tale', 'talk', 'tall', 'tank',
  'tape', 'task', 'team', 'tech', 'tell', 'tend', 'term', 'test', 'text', 'than',
  'that', 'them', 'then', 'they', 'thin', 'this', 'thus', 'till', 'time', 'tiny',
  'told', 'toll', 'tone', 'tony', 'took', 'tool', 'tour', 'town', 'tree', 'trip',
  'true', 'tune', 'turn', 'twin', 'type', 'unit', 'upon', 'used', 'user', 'vary',
  'vast', 'very', 'vice', 'view', 'vote', 'wage', 'wait', 'wake', 'walk', 'wall',
  'want', 'ward', 'warm', 'wash', 'wave', 'ways', 'weak', 'wear', 'week', 'well',
  'went', 'were', 'west', 'what', 'when', 'whom', 'wide', 'wife', 'wild', 'will',
  'wind', 'wine', 'wing', 'wire', 'wise', 'wish', 'with', 'wood', 'word', 'wore',
  'work', 'worn', 'wrap', 'yard', 'yeah', 'year', 'your', 'zero', 'zone'
];

/**
 * Generate a random passphrase using word list
 * @param {number} wordCount - Number of words to include
 * @param {string} separator - Character to separate words
 * @param {string} passwordFieldId - ID of password input field
 * @param {string} confirmFieldId - ID of confirm input field
 */
function generatePassword(wordCount, separator, passwordFieldId, confirmFieldId) {
  const words = [];
  for (let i = 0; i < wordCount; i++) {
    const randomIndex = Math.floor(Math.random() * WORD_LIST.length);
    // Capitalize first letter of each word
    const word = WORD_LIST[randomIndex];
    words.push(word.charAt(0).toUpperCase() + word.slice(1));
  }

  // Add a random number at the end for extra strength
  const randomNum = Math.floor(Math.random() * 100);
  const password = words.join(separator) + randomNum;

  // Set the password fields
  const passwordField = document.getElementById(passwordFieldId);
  const confirmField = document.getElementById(confirmFieldId);

  if (passwordField) {
    passwordField.value = password;
    passwordField.type = 'text'; // Show password briefly
  }
  if (confirmField) {
    confirmField.value = password;
    confirmField.type = 'text'; // Show password briefly
  }

  // Update strength meter
  updatePasswordStrength(password);

  return password;
}

/**
 * Calculate password strength score (0-4)
 * @param {string} password - Password to check
 * @returns {number} Score from 0 (weak) to 4 (very strong)
 */
function calculatePasswordStrength(password) {
  if (!password) return 0;

  let score = 0;
  const length = password.length;

  // Length score
  if (length >= 8) score++;
  if (length >= 12) score++;
  if (length >= 16) score++;

  // Complexity score
  if (/[a-z]/.test(password)) score++; // lowercase
  if (/[A-Z]/.test(password)) score++; // uppercase
  if (/[0-9]/.test(password)) score++; // numbers
  if (/[^a-zA-Z0-9]/.test(password)) score++; // special chars

  // Penalize common patterns
  if (/^[a-z]+$/.test(password) || /^[A-Z]+$/.test(password) || /^[0-9]+$/.test(password)) {
    score = Math.max(0, score - 2);
  }

  // Sequential characters penalty
  if (/abc|bcd|cde|123|234|345|456|567|678|789/i.test(password)) {
    score = Math.max(0, score - 1);
  }

  // Normalize to 0-4 scale
  if (score >= 7) return 4;
  if (score >= 5) return 3;
  if (score >= 3) return 2;
  if (score >= 1) return 1;
  return 0;
}

/**
 * Update the password strength progress bar
 * @param {string} password - Password to check
 */
function updatePasswordStrength(password) {
  const progressBar = document.getElementById('StrengthProgressBar');
  const passScoreField = document.getElementById('pass_score');

  if (!progressBar) return;

  // If password is empty, hide the progress bar and reset score
  if (!password || password.length === 0) {
    progressBar.style.width = '0%';
    progressBar.textContent = '';
    progressBar.style.backgroundColor = 'transparent';
    if (passScoreField) {
      passScoreField.value = '0';
    }
    return;
  }

  const score = calculatePasswordStrength(password);

  // Update hidden field for form submission
  if (passScoreField) {
    passScoreField.value = score;
  }

  // Color and width mapping
  const colors = ['#d9534f', '#f0ad4e', '#5bc0de', '#5cb85c', '#449d44'];
  const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
  const widths = [20, 40, 60, 80, 100];

  progressBar.style.width = widths[score] + '%';
  progressBar.style.backgroundColor = colors[score];
  progressBar.textContent = labels[score];
  progressBar.setAttribute('aria-valuenow', widths[score]);
}

/**
 * Initialize password strength meter on an input field
 * @param {string} passwordInputId - ID of password input field
 */
function initPasswordStrength(passwordInputId) {
  const passwordInput = document.getElementById(passwordInputId);
  if (!passwordInput) return;

  // Update on input
  passwordInput.addEventListener('input', function() {
    updatePasswordStrength(this.value);
  });

  // Initial check
  updatePasswordStrength(passwordInput.value);
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    generatePassword,
    calculatePasswordStrength,
    updatePasswordStrength,
    initPasswordStrength
  };
}
