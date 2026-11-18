/**
 * Global Progressive Themed Select Enhancer
 * Upgrades standard HTML <select> elements into theme-matched, accessible,
 * keyboard-navigable dropdowns using the active Swift Track CSS variables
 * across all 6 color themes and Light/Dark modes — while preserving 100% of
 * native form submission, HTML5 validation, onchange handlers, and Alpine x-model.
 */

const EXCLUDED_SELECTOR = [
    '[data-native-select]',
    '[multiple]',
    '.flatpickr-monthDropdown-months',
    '.ts-native',
].join(',');

const CHECK_SVG = `<svg class="ts-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>`;
const CHEVRON_SVG = `<svg class="ts-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>`;
const SEARCH_SVG = `<svg class="ts-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>`;

let uid = 0;
let activeInstance = null;

function isEligibleSelect(select) {
    if (!(select instanceof HTMLSelectElement)) return false;
    if (select.dataset.tsEnhanced === '1') return false;
    if (select.matches(EXCLUDED_SELECTOR)) return false;
    if (select.size && select.size > 1) return false;
    // Skip selects embedded transparently inside custom input-groups (e.g. phone country prefix)
    if (select.classList.contains('bg-transparent') && select.classList.contains('border-0')) return false;
    return true;
}

function detectVariant(select) {
    const cls = select.className || '';
    const isCompact = cls.includes('text-xs') || select.name === 'per_page';
    const isFullWidth =
        cls.includes('w-full') ||
        ((cls.includes('form-select') || cls.includes('form-control')) &&
            !select.style.minWidth &&
            !cls.includes('w-auto') &&
            select.name !== 'per_page');

    return { isCompact, isFullWidth };
}

