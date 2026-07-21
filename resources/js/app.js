import { ChevronDown, ChevronRight, CircleHelp, FilePlus, History, Pencil, Trash2, X, createIcons } from 'lucide';

createIcons({
    icons: {
        ChevronDown,
        ChevronRight,
        CircleHelp,
        FilePlus,
        History,
        Pencil,
        Trash2,
        X,
    },
});

function syncLoginPanels() {
    const checked = document.querySelector('[data-login-mode]:checked');
    const mode = checked?.value ?? 'none';

    document.querySelectorAll('[data-login-panel]').forEach((panel) => {
        const isActive = panel.dataset.loginPanel === mode;
        panel.hidden = !isActive;
        panel.querySelectorAll('input, select').forEach((field) => {
            field.disabled = !isActive;
        });
    });
}

function syncLoginUserFields() {
    const firstName = document.querySelector('[data-employee-first-name]')?.value.trim() ?? '';
    const lastName = document.querySelector('[data-employee-last-name]')?.value.trim() ?? '';
    const workEmail = document.querySelector('[data-employee-work-email]')?.value.trim() ?? '';
    const loginName = [firstName, lastName].filter(Boolean).join(' ');
    const nameField = document.querySelector('[data-login-user-name]');
    const emailField = document.querySelector('[data-login-user-email]');
    const namePreview = document.querySelector('[data-login-user-name-preview]');
    const emailPreview = document.querySelector('[data-login-user-email-preview]');

    if (nameField) {
        nameField.value = loginName;
    }

    if (emailField) {
        emailField.value = workEmail;
    }

    if (namePreview) {
        namePreview.textContent = loginName || 'Use employee name';
    }

    if (emailPreview) {
        emailPreview.textContent = workEmail || 'Use work email';
    }
}

document.querySelectorAll('[data-login-mode]').forEach((input) => {
    input.addEventListener('change', syncLoginPanels);
});
syncLoginPanels();

document.querySelectorAll('[data-employee-first-name], [data-employee-last-name], [data-employee-work-email]').forEach((input) => {
    input.addEventListener('input', syncLoginUserFields);
});
syncLoginUserFields();

document.querySelectorAll('[data-quick-select]').forEach((select) => {
    select.addEventListener('change', () => {
        if (select.value !== '__create') {
            select.dataset.current = select.value;
            return;
        }

        const type = select.dataset.quickSelect;
        const dialog = document.querySelector(`[data-quick-dialog="${type}"]`);

        if (!dialog) {
            select.value = select.dataset.current || '';
            return;
        }

        dialog.showModal();
    });
});

document.querySelectorAll('[data-quick-dialog]').forEach((dialog) => {
    dialog.addEventListener('close', () => {
        const select = document.querySelector(`[data-quick-select="${dialog.dataset.quickDialog}"]`);

        if (select?.value === '__create') {
            select.value = select.dataset.current || '';
        }
    });
});

document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector(`[data-dialog="${button.dataset.dialogOpen}"]`)?.showModal();
    });
});

document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector(`[data-dialog="${button.dataset.dialogClose}"]`)?.close();
    });
});

document.querySelectorAll('[data-quick-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const type = form.dataset.quickForm;
        const select = document.querySelector(`[data-quick-select="${type}"]`);
        const dialog = document.querySelector(`[data-quick-dialog="${type}"]`);
        const error = form.querySelector('[data-quick-error]');
        const payload = new FormData(form);

        if (error) {
            error.hidden = true;
            error.classList.add('hidden');
            error.textContent = '';
        }

        try {
            const response = await fetch(form.dataset.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: payload,
            });
            const body = await response.json();

            if (!response.ok) {
                throw new Error(body?.error?.message || body?.message || 'Could not create record.');
            }

            const option = new Option(body.data.option_label || body.data.path || body.data.name, body.data.id, true, true);
            select.add(option, select.querySelector('option[value="__create"]'));
            select.dataset.current = body.data.id;
            form.reset();
            dialog.close();
        } catch (quickCreateError) {
            if (error) {
                error.textContent = quickCreateError.message;
                error.hidden = false;
                error.classList.remove('hidden');
            }
            select.value = select.dataset.current || '';
        }
    });
});

