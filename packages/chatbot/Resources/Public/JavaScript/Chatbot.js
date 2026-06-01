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

  function setOpen(open) {
    panel.hidden = !open;
    fab.setAttribute('aria-label', open ? 'Close AI Assistant' : 'Open AI Assistant');
    if (open) input.focus();
  }
  fab.addEventListener('click', () => setOpen(panel.hidden));
  closeBtn.addEventListener('click', () => setOpen(false));

  function appendMessage(text, role) {
    const el = document.createElement('div');
    el.className = 'chatbot-msg chatbot-msg--' + role;
    el.textContent = text;
    messages.appendChild(el);
    messages.scrollTop = messages.scrollHeight;
    return el;
  }

  function formatAnswer(text) {
    return text.replace(/\s*(\d+\.)\s+/g, '\n$1 ').trim();
  }

  async function send() {
    const question = input.value.trim();
    if (!question) return;
    appendMessage(question, 'user');
    input.value = '';

    const pending = appendMessage('…', 'ai');
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'tx_chatbot_chatbot[question]': question }),
      });
      const data = await res.json();
      pending.textContent = formatAnswer(data.answer || 'Sorry, something went wrong. Please try again.');
    } catch (e) {
      pending.textContent = 'Sorry, something went wrong. Please try again.';
    }
    messages.scrollTop = messages.scrollHeight;
  }

  sendBtn.addEventListener('click', send);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); send(); }
  });
})();