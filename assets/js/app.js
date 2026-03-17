/**
 * KawaiiEmoji — Main Application JavaScript
 * Handles: copy-to-clipboard, search, category filters, tags input,
 * live preview, toasts, password toggle, back-to-top button
 */

document.addEventListener('DOMContentLoaded', () => {
    initPageTransitions();
    initCopyButtons();
    initLikeButtons();
    initCategoryTabs();
    initSearchBar();
    initPasswordToggle();
    initBackToTop();
    initTagsInput();
    initLivePreview();
    initCharCounters();
    initScrollReveal();
    revealCards(document.querySelectorAll('.emoji-card'));
});

/* ========== Page Transitions ========== */

function initPageTransitions() {
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (!link) return;

        const href = link.getAttribute('href');
        // Skip external, hash, javascript, and new-tab links
        if (!href || href.startsWith('#') || href.startsWith('javascript') ||
            href.startsWith('http') || link.target === '_blank') return;

        e.preventDefault();
        document.body.classList.add('page-exit');
        setTimeout(() => { window.location.href = href; }, 250);
    });
}

/* ========== Staggered Card Reveal ========== */

function revealCards(cards) {
    cards.forEach((card, i) => {
        setTimeout(() => card.classList.add('card-visible'), i * 50);
    });
}

/* ========== Scroll Reveal ========== */

function initScrollReveal() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('card-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.emoji-card').forEach(card => observer.observe(card));
}

/* ========== Toast Notifications ========== */

function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'toastOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/* ========== Copy to Clipboard ========== */

function initCopyButtons() {
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const symbol = btn.dataset.symbol;
            const emojiId = btn.dataset.id;
            if (!symbol) return;

            const doCopy = () => {
                const original = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.classList.add('btn-primary');
                setTimeout(() => {
                    btn.textContent = original;
                    btn.classList.remove('btn-primary');
                }, 1500);

                // Track unique copy per device via localStorage
                if (emojiId) {
                    const key = 'copied_emojis';
                    const copied = JSON.parse(localStorage.getItem(key) || '[]');
                    if (!copied.includes(emojiId)) {
                        copied.push(emojiId);
                        localStorage.setItem(key, JSON.stringify(copied));
                        // Increment server-side download counter
                        fetch('/api/emojis.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'copy', id: emojiId })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Update all copy count labels for this emoji on the page
                                document.querySelectorAll(`.copy-count[data-id="${emojiId}"]`).forEach(el => {
                                    el.textContent = data.downloads;
                                });
                            }
                        })
                        .catch(() => {});
                    }
                }
            };

            navigator.clipboard.writeText(symbol).then(doCopy).catch(() => {
                const textarea = document.createElement('textarea');
                textarea.value = symbol;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                doCopy();
            });
        });
    });
}

/* ========== Like Buttons ========== */

function initLikeButtons() {
    document.querySelectorAll('.btn-like').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const emojiId = btn.dataset.id;
            if (!emojiId) return;

            btn.style.transform = 'scale(1.3)';
            setTimeout(() => btn.style.transform = 'scale(1)', 200);

            fetch('/api/emojis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'like', id: emojiId })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.auth === false) {
                        showToast('Sign in to like emojis', 'error');
                        setTimeout(() => { window.location.href = '/login.php'; }, 1500);
                        return;
                    }
                    if (data.success) {
                        const countEl = btn.querySelector('.like-count');
                        if (countEl) {
                            countEl.textContent = data.likes;
                        }
                        if (data.liked) {
                            btn.classList.add('liked');
                            btn.querySelector('.like-icon').textContent = '♥';
                        } else {
                            btn.classList.remove('liked');
                            btn.querySelector('.like-icon').textContent = '♡';
                        }
                    }
                })
                .catch(() => { });
        });
    });
}

/* ========== Category Filter Tabs ========== */

function initCategoryTabs() {
    const tabs = document.querySelectorAll('.category-tab');
    const grid = document.querySelector('.gallery-grid');
    if (!tabs.length || !grid) return;

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const category = tab.dataset.category || '';
            loadEmojis({ category });
        });
    });
}

/* ========== Search Bar ========== */

function initSearchBar() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;

    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const query = searchInput.value.trim();
            loadEmojis({ q: query });
        }, 300);
    });
}

/* ========== Load Emojis (AJAX) ========== */

function loadEmojis(params = {}) {
    const grid = document.querySelector('.gallery-grid');
    if (!grid) return;

    // Show skeleton loader
    grid.innerHTML = '';
    for (let i = 0; i < 10; i++) {
        grid.innerHTML += `
            <div class="skeleton-card">
                <div class="skeleton-preview"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line short"></div>
            </div>
        `;
    }

    const queryString = new URLSearchParams(params).toString();
    fetch(`/api/search.php?${queryString}`)
        .then(res => res.json())
        .then(data => {
            grid.innerHTML = '';
            if (data.emojis && data.emojis.length > 0) {
                data.emojis.forEach(emoji => {
                    grid.innerHTML += renderEmojiCard(emoji);
                });
                // Re-init interactive buttons
                initCopyButtons();
                initLikeButtons();
                revealCards(grid.querySelectorAll('.emoji-card'));
            } else {
                grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#9CA3AF;padding:40px;">No emojis found :(</p>';
            }

            // Update count
            const countEl = document.querySelector('.gallery-count');
            if (countEl && data.total !== undefined) {
                countEl.textContent = `Showing: ${data.total} emojis`;
            }
        })
        .catch(() => {
            grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#F87171;padding:40px;">Failed to load emojis</p>';
        });
}

