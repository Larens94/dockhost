// usePanelTranslations.js — Dot-key lookup for the shared panel translations prop.
//
// exports: usePanelTranslations
// used_by: none
// rules:   Read page.props.translations from Laravel lang files. Do not add a JS i18n package. Panel Vue pages and components import this helper.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Shared Inertia translations helper.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Used by the rest of the panel UI.

import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePanelTranslations() {
    const page = usePage();

    const translations = computed(() => {
        const value = page.props.translations;

        return value && typeof value === 'object' ? value : {};
    });

    const locale = computed(() => (typeof page.props.locale === 'string' ? page.props.locale : 'it'));

    const t = (key, replacements = {}) => {
        const value = String(key)
            .split('.')
            .reduce((node, part) => {
                if (node !== null && typeof node === 'object' && Object.prototype.hasOwnProperty.call(node, part)) {
                    return node[part];
                }

                return undefined;
            }, translations.value);

        if (typeof value !== 'string') {
            return key;
        }

        return Object.entries(replacements).reduce(
            (text, [name, replacement]) => text.replaceAll(`:${name}`, String(replacement ?? '')),
            value,
        );
    };

    return { t, locale, translations };
}
