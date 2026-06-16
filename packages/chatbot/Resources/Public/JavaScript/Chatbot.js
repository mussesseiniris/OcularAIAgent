(function () {
  const fab = document.getElementById('chatbot-fab');
  const panel = document.getElementById('chatbot-panel');
  const closeBtn = document.getElementById('chatbot-close');
  const input = document.getElementById('chatbot-input');
  const messages = document.getElementById('chatbot-messages');
  const sendBtn = document.getElementById('chatbot-send');
  if (!fab || !panel || !sendBtn) return;

  const url = sendBtn.dataset.url;

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
    const pending = appendMessage('…', 'ai');


    try {
      token = await getFreshToken();
    } catch (e) {
      pending.textContent = 'Bot verification failed. Please refresh and try again.';
      return;
    }

    try {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',    //include session cookie for server-side chat history
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          'tx_chatbot_chatbot[question]': question,
          'tx_chatbot_chatbot[turnstileToken]': token,
        })
      });

      const data = await res.json();

      if (data.verified) {
        humanVerified = true;
        turnstileToken = null;
      }

      const answer = data.answer || 'Sorry, something went wrong. Please try again.';
      pending.innerHTML = DOMPurify.sanitize(marked.parse(answer));  

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