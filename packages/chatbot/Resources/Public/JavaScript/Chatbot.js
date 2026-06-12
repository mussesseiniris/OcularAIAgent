// const input = document.getElementById('chatbot-input');
// const messages = document.getElementById('chatbot-messages');
// const sendButton = document.getElementById('chatbot-send');
// const url = sendButton.dataset.url;



// sendButton.addEventListener('click', function () {
//     const question = input.value;
//     const questionDiv = document.createElement('p');
//     questionDiv.textContent = question;
//     messages.appendChild(questionDiv);
//     questionDiv.classList.add("user-chat");
//     sendQ();
// })

// async function sendQ() {
//     const response = await fetch(url, {
//         method: "Post",
//         headers: { "Content-Type": "application/x-www-form-urlencoded" },
//         body: new URLSearchParams({
//             "tx_chatbot_chatbot[question]": input.value,
//         }),
//     });
//     const result = await response.json();
//     const AIResultDiv = document.createElement('p');
//     AIResultDiv.textContent = result.answer;
//     messages.appendChild(AIResultDiv);
//     AIResultDiv.classList.add("AI-chat");
// }
(function () {
  const fab = document.getElementById('chatbot-fab');
  const panel = document.getElementById('chatbot-panel');
  const closeBtn = document.getElementById('chatbot-close');
  const input = document.getElementById('chatbot-input');
  const messages = document.getElementById('chatbot-messages');
  const sendBtn = document.getElementById('chatbot-send');
  if (!fab || !panel || !sendBtn) return;

  const url = sendBtn.dataset.url;
  const history = [];

  function setOpen(open) {
    panel.hidden = !open;
    fab.setAttribute('aria-label', open ? 'Close AI Assistant' : 'Open AI Assistant');
    if (open) input.focus();
  }

  fab.addEventListener('click', () => {
    setOpen(panel.hidden);
  });

  closeBtn.addEventListener('click', () => setOpen(false));

  function appendMessage(text, role) {
    const el = document.createElement('div');
    el.className = 'chatbot-msg chatbot-msg--' + role;
    el.textContent = text;
    messages.appendChild(el);
    messages.scrollTop = messages.scrollHeight;
    return el;
  }

  function getFreshToken() {
    return new Promise((resolve, reject) => {
      const container = document.getElementById('cf-turnstile');
      console.log('[Turnstile] container found:', container);
      console.log('[Turnstile] window.turnstile exists:', !!window.turnstile);
      if (!container || !window.turnstile) {
        console.log('[Turnstile] Skipping — resolving empty');
        resolve(''); // No Turnstile available (dev environment)
        return;
      }
      turnstile.reset(container);
      turnstile.execute(container, {
        callback: (token) => {
                console.log('[Turnstile] Token received:', token ? 'yes' : 'empty');
                resolve(token);
            },
            'error-callback': (err) => {
                console.log('[Turnstile] Error:', err);
                reject(new Error('Turnstile verification failed'));
            },
        });
    });
  }

  async function send() {
    const question = input.value.trim();
    if (!question) return;
    appendMessage(question, 'user');
    input.value = '';
    history.push({ role: 'user', content: question });
    const pending = appendMessage('…', 'ai');


    try {
      token = await getFreshToken();
    } catch (e) {
      pending.textContent = 'Bot verification failed. Please refresh and try again.';
      return;
    }



    const body = {
      'tx_chatbot_chatbot[question]': question,
      'tx_chatbot_chatbot[history]': JSON.stringify(history),
    };

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          'tx_chatbot_chatbot[question]': question,
          'tx_chatbot_chatbot[history]': JSON.stringify(history),
          'tx_chatbot_chatbot[turnstileToken]': token,
        })
      });

      // const text = await res.text();
      // console.log('Raw response:', text);
      // const data = JSON.parse(text);

      const data = await res.json();

      if (data.verified) {
        humanVerified = true;
        turnstileToken = null;
      }

      const answer = data.answer || 'Sorry, something went wrong. Please try again.';
      pending.innerHTML = DOMPurify.sanitize(marked.parse(answer));  
      history.push({ role: 'assistant', content: data.answer });

    } catch (e) {
      // console.error('Chatbot error:', e);
      pending.textContent = 'Sorry, something went wrong. Please try again.';
    }
    messages.scrollTop = messages.scrollHeight;
  }

  sendBtn.addEventListener('click', send);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); send(); }
  });

})();