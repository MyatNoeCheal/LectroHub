<!-- ==========================================================
     Chatbot widget partial — styled to match LectroHub (light theme)
     Include this with: require_once __DIR__ . '/../chatbot-deployment/static/index.php';
     No <!DOCTYPE>, <html>, <head>, or <body> here — this is a
     fragment meant to be dropped into an existing page's <body>.

     FIX INCLUDED: if it was appearing high up the page instead of
     pinned to the true bottom-right corner, that's almost always
     caused by an ancestor element having `transform`, `filter`,
     `backdrop-filter`, `perspective`, or `will-change: transform`
     set on it — any of those turns that ancestor into the
     containing block for `position: fixed` descendants, so the
     widget ends up "fixed" relative to that box instead of the
     real viewport.

     The script below defensively re-parents the widget to be a
     direct child of <body> on load, so it always escapes whatever
     wrapper it was rendered inside and pins to the actual corner
     of the browser window.
     ========================================================== -->

<style>
    .chatbox-widget,
    .chatbox-widget * {
        box-sizing: border-box;
    }

    .chatbox-widget {
        --lh-bg: #ffffff;
        --lh-bg-2: #f4f6f9;
        --lh-border: #e6eaf0;
        --lh-text: #12161c;
        --lh-muted: #6b7684;
        --lh-dim: #9aa3ae;
        --lh-accent: #00c2d6;
        --lh-accent-2: #00e5ff;
        --lh-glow: rgba(0, 194, 214, .22);

        /* !important here is deliberate: it guards against page CSS
       that might target ".chatbox-widget" (or a generic
       "div { position: relative }" rule) with higher specificity
       and quietly override the fixed positioning. */
        position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    left: auto !important;
    top: auto !important;
    z-index: 999999 !important;
    font-family: 'DM Sans', sans-serif;
    font-weight: 400;
    font-size: 100%;
    pointer-events: none; 
    }

    .chatbox-widget .chatbox__button {
    text-align: right;
    margin-top: 12px;
    pointer-events: auto;   /* ← NEW: launcher button stays clickable */
}

.chatbox-widget .chatbox__support {
    display: flex;
    flex-direction: column;
    background: var(--lh-bg);
    border: 1px solid var(--lh-border);
    width: 350px;
    height: 460px;
    z-index: -123456;
    opacity: 0;
    pointer-events: none;   /* ← FIX: closed panel is invisible but was still catching clicks */
    box-shadow: 0 18px 46px rgba(20, 30, 45, .16), 0 2px 8px rgba(20, 30, 45, .06);
    border-radius: 18px;
    overflow: hidden;
    transform-origin: bottom right;
    transition: opacity .3s ease, transform .3s cubic-bezier(.16, 1, .3, 1);
}

