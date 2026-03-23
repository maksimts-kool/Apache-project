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
    initCategoryCreator();
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

                if (emojiId) {
                    // Increment server-side download counter on every successful copy
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
                        .catch(() => { });
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

function initCategoryCreator() {
    const modal = document.getElementById('category-modal');
    const openBtn = document.getElementById('open-category-modal');
    const form = document.getElementById('category-create-form');
    const select = document.getElementById('category');

    if (!modal || !openBtn || !form || !select) return;

    const closeButtons = modal.querySelectorAll('[data-close-category-modal]');
    const emojiInput = document.getElementById('category-emoji');
    const nameInput = document.getElementById('category-name');
    const submitBtn = document.getElementById('create-category-btn');
    const emojiOptions = Array.from(modal.querySelectorAll('[data-category-emoji]'));

    let closeTimer = null;

    const setActiveEmoji = (value) => {
        if (emojiInput) {
            emojiInput.value = value;
        }

        emojiOptions.forEach(option => {
            const isActive = option.dataset.categoryEmoji === value;
            option.classList.toggle('active', isActive);
            option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const closeModal = () => {
        modal.classList.remove('visible');
        document.body.classList.remove('modal-open');

        if (closeTimer) clearTimeout(closeTimer);
        closeTimer = setTimeout(() => {
            modal.hidden = true;
            form.reset();
            setActiveEmoji('');
        }, 180);
    };

    const openModal = () => {
        modal.hidden = false;
        document.body.classList.add('modal-open');
        requestAnimationFrame(() => modal.classList.add('visible'));
        if (emojiOptions[0] && !emojiInput.value) {
            setActiveEmoji(emojiOptions[0].dataset.categoryEmoji || '');
        }
        if (nameInput) nameInput.focus();
    };

    emojiOptions.forEach(option => {
        option.addEventListener('click', () => {
            setActiveEmoji(option.dataset.categoryEmoji || '');
        });
    });

    openBtn.addEventListener('click', openModal);

    closeButtons.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const emoji = emojiInput ? emojiInput.value.trim() : '';
        const name = nameInput ? nameInput.value.trim() : '';

        if (!emoji || !name) {
            showToast('Fill in both fields', 'error');
            return;
        }

        const originalText = submitBtn ? submitBtn.textContent : 'Create';
        if (submitBtn) {
            submitBtn.textContent = 'Creating...';
            submitBtn.disabled = true;
        }

        fetch('/api/categories.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ emoji, name })
        })
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.category) {
                    showToast(data.message || 'Failed to create category', 'error');
                    return;
                }

                const existingOption = Array.from(select.options).find(option => option.value === data.category.slug);
                if (!existingOption) {
                    const option = document.createElement('option');
                    option.value = data.category.slug;
                    option.textContent = data.category.label || `${data.category.emoji} ${data.category.name}`;
                    select.appendChild(option);
                }

                select.value = data.category.slug;
                showToast('Category created', 'success');
                closeModal();
            })
            .catch(() => {
                showToast('Failed to create category', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            });
    });
}

/* ========== Search Bar ========== */