document.querySelectorAll('[data-repeatable-photos]').forEach((container) => {
    const inputs = container.querySelector('[data-photo-inputs]');
    const addButton = container.querySelector('[data-add-photo]');

    addButton?.addEventListener('click', () => {
        const input = document.createElement('input');
        input.name = 'photos[]';
        input.type = 'file';
        input.accept = 'image/*';
        input.dataset.photoInput = '';
        input.className = 'w-full rounded-md border border-zinc-300 px-3 py-2';

        inputs?.append(input);
        input.focus();
    });
});

document.querySelectorAll('[data-tag-suggestion]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.querySelector('[data-tag-input]');
        const tag = button.dataset.tagSuggestion;

        if (!input || !tag) {
            return;
        }

        const tags = input.value
            .split(',')
            .map((value) => value.trim())
            .filter(Boolean);

        if (!tags.some((value) => value.toLowerCase() === tag.toLowerCase())) {
            tags.push(tag);
        }

        input.value = tags.join(', ');
        input.focus();
    });
});

function normalizedSearch(value) {
    return value.trim().toLocaleLowerCase();
}

function setComboboxOpen(search, menu, open) {
    menu.hidden = !open;
    search.setAttribute('aria-expanded', open ? 'true' : 'false');
}

document.querySelectorAll('[data-character-limit]').forEach((field) => {
    const counter = field.closest('label')?.querySelector('[data-character-count]');
    const limit = Number(field.dataset.characterLimit);
    const update = () => {
        if (counter) {
            counter.textContent = `${field.value.length}/${limit}`;
        }
    };

    field.addEventListener('input', update);
    update();
});

document.querySelectorAll('[data-single-combobox]').forEach((combobox) => {
    const search = combobox.querySelector('[data-combobox-search]');
    const value = combobox.querySelector('[data-combobox-value]');
    const menu = combobox.querySelector('[data-combobox-menu]');
    const empty = combobox.querySelector('[data-combobox-empty]');
    const options = [...combobox.querySelectorAll('[data-combobox-option]')];
    const toggle = combobox.querySelector('[data-combobox-toggle]');

    const filter = () => {
        const query = normalizedSearch(search.value);
        let visible = 0;

        options.forEach((option) => {
            const matches = !query || normalizedSearch(option.dataset.label).includes(query);
            option.hidden = !matches;
            option.setAttribute('aria-selected', option.dataset.value === value.value ? 'true' : 'false');
            visible += matches ? 1 : 0;
        });

        empty.hidden = visible > 0;
    };

    const open = () => {
        filter();
        setComboboxOpen(search, menu, true);
    };

    const close = () => setComboboxOpen(search, menu, false);

    const select = (option) => {
        value.value = option.dataset.value;
        search.value = option.dataset.value ? option.dataset.label : '';
        filter();
        close();
        search.focus();
    };

    search.addEventListener('focus', open);
    search.addEventListener('input', () => {
        value.value = '';
        open();
    });
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }

        if (event.key === 'Enter') {
            const firstVisible = options.find((option) => !option.hidden);
            if (firstVisible && !menu.hidden) {
                event.preventDefault();
                select(firstVisible);
            }
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            open();
            options.find((option) => !option.hidden)?.focus();
        }
    });
    toggle.addEventListener('click', () => {
        menu.hidden ? open() : close();
        search.focus();
    });
    options.forEach((option) => {
        option.addEventListener('click', () => select(option));
        option.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
                search.focus();
            }
        });
    });
    document.addEventListener('click', (event) => {
        if (!combobox.contains(event.target)) {
            close();
            const selected = options.find((option) => option.dataset.value === value.value);
            search.value = selected?.dataset.value ? selected.dataset.label : '';
        }
    });
});