function renderEmojiCard(emoji) {
    const author = emoji.is_anonymous ? 'Anonymous' : `@${emoji.username || 'unknown'}`;
    const liked = emoji.user_liked ? true : false;
    const likedClass = liked ? ' liked' : '';
    const heartIcon = liked ? '♥' : '♡';
    return `
        <div class="emoji-card" onclick="window.location='/emoji.php?id=${emoji.id}'">
            <div class="emoji-symbol">${escapeHtml(emoji.symbol)}</div>
            <div class="emoji-name">${escapeHtml(emoji.name)}</div>
            <div class="emoji-meta">
                <span>${author}</span>
                <span>📋 <span class="copy-count" data-id="${emoji.id}">${emoji.downloads || 0}</span></span>
            </div>
            <div class="card-actions">
                <button class="btn btn-secondary btn-sm btn-copy" data-symbol="${escapeAttr(emoji.symbol)}" data-id="${emoji.id}" onclick="event.stopPropagation()">📋 Copy</button>
                <button class="btn btn-ghost btn-sm btn-like${likedClass}" data-id="${emoji.id}" onclick="event.stopPropagation()">
                    <span class="like-icon">${heartIcon}</span> <span class="like-count">${emoji.likes || 0}</span>
                </button>
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeAttr(text) {
    return text.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* ========== Password Toggle ========== */

function initPasswordToggle() {
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        });
    });
}

/* ========== Back to Top ========== */

function initBackToTop() {
    const btn = document.querySelector('.back-to-top');
    if (!btn) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 400) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    });

    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

/* ========== Tags Input ========== */

function initTagsInput() {
    const container = document.querySelector('.tags-container');
    const hiddenInput = document.getElementById('tags-hidden');
    const input = container ? container.querySelector('.tags-input') : null;
    if (!container || !input) return;

    const maxTags = 10;
    let tags = [];

    // Load existing tags (for edit mode)
    if (hiddenInput && hiddenInput.value) {
        tags = hiddenInput.value.split(',').filter(t => t.trim());
        tags.forEach(tag => addTagPill(tag, container, input));
    }

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = input.value.trim();
            if (val && tags.length < maxTags && !tags.includes(val)) {
                tags.push(val);
                addTagPill(val, container, input);
                if (hiddenInput) hiddenInput.value = tags.join(',');
                input.value = '';
            }
        }

        if (e.key === 'Backspace' && input.value === '' && tags.length > 0) {
            const removed = tags.pop();
            const pills = container.querySelectorAll('.tag-pill');
            if (pills.length) pills[pills.length - 1].remove();
            if (hiddenInput) hiddenInput.value = tags.join(',');
        }
    });

    container.addEventListener('click', () => input.focus());

    function addTagPill(text, container, input) {
        const pill = document.createElement('span');
        pill.className = 'tag-pill';
        pill.innerHTML = `${escapeHtml(text)} <button class="tag-remove" type="button">✕</button>`;

        pill.querySelector('.tag-remove').addEventListener('click', () => {
            tags = tags.filter(t => t !== text);
            pill.remove();
            if (hiddenInput) hiddenInput.value = tags.join(',');
        });

        container.insertBefore(pill, input);
    }
}

/* ========== Live Preview (Upload Page) ========== */

function initLivePreview() {
    const textarea = document.getElementById('emoji-symbol');
    const preview = document.querySelector('.live-preview');
    if (!textarea || !preview) return;

    textarea.addEventListener('input', () => {
        const val = textarea.value.trim();
        preview.textContent = val || '( ? )';
    });
}

/* ========== Character Counters ========== */

function initCharCounters() {
    document.querySelectorAll('[data-maxlength]').forEach(input => {
        const max = parseInt(input.dataset.maxlength);
        const counter = input.parentElement.parentElement.querySelector('.char-counter');
        if (!counter) return;

        const update = () => {
            counter.textContent = `${input.value.length} / ${max}`;
            if (input.value.length > max) {
                counter.style.color = '#F87171';
            } else {
                counter.style.color = '';
            }
        };

        input.addEventListener('input', update);
        update();
    });
}

/* ========== Sort Dropdown ========== */

const sortSelect = document.getElementById('sort-select');
if (sortSelect) {
    sortSelect.addEventListener('change', () => {
        const activeTab = document.querySelector('.category-tab.active');
        const category = activeTab ? activeTab.dataset.category || '' : '';
        const searchInput = document.getElementById('search-input');
        const q = searchInput ? searchInput.value.trim() : '';
        loadEmojis({ category, q, sort: sortSelect.value });
    });
}