function initSearchBar() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;

    // Create suggestions dropdown
    const container = searchInput.parentElement;
    const suggestions = document.createElement('div');
    suggestions.className = 'tag-suggestions';
    container.appendChild(suggestions);

    let debounceTimer;
    let suggestionsTimer;

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();

        // Standard emoji search
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            loadEmojis({ q: query });
        }, 300);

        // Tag suggestions
        clearTimeout(suggestionsTimer);
        if (query.startsWith('#') && query.length > 1) {
            suggestionsTimer = setTimeout(() => {
                fetch(`/api/tags.php?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.tags && data.tags.length > 0) {
                            renderSuggestions(data.tags, suggestions, searchInput);
                        } else {
                            suggestions.style.display = 'none';
                        }
                    });
            }, 200);
        } else {
            suggestions.style.display = 'none';
        }
    });

    // Close suggestions on click outside
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });
}

function renderSuggestions(tags, container, input) {
    container.innerHTML = '';
    tags.forEach(tag => {
        const item = document.createElement('div');
        item.className = 'tag-suggestion-item';
        item.innerHTML = `<span class="suggestion-icon">#</span><span>${tag}</span>`;
        item.addEventListener('click', () => {
            input.value = `#${tag}`;
            container.style.display = 'none';
            loadEmojis({ q: `#${tag}` });
        });
        container.appendChild(item);
    });
    container.style.display = 'block';
}

/* ========== Load Emojis (AJAX) ========== */

let loadingTimeoutId = null;

function loadEmojis(params = {}) {
    const grid = document.querySelector('.gallery-grid');
    if (!grid) return;

    // Clear any previous loader timeout
    if (loadingTimeoutId) clearTimeout(loadingTimeoutId);

    // Schedule showing the loader ONLY after 1 second of waiting
    loadingTimeoutId = setTimeout(() => {
        grid.innerHTML = '';
        for (let i = 0; i < 10; i++) {
            grid.innerHTML += `
                <div class="skeleton-card">
                    <div class="skeleton-row row-1"><div class="skeleton-preview"></div></div>
                    <div class="skeleton-row row-2"><div class="skeleton-line"></div><div class="skeleton-line short"></div></div>
                    <div class="skeleton-row row-3"><div class="skeleton-line"></div></div>
                    <div class="skeleton-row row-4"><div class="skeleton-line"></div><div class="skeleton-line"></div></div>
                </div>
            `;
        }
    }, 1000);

    const queryString = new URLSearchParams(params).toString();
    fetch(`/api/search.php?${queryString}`)
        .then(res => res.json())
        .then(data => {
            // Data arrived! Cancel the pending loader animation
            if (loadingTimeoutId) {
                clearTimeout(loadingTimeoutId);
                loadingTimeoutId = null;
            }
            grid.innerHTML = '';
            if (data.emojis && data.emojis.length > 0) {
                data.emojis.forEach(emoji => {
                    grid.innerHTML += renderEmojiCard(emoji);
                });
                // Re-init interactive buttons
                initCopyButtons();
                initLikeButtons();
                initTagClicks();
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
            if (loadingTimeoutId) {
                clearTimeout(loadingTimeoutId);
                loadingTimeoutId = null;
            }
            grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#F87171;padding:40px;">Failed to load emojis</p>';
        });
}

function renderEmojiCard(emoji) {
    const author = emoji.is_anonymous ? 'Anonymous' : `@${emoji.username || 'unknown'}`;
    const liked = emoji.user_liked ? true : false;
    const likedClass = liked ? ' liked' : '';
    const heartIcon = liked ? '♥' : '♡';

    // Parse tags
    const tags = emoji.tags ? emoji.tags.split(',').filter(t => t.trim()) : [];
    const tagsHtml = tags.length > 0
        ? `<div class="detail-tags">
            ${tags.map(tag => `<a href="/?q=%23${encodeURIComponent(tag)}" class="tag" data-tag="${escapeAttr(tag)}" onclick="event.stopPropagation()">#${escapeHtml(tag)}</a>`).join(' ')}
           </div>`
        : '<div class="detail-tags"></div>'; // Empty row for consistency

    return `
        <div class="emoji-card" onclick="window.location='/emoji.php?id=${emoji.id}'">
            <div class="emoji-card-row row-emoji">
                <div class="emoji-symbol">${escapeHtml(emoji.symbol)}</div>
            </div>
            <div class="emoji-card-row row-info">
                <div class="emoji-name">${escapeHtml(emoji.name)}</div>
                <div class="emoji-meta">
                    <span class="emoji-creator">${author}</span>
                    <span class="emoji-counter">📋 <span class="copy-count" data-id="${emoji.id}">${emoji.downloads || 0}</span></span>
                </div>
            </div>
            <div class="emoji-card-row row-tags">
                ${tagsHtml}
            </div>
            <div class="emoji-card-row row-actions">
                <div class="card-actions">
                    <button class="btn btn-secondary btn-copy" data-symbol="${escapeAttr(emoji.symbol)}" data-id="${emoji.id}" onclick="event.stopPropagation()">📋 Copy</button>
                    <button class="btn btn-ghost btn-like${likedClass}" data-id="${emoji.id}" onclick="event.stopPropagation()">
                        <span class="like-icon">${heartIcon}</span> <span class="like-count">${emoji.likes || 0}</span>
                    </button>
                </div>
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

function initTagClicks() {
    document.querySelectorAll('.detail-tags .tag').forEach(pill => {
        pill.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const tag = pill.dataset.tag;
            if (!tag) return; // For detail page tags that don't have data-tag (they are <a>)

            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.value = `#${tag}`;
                // Scroll to gallery
                const gallery = document.getElementById('gallery');
                if (gallery) {
                    gallery.scrollIntoView({ behavior: 'smooth' });
                }
                loadEmojis({ q: `#${tag}` });
            }
        });
    });
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

    // Suggestions for tags input
    const suggestions = document.createElement('div');
    suggestions.className = 'tag-suggestions';
    container.appendChild(suggestions);

    let suggestionsTimer;
    input.addEventListener('input', () => {
        const val = input.value.trim();
        clearTimeout(suggestionsTimer);
        // Trigger suggestions only if starts with # (as per request) or just starts typing
        if (val.startsWith('#') && val.length > 1) {
            suggestionsTimer = setTimeout(() => {
                fetch(`/api/tags.php?q=${encodeURIComponent(val)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.tags && data.tags.length > 0) {
                            renderTagsSuggestions(data.tags, suggestions, input, (selected) => {
                                if (tags.length < maxTags && !tags.includes(selected)) {
                                    tags.push(selected);
                                    addTagPill(selected, container, input);
                                    if (hiddenInput) hiddenInput.value = tags.join(',');
                                    input.value = '';
                                }
                                suggestions.style.display = 'none';
                            });
                        } else {
                            suggestions.style.display = 'none';
                        }
                    });
            }, 200);
        } else {
            suggestions.style.display = 'none';
        }
    });

    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });
}

function renderTagsSuggestions(tags, container, input, onSelect) {
    container.innerHTML = '';
    tags.forEach(tag => {
        const item = document.createElement('div');
        item.className = 'tag-suggestion-item';
        item.innerHTML = `<span class="suggestion-icon">#</span><span>${tag}</span>`;
        item.addEventListener('click', () => onSelect(tag));
        container.appendChild(item);
    });
    container.style.display = 'block';
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