document.querySelectorAll('[data-multi-combobox]').forEach((combobox) => {
    const search = combobox.querySelector('[data-combobox-search]');
    const control = combobox.querySelector('[data-combobox-control]');
    const selectedContainer = combobox.querySelector('[data-combobox-selected]');
    const valuesContainer = combobox.querySelector('[data-combobox-values]');
    const menu = combobox.querySelector('[data-combobox-menu]');
    const empty = combobox.querySelector('[data-combobox-empty]');
    const options = [...combobox.querySelectorAll('[data-combobox-option]')];
    const toggle = combobox.querySelector('[data-combobox-toggle]');
    const selected = [...valuesContainer.querySelectorAll('input[name="tags[]"]')].map((input) => input.value);

    const hasValue = (candidate) => selected.some((item) => normalizedSearch(item) === normalizedSearch(candidate));

    const filter = () => {
        const query = normalizedSearch(search.value);
        let visible = 0;

        options.forEach((option) => {
            const matches = !hasValue(option.dataset.value)
                && (!query || normalizedSearch(option.dataset.label).includes(query));
            option.hidden = !matches;
            option.setAttribute('aria-selected', hasValue(option.dataset.value) ? 'true' : 'false');
            visible += matches ? 1 : 0;
        });

        empty.hidden = visible > 0;
    };

    const open = () => {
        filter();
        setComboboxOpen(search, menu, true);
    };

    const close = () => setComboboxOpen(search, menu, false);

    const render = () => {
        selectedContainer.replaceChildren();
        valuesContainer.replaceChildren();

        selected.forEach((tag) => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex max-w-full items-center gap-1 rounded bg-zinc-100 px-2 py-1 text-sm text-zinc-700';
            chip.dataset.selectedValue = tag;

            const label = document.createElement('span');
            label.className = 'truncate';
            label.textContent = tag;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.removeValue = tag;
            remove.setAttribute('aria-label', `Remove ${tag}`);
            remove.className = 'text-zinc-400 hover:text-zinc-950';
            remove.textContent = '×';

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'tags[]';
            hidden.value = tag;

            chip.append(label, remove);
            selectedContainer.append(chip);
            valuesContainer.append(hidden);
        });

        filter();
    };

    const add = (option) => {
        if (!hasValue(option.dataset.value)) {
            selected.push(option.dataset.value);
        }
        search.value = '';
        render();
        open();
        search.focus();
    };

    search.addEventListener('focus', open);
    search.addEventListener('input', open);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }

        if (event.key === 'Enter') {
            const firstVisible = options.find((option) => !option.hidden);
            if (firstVisible && !menu.hidden) {
                event.preventDefault();
                add(firstVisible);
            }
        }

        if (event.key === 'Backspace' && search.value === '' && selected.length > 0) {
            selected.pop();
            render();
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            open();
            options.find((option) => !option.hidden)?.focus();
        }
    });
    toggle.addEventListener('click', () => {
        menu.hidden ? open() : close();
        search.focus();
    });
    control.addEventListener('click', (event) => {
        if (!event.target.closest('[data-remove-value], [data-combobox-toggle]')) {
            search.focus();
        }
    });
    selectedContainer.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-value]');
        if (!button) {
            return;
        }

        const index = selected.findIndex((item) => normalizedSearch(item) === normalizedSearch(button.dataset.removeValue));
        if (index >= 0) {
            selected.splice(index, 1);
            render();
            search.focus();
        }
    });
    options.forEach((option) => {
        option.addEventListener('click', () => add(option));
        option.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
                search.focus();
            }
        });
    });
    document.addEventListener('click', (event) => {
        if (!combobox.contains(event.target)) {
            close();
        }
    });

    render();
});