.chatbox-widget .chatbox--active {
    transform: translateY(-14px);
    z-index: 123456;
    opacity: 1;
    pointer-events: auto;   /* ← FIX: only clickable once actually open */
}

    .chatbox-widget .chatbox__button {
        text-align: right;
        margin-top: 12px;
    }

    .chatbox-widget .send__button {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        padding: 0;
        background: linear-gradient(135deg, var(--lh-accent-2), var(--lh-accent));
        border: none;
        border-radius: 50%;
        outline: none;
        cursor: pointer;
        flex-shrink: 0;
        box-shadow: 0 4px 12px var(--lh-glow);
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .chatbox-widget .send__button:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px var(--lh-glow);
    }

    .chatbox-widget .send__button svg {
        width: 16px;
        height: 16px;
    }

    /* ── Header ── */
    .chatbox-widget .chatbox__header {
        position: sticky;
        top: 0;
        background: #ffffff;
        border-bottom: 1px solid var(--lh-border);
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
    }

    .chatbox-widget .chatbox__image--header {
        margin-right: 2px;
        flex-shrink: 0;
    }

    .chatbox-widget .chatbox__image--header img {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        padding: 6px;
        background: rgba(0, 194, 214, .1);
        border: 1px solid rgba(0, 194, 214, .25);
        box-sizing: border-box;
    }

    .chatbox-widget .chatbox__heading--header {
        font-family: 'Syne', sans-serif;
        font-weight: 700;
        font-size: 1rem;
        color: var(--lh-text);
        margin: 0;
        letter-spacing: -.01em;
    }

    .chatbox-widget .chatbox__description--header {
        font-family: 'DM Sans', sans-serif;
        font-size: .78rem;
        color: var(--lh-muted);
        margin: 2px 0 0;
    }

    /* ── Messages ── */
    .chatbox-widget .chatbox__messages {
        margin-top: auto;
        display: flex;
        overflow-y: auto;
        flex-direction: column-reverse;
        padding: 16px 16px 4px;
        background: var(--lh-bg);
        scrollbar-width: thin;
        scrollbar-color: var(--lh-border) transparent;
    }

    .chatbox-widget .chatbox__messages::-webkit-scrollbar {
        width: 6px;
    }

    .chatbox-widget .chatbox__messages::-webkit-scrollbar-thumb {
        background: var(--lh-border);
        border-radius: 10px;
    }

    .chatbox-widget .messages__item {
        margin-top: 10px;
        padding: 9px 13px;
        max-width: 78%;
        width: fit-content;
        font-size: .85rem;
        line-height: 1.45;
        font-family: 'DM Sans', sans-serif;
    }

    /* Bot replies — left aligned, soft grey */
    .chatbox-widget .messages__item--visitor,
    .chatbox-widget .messages__item--typing {
        background: var(--lh-bg-2);
        border: 1px solid var(--lh-border);
        color: var(--lh-text);
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
        border-bottom-right-radius: 16px;
        margin-right: auto;
    }

    /* Your messages — right aligned, cyan gradient */
    .chatbox-widget .messages__item--operator {
        background: linear-gradient(135deg, var(--lh-accent-2), var(--lh-accent));
        color: #ffffff;
        font-weight: 500;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
        border-bottom-left-radius: 16px;
        margin-left: auto;
        box-shadow: 0 4px 12px var(--lh-glow);
    }

    /* ── Footer / input row ── */
    .chatbox-widget .chatbox__footer {
        position: sticky;
        bottom: 0;
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        background: var(--lh-bg-2);
        border-top: 1px solid var(--lh-border);
    }

    .chatbox-widget .chatbox__footer input {
        flex: 1;
        border: 1px solid var(--lh-border);
        background: #ffffff;
        color: var(--lh-text);
        padding: 10px 14px;
        border-radius: 30px;
        text-align: left;
        font-family: 'DM Sans', sans-serif;
        font-size: .85rem;
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .chatbox-widget .chatbox__footer input::placeholder {
        color: var(--lh-dim);
    }

    .chatbox-widget .chatbox__footer input:focus {
        border-color: var(--lh-accent);
        box-shadow: 0 0 0 3px var(--lh-glow);
    }

    /* ── Launcher button ── */
    .chatbox-widget .chatbox__button button,
    .chatbox-widget .chatbox__button button:focus,
    .chatbox-widget .chatbox__button button:visited {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 58px;
        height: 58px;
        padding: 0;
        background: linear-gradient(135deg, var(--lh-accent-2), var(--lh-accent));
        border: none;
        border-radius: 50%;
        outline: none;
        box-shadow: 0 10px 24px var(--lh-glow), 0 2px 6px rgba(20, 30, 45, .15);
        cursor: pointer;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .chatbox-widget .chatbox__button button:hover {
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 14px 28px var(--lh-glow), 0 2px 6px rgba(20, 30, 45, .15);
    }

    .chatbox-widget .chatbox__button button img {
        width: 26px;
        height: 26px;
        filter: brightness(0) invert(1);
    }

.chatbox-widget .messages__products {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    margin-top: 6px;
    padding-bottom: 4px;
}

.chatbox-widget .product-card-mini {
    flex: 0 0 auto;
    width: 110px;
    background: var(--lh-bg);
    border: 1px solid var(--lh-border);
    border-radius: 10px;
    padding: 8px;
    text-decoration: none;
    color: var(--lh-text);
    font-family: 'DM Sans', sans-serif;
}

.chatbox-widget .product-card-mini img {
    width: 100%;
    height: 70px;
    object-fit: cover;
    border-radius: 6px;
    margin-bottom: 6px;
}

.chatbox-widget .product-card-mini__name {
    font-size: .72rem;
    line-height: 1.3;
    margin-bottom: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.chatbox-widget .product-card-mini__price {
    font-size: .76rem;
    font-weight: 600;
    color: var(--lh-accent);
}

</style>

<div class="chatbox-widget" id="lh-chatbox-widget">
    <div class="chatbox__support">
        <div class="chatbox__header">
            <div class="chatbox__image--header">
                <img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/chatbot-icon-svg-download-png-9354662.png?f=webp&w=128" alt="Sam avatar" width="32" height="32">
            </div>
            <div class="chatbox__content--header">
                <h4 class="chatbox__heading--header">LectroHub Support</h4>
                <p class="chatbox__description--header">Hi. My name is Rachel. How can I help you?</p>
            </div>
        </div>
        <div class="chatbox__messages">
            <div></div>
        </div>
        <div class="chatbox__footer">
            <input type="text" placeholder="Write a message...">
            <button class="chatbox__send--footer send__button" aria-label="Send message">
                <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13" />
                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                </svg>
            </button>
        </div>
    </div>
    <div class="chatbox__button">
        <!-- absolute, server-root-relative path so it resolves the same no matter which page includes this partial -->
        <button><img src="https://cdn.iconscout.com/icon/premium/png-256-thumb/chatbot-icon-svg-download-png-9354662.png?f=webp&w=128" alt="Open chat" width="24" height="24"></button>
    </div>
</div>

<script>
    (function() {
        /* ── Re-parent to <body> so nothing upstream (a transformed,
           filtered, or backdrop-filtered wrapper) can hijack this
           element's `position: fixed` and make it "float" relative
           to that wrapper instead of the real browser viewport. ── */
        var widgetRoot = document.getElementById('lh-chatbox-widget');
        if (widgetRoot && widgetRoot.parentElement !== document.body) {
            document.body.appendChild(widgetRoot);
        }

        class Chatbox {
            constructor(root) {
                this.root = root;
                this.args = {
                    openButton: root.querySelector('.chatbox__button'),
                    chatBox: root.querySelector('.chatbox__support'),
                    sendButton: root.querySelector('.send__button')
                };
                this.state = false;
                this.messages = [];
            }

            display() {
                const {
                    openButton,
                    chatBox,
                    sendButton
                } = this.args;

                openButton.addEventListener('click', () => this.toggleState(chatBox));
                sendButton.addEventListener('click', () => this.onSendButton(chatBox));

                const node = chatBox.querySelector('input');
                node.addEventListener('keyup', ({
                    key
                }) => {
                    if (key === 'Enter') {
                        this.onSendButton(chatBox);
                    }
                });
            }

            toggleState(chatbox) {
                this.state = !this.state;
                if (this.state) {
                    chatbox.classList.add('chatbox--active');
                } else {
                    chatbox.classList.remove('chatbox--active');
                }
            }

            onSendButton(chatbox) {
    const textField = chatbox.querySelector('input');
    const text1 = textField.value;
    if (text1 === '') return;

    this.messages.push({ name: 'User', message: text1 });

    fetch('/LectroHub/customer/chatbot_proxy.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: JSON.stringify({
                message: text1
            }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(r => r.json())
        .then(r => {
            this.messages.push({
                name: 'Sam',
                message: r.text,
                products: r.products || null
            });
            this.updateChatText(chatbox);
            textField.value = '';
        })
        .catch((error) => {
            console.error('Chatbot error:', error);
            this.messages.push({ name: 'Rachel', message: 'Sorry, I\'m having trouble connecting right now.' });
            this.updateChatText(chatbox);
            textField.value = '';
        });
}

         updateChatText(chatbox) {
    let html = '';
    this.messages.slice().reverse().forEach((item) => {
        const isBot = item.name === 'Sam';
        const cls = isBot ? 'messages__item--visitor' : 'messages__item--operator';
        html += `<div class="messages__item ${cls}">${item.message}</div>`;

        if (isBot && item.products && item.products.length) {
            html += '<div class="messages__products">';
            item.products.forEach(p => {
                html += `
                    <a class="product-card-mini" href="${p.url}">
                        <img src="${p.image}" alt="${p.name}">
                        <div class="product-card-mini__name">${p.name}</div>
                        <div class="product-card-mini__price">€${p.price.toFixed(2)}</div>
                    </a>`;
            });
            html += '</div>';
        }
    });

    const chatmessage = chatbox.querySelector('.chatbox__messages');
    chatmessage.innerHTML = html;
}
        }

        document.querySelectorAll('.chatbox-widget').forEach((root) => {
            const chatbox = new Chatbox(root);
            chatbox.display();
        });
    })();
</script>