function enhanceSelect(select) {
    if (!isEligibleSelect(select)) return null;
    select.dataset.tsEnhanced = '1';

    const instanceId = `ts-${++uid}`;
    const { isCompact, isFullWidth } = detectVariant(select);

    // Create wrapper and move native select inside (preserving DOM order & form association)
    const wrapper = document.createElement('div');
    wrapper.className = `ts-wrap${isFullWidth ? ' ts-full' : ' ts-inline'}${isCompact ? ' ts-compact' : ''}`;
    if (select.style.minWidth) {
        wrapper.style.minWidth = select.style.minWidth;
    }

    const parent = select.parentNode;
    if (!parent) return null;
    parent.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    // Hide native select visually while keeping it in the DOM for form submit, required validation & Alpine
    select.classList.add('ts-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    // Trigger button
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = `ts-trigger no-scale${isCompact ? ' ts-trigger-compact' : ''}`;
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', `${instanceId}-listbox`);

    const labelSpan = document.createElement('span');
    labelSpan.className = 'ts-value';

    trigger.appendChild(labelSpan);
    trigger.insertAdjacentHTML('beforeend', CHEVRON_SVG);
    wrapper.appendChild(trigger);

    // Floating menu (mounted into document.body when open so overflow-hidden cards/modals never clip it)
    const menu = document.createElement('div');
    menu.className = `ts-menu${isCompact ? ' ts-menu-compact' : ''}`;
    menu.id = `${instanceId}-listbox`;
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('data-state', 'closed');

    // Search header (shown dynamically when options >= 8)
    const searchWrap = document.createElement('div');
    searchWrap.className = 'ts-search-wrap';
    searchWrap.innerHTML = `${SEARCH_SVG}<input type="text" class="ts-search-input" placeholder="Search options..." autocomplete="off" aria-label="Search options">`;
    const searchInput = searchWrap.querySelector('input');

    const listEl = document.createElement('div');
    listEl.className = 'ts-list custom-scrollbar';

    menu.appendChild(searchWrap);
    menu.appendChild(listEl);

    // Prevent clicks inside the body-teleported menu from triggering parent @click.outside handlers (e.g. modals/drawers)
    menu.addEventListener('click', (e) => e.stopPropagation());
    menu.addEventListener('mousedown', (e) => {
        if (e.target !== searchInput) {
            e.preventDefault();
        }
    });

    let isOpen = false;
    let highlightedIndex = -1;
    let visibleItems = [];
    let typeaheadBuffer = '';
    let typeaheadTimer = null;

    const getSelectedOption = () => {
        if (select.selectedIndex >= 0 && select.options[select.selectedIndex]) {
            return select.options[select.selectedIndex];
        }
        return select.options[0] || null;
    };

    const syncTriggerState = () => {
        const opt = getSelectedOption();
        const text = opt ? opt.textContent.trim() : '';
        labelSpan.textContent = text || 'Select...';

        const isPlaceholder = !opt || opt.value === '';
        labelSpan.classList.toggle('is-placeholder', isPlaceholder);

        const isDisabled = select.disabled;
        trigger.disabled = isDisabled;
        wrapper.classList.toggle('is-disabled', isDisabled);

        const isInvalid =
            select.classList.contains('border-danger') ||
            select.classList.contains('is-invalid') ||
            select.getAttribute('aria-invalid') === 'true';
        trigger.classList.toggle('is-invalid', isInvalid);
    };

    const buildMenuItems = (filterTerm = '') => {
        listEl.innerHTML = '';
        visibleItems = [];

        const query = filterTerm.trim().toLowerCase();
        const totalOptions = select.options.length;
        const showSearch = totalOptions >= 8;
        searchWrap.style.display = showSearch ? 'block' : 'none';

        const currentVal = select.value;

        const appendOptionButton = (option) => {
            const label = option.textContent.trim();
            if (query && !label.toLowerCase().includes(query)) return false;

            const itemBtn = document.createElement('button');
            itemBtn.type = 'button';
            itemBtn.className = 'ts-option no-scale';
            itemBtn.setAttribute('role', 'option');
            itemBtn.dataset.value = option.value;

            const isSelected = option.selected || (option.value === currentVal && select.selectedIndex === option.index);
            itemBtn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            if (isSelected) {
                itemBtn.classList.add('is-selected');
            }
            if (option.disabled) {
                itemBtn.disabled = true;
                itemBtn.classList.add('is-disabled');
            }

            const textEl = document.createElement('span');
            textEl.className = 'ts-option-text';
            textEl.textContent = label;
            itemBtn.appendChild(textEl);

            if (isSelected) {
                itemBtn.insertAdjacentHTML('beforeend', CHECK_SVG);
            }

            const idx = visibleItems.length;
            itemBtn.addEventListener('pointerenter', () => {
                if (!option.disabled) setHighlight(idx);
            });
            itemBtn.addEventListener('mousedown', (e) => {
                // Prevent focus loss on search input / trigger before click completes
                e.preventDefault();
            });
            itemBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (option.disabled) return;
                commitValue(option);
            });

            visibleItems.push({ el: itemBtn, option });
            listEl.appendChild(itemBtn);
            return true;
        };

        // Preserve <optgroup> structure if present
        Array.from(select.children).forEach((child) => {
            if (child.tagName === 'OPTGROUP') {
                const groupOptions = Array.from(child.querySelectorAll('option'));
                const matching = groupOptions.filter((o) => !query || o.textContent.trim().toLowerCase().includes(query));
                if (!matching.length) return;

                const groupLabel = document.createElement('div');
                groupLabel.className = 'ts-group-label';
                groupLabel.textContent = child.label || '';
                listEl.appendChild(groupLabel);

                matching.forEach((opt) => appendOptionButton(opt));
            } else if (child.tagName === 'OPTION') {
                appendOptionButton(child);
            }
        });

        if (visibleItems.length === 0) {
            const emptyEl = document.createElement('div');
            emptyEl.className = 'ts-empty';
            emptyEl.textContent = 'No matching options';
            listEl.appendChild(emptyEl);
            highlightedIndex = -1;
            return;
        }

        const selectedIdx = visibleItems.findIndex((item) => !item.option.disabled && item.el.classList.contains('is-selected'));
        const firstEnabled = visibleItems.findIndex((item) => !item.option.disabled);
        setHighlight(selectedIdx >= 0 ? selectedIdx : firstEnabled, true);
    };

    const setHighlight = (index, scrollIntoView = false) => {
        visibleItems.forEach((item) => item.el.classList.remove('is-highlighted'));
        if (index < 0 || index >= visibleItems.length) {
            highlightedIndex = -1;
            return;
        }
        highlightedIndex = index;
        const target = visibleItems[index].el;
        target.classList.add('is-highlighted');
        if (scrollIntoView) {
            target.scrollIntoView({ block: 'nearest' });
        }
    };

    const moveHighlight = (delta) => {
        if (!visibleItems.length) return;
        const len = visibleItems.length;
        let next = highlightedIndex;
        for (let step = 0; step < len; step++) {
            next = (next + delta + len) % len;
            if (!visibleItems[next].option.disabled) {
                setHighlight(next, true);
                return;
            }
        }
    };

    const positionMenu = () => {
        if (!isOpen) return;
        const rect = trigger.getBoundingClientRect();
        const gap = 6;
        const viewportH = window.innerHeight;
        const viewportW = window.innerWidth;

        const minWidth = Math.max(rect.width, isCompact ? 112 : 160);
        menu.style.minWidth = `${minWidth}px`;
        menu.style.maxWidth = `${Math.min(viewportW - 16, Math.max(rect.width + 64, 340))}px`;

        // Measure natural height
        menu.style.visibility = 'hidden';
        menu.style.top = '0px';
        menu.style.left = '0px';
        const menuRect = menu.getBoundingClientRect();
        const menuH = menuRect.height || 240;
        const menuW = menuRect.width || minWidth;

        const spaceBelow = viewportH - rect.bottom - gap - 8;
        const spaceAbove = rect.top - gap - 8;
        const openAbove = spaceBelow < Math.min(menuH, 220) && spaceAbove > spaceBelow;

        let top;
        let maxListH;
        if (openAbove) {
            const available = Math.max(120, Math.min(spaceAbove, 320));
            maxListH = Math.max(88, available - (searchWrap.style.display !== 'none' ? 52 : 12));
            listEl.style.maxHeight = `${maxListH}px`;
            const finalH = Math.min(menu.getBoundingClientRect().height, available);
            top = Math.max(8, rect.top - gap - finalH);
            menu.dataset.side = 'top';
        } else {
            const available = Math.max(120, Math.min(spaceBelow, 320));
            maxListH = Math.max(88, available - (searchWrap.style.display !== 'none' ? 52 : 12));
            listEl.style.maxHeight = `${maxListH}px`;
            top = rect.bottom + gap;
            menu.dataset.side = 'bottom';
        }

        let left = rect.left;
        if (left + menuW > viewportW - 8) {
            left = Math.max(8, rect.right - menuW);
        }

        menu.style.top = `${Math.round(top)}px`;
        menu.style.left = `${Math.round(left)}px`;
        menu.style.visibility = 'visible';
    };

    const open = () => {
        if (isOpen || select.disabled) return;
        if (activeInstance && activeInstance !== instance) {
            activeInstance.close(false);
        }

        isOpen = true;
        activeInstance = instance;
        searchInput.value = '';
        buildMenuItems('');

        document.body.appendChild(menu);
        trigger.setAttribute('aria-expanded', 'true');
        trigger.classList.add('is-open');

        positionMenu();
        requestAnimationFrame(() => {
            menu.setAttribute('data-state', 'open');
            const selectedEl = listEl.querySelector('.is-selected');
            if (selectedEl) {
                selectedEl.scrollIntoView({ block: 'nearest' });
            }
            if (searchWrap.style.display !== 'none') {
                searchInput.focus({ preventScroll: true });
            }
        });

        window.addEventListener('resize', positionMenu, { passive: true });
        window.addEventListener('scroll', handleViewportScroll, true);
        document.addEventListener('pointerdown', handleOutsidePointer, true);
    };

    const close = (restoreFocus = false) => {
        if (!isOpen) return;
        isOpen = false;
        if (activeInstance === instance) {
            activeInstance = null;
        }

        trigger.setAttribute('aria-expanded', 'false');
        trigger.classList.remove('is-open');
        menu.setAttribute('data-state', 'closed');

        window.removeEventListener('resize', positionMenu);
        window.removeEventListener('scroll', handleViewportScroll, true);
        document.removeEventListener('pointerdown', handleOutsidePointer, true);

        if (menu.parentNode) {
            menu.parentNode.removeChild(menu);
        }

        if (restoreFocus) {
            trigger.focus({ preventScroll: true });
        }
    };

    const toggle = () => {
        isOpen ? close(true) : open();
    };

    const commitValue = (option) => {
        const previousValue = select.value;
        const previousIndex = select.selectedIndex;

        select.selectedIndex = option.index;
        syncTriggerState();
        close(true);

        if (previousValue !== option.value || previousIndex !== option.index) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const handleViewportScroll = (event) => {
        if (menu.contains(event.target)) return;
        positionMenu();
    };

    const handleOutsidePointer = (event) => {
        if (trigger.contains(event.target) || menu.contains(event.target)) return;
        close(false);
    };

    const handleKeydown = (event) => {
        if (!isOpen) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                open();
            }
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close(true);
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveHighlight(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveHighlight(-1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            const first = visibleItems.findIndex((i) => !i.option.disabled);
            if (first >= 0) setHighlight(first, true);
        } else if (event.key === 'End') {
            event.preventDefault();
            for (let i = visibleItems.length - 1; i >= 0; i--) {
                if (!visibleItems[i].option.disabled) {
                    setHighlight(i, true);
                    break;
                }
            }
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (highlightedIndex >= 0 && visibleItems[highlightedIndex]) {
                commitValue(visibleItems[highlightedIndex].option);
            }
        } else if (event.key === 'Tab') {
            close(false);
        } else if (
            searchWrap.style.display === 'none' &&
            event.key.length === 1 &&
            !event.ctrlKey &&
            !event.metaKey &&
            !event.altKey
        ) {
            // Typeahead for short lists without a search box
            typeaheadBuffer += event.key.toLowerCase();
            clearTimeout(typeaheadTimer);
            typeaheadTimer = setTimeout(() => {
                typeaheadBuffer = '';
            }, 600);

            const matchIdx = visibleItems.findIndex(
                (i) => !i.option.disabled && i.option.textContent.trim().toLowerCase().startsWith(typeaheadBuffer),
            );
            if (matchIdx >= 0) {
                setHighlight(matchIdx, true);
            }
        }
    };

    // Trigger interactions (preventDefault stops wrapping <label> from invoking native OS dropdown)
    trigger.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggle();
    });
    trigger.addEventListener('keydown', handleKeydown);

    searchInput.addEventListener('input', () => {
        buildMenuItems(searchInput.value);
        positionMenu();
    });
    searchInput.addEventListener('keydown', handleKeydown);

    // Handle external <label for="selectId"> clicks smoothly
    if (select.id) {
        document.querySelectorAll(`label[for="${CSS.escape(select.id)}"]`).forEach((lbl) => {
            lbl.addEventListener('click', (e) => {
                if (e.target === trigger || trigger.contains(e.target)) return;
                e.preventDefault();
                trigger.focus();
                open();
            });
        });
    }

    // Intercept programmatic value updates (e.g., Alpine x-model, modal openEdit)
    const nativeValueDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    if (nativeValueDesc && nativeValueDesc.configurable) {
        Object.defineProperty(select, 'value', {
            configurable: true,
            get() {
                return nativeValueDesc.get.call(this);
            },
            set(val) {
                nativeValueDesc.set.call(this, val);
                syncTriggerState();
            },
        });
    }

    const nativeIndexDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
    if (nativeIndexDesc && nativeIndexDesc.configurable) {
        Object.defineProperty(select, 'selectedIndex', {
            configurable: true,
            get() {
                return nativeIndexDesc.get.call(this);
            },
            set(idx) {
                nativeIndexDesc.set.call(this, idx);
                syncTriggerState();
            },
        });
    }

    select.addEventListener('change', syncTriggerState);
    select.addEventListener('input', syncTriggerState);
    if (select.form) {
        select.form.addEventListener('reset', () => setTimeout(syncTriggerState, 0));
    }

    // Observe dynamic <option> changes (e.g., Alpine x-for AJAX options) & disabled/class changes
    const observer = new MutationObserver(() => {
        syncTriggerState();
        if (isOpen) {
            buildMenuItems(searchInput.value);
            positionMenu();
        }
    });
    observer.observe(select, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['disabled', 'class', 'selected', 'aria-invalid'],
    });

    syncTriggerState();

    const instance = { open, close, syncTriggerState };
    return instance;
}

export function initThemedSelects() {
    const scan = (root = document) => {
        if (root instanceof HTMLSelectElement) {
            enhanceSelect(root);
            return;
        }
        if (!root.querySelectorAll) return;
        root.querySelectorAll('select').forEach((sel) => enhanceSelect(sel));
    };

    // Initial pass (after Alpine has had a tick to run x-for / x-model)
    scan(document);
    requestAnimationFrame(() => scan(document));

    // Watch for dynamically injected selects (e.g. modals, x-if templates)
    const bodyObserver = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    scan(node);
                }
            }
        }
    });

    if (document.body) {
        bodyObserver.observe(document.body, { childList: true, subtree: true });
    }
}