document.querySelectorAll('[data-markdown-editor]').forEach((editor) => {
    const body = editor.querySelector('[data-markdown-body]');
    const title = editor.querySelector('[data-markdown-title]');
    const importer = editor.querySelector('[data-markdown-import]');
    const importStatus = editor.querySelector('[data-import-status]');
    const writePanel = editor.querySelector('[data-markdown-write]');
    const previewPanel = editor.querySelector('[data-markdown-preview]');
    const previewContent = editor.querySelector('[data-markdown-preview-content]');
    const previewEmpty = editor.querySelector('[data-markdown-preview-empty]');
    const tabs = editor.querySelectorAll('[data-markdown-tab]');
    const articleLinkMenu = editor.querySelector('[data-article-link-menu]');
    let articleLinkAbort;
    let articleLinkTimer;
    let mentionStart = null;

    const closeArticleLinks = () => {
        if (articleLinkMenu) {
            articleLinkMenu.hidden = true;
            articleLinkMenu.replaceChildren();
        }
        mentionStart = null;
    };

    const insertArticleLink = (markdown) => {
        if (mentionStart === null) {
            return;
        }

        const cursor = body.selectionStart;
        body.setRangeText(`${markdown} `, mentionStart, cursor, 'end');
        closeArticleLinks();
        body.focus();
    };

    const searchArticleLinks = async () => {
        if (!articleLinkMenu || !editor.dataset.linkSearchUrl) {
            return;
        }

        const cursor = body.selectionStart;
        const match = body.value.slice(0, cursor).match(/(^|\s)@([^@\n]{1,80})$/);
        const query = match?.[2]?.trim() ?? '';
        if (!match || query.length < 1) {
            closeArticleLinks();
            return;
        }

        mentionStart = cursor - match[2].length - 1;
        articleLinkAbort?.abort();
        articleLinkAbort = new AbortController();
        const url = new URL(editor.dataset.linkSearchUrl, window.location.origin);
        url.searchParams.set('q', query);
        if (editor.dataset.currentArticleId) {
            url.searchParams.set('exclude', editor.dataset.currentArticleId);
        }

        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: articleLinkAbort.signal});
            const payload = await response.json();
            articleLinkMenu.replaceChildren();

            (payload.data ?? []).forEach((article) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'block w-full rounded px-3 py-2 text-left hover:bg-zinc-100';

                const title = document.createElement('span');
                title.className = 'block text-sm font-medium';
                title.textContent = article.title;

                const meta = document.createElement('span');
                meta.className = 'mt-0.5 block text-xs text-zinc-500';
                meta.textContent = [article.category, article.status].filter(Boolean).join(' · ');

                button.append(title, meta);
                button.addEventListener('click', () => insertArticleLink(article.markdown));
                articleLinkMenu.append(button);
            });

            if ((payload.data ?? []).length === 0) {
                const empty = document.createElement('p');
                empty.className = 'px-3 py-4 text-center text-sm text-zinc-500';
                empty.textContent = 'No matching articles.';
                articleLinkMenu.append(empty);
            }

            articleLinkMenu.hidden = false;
        } catch (error) {
            if (error.name !== 'AbortError') {
                closeArticleLinks();
            }
        }
    };

    body.addEventListener('input', () => {
        window.clearTimeout(articleLinkTimer);
        articleLinkTimer = window.setTimeout(searchArticleLinks, 180);
    });
    body.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeArticleLinks();
        }
    });

    importer?.addEventListener('change', async () => {
        const file = importer.files?.[0];
        if (!file) {
            return;
        }

        try {
            const buffer = await file.arrayBuffer();
            const markdown = new TextDecoder('utf-8', { fatal: true }).decode(buffer);
            body.value = markdown;

            if (!title.value.trim()) {
                const heading = markdown.match(/^\s*#\s+(.+?)\s*#*\s*$/m)?.[1];
                const filename = file.name.replace(/\.(md|markdown)$/i, '').replace(/[-_]+/g, ' ');
                title.value = (heading || filename).replace(/[*_`\[\]]/g, '').trim();
            }

            if (importStatus) {
                importStatus.textContent = `${file.name} loaded and ready to edit.`;
            }
        } catch {
            if (importStatus) {
                importStatus.textContent = 'This file is not valid UTF-8 Markdown.';
                importStatus.classList.add('text-red-700');
            }
        }
    });

    tabs.forEach((tab) => {
        tab.addEventListener('click', async () => {
            const mode = tab.dataset.markdownTab;
            const isPreview = mode === 'preview';

            tabs.forEach((item) => {
                const active = item === tab;
                item.setAttribute('aria-selected', active ? 'true' : 'false');
                item.classList.toggle('font-semibold', active);
                item.classList.toggle('text-zinc-950', active);
                item.classList.toggle('shadow-sm', active);
                item.classList.toggle('font-medium', !active);
                item.classList.toggle('text-zinc-500', !active);
            });

            writePanel.hidden = isPreview;
            previewPanel.hidden = !isPreview;

            if (!isPreview) {
                body.focus();
                return;
            }

            previewContent.innerHTML = '';
            previewEmpty.hidden = Boolean(body.value.trim());
            if (!body.value.trim()) {
                return;
            }

            const response = await fetch(editor.dataset.previewUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ body_markdown: body.value }),
            });
            const payload = await response.json();

            if (response.ok) {
                previewContent.innerHTML = payload.data.html;
            } else {
                previewEmpty.hidden = false;
                previewEmpty.textContent = payload?.error?.message || payload?.message || 'Preview could not be loaded.';
            }
        });
    });
});
