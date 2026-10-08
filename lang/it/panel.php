<?php

// panel.php — Italian copy for the panel chrome and the pages wired to translations.
//
// exports: none
// used_by: none
// rules:   Italian is the source wording and the default locale. Loaded as the panel group by HandleInertiaRequests.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Italian panel strings.

return [
    'locales' => [
        'it' => 'Italiano',
        'en' => 'English',
    ],
    'layout' => [
        'open_menu' => 'Apri menu',
        'close_menu' => 'Chiudi menu',
        'account' => 'Account',
        'security' => 'Sicurezza (2FA)',
        'language' => 'Lingua',
        'logout' => 'Esci',
        'version' => 'Versione',
        'panel_eyebrow' => 'Pannello hosting',
        'hosting_badge' => 'Hosting',
        'nav' => [
            'customers' => 'Clienti',
            'spaces' => 'Spazi',
            'domains' => 'Domini',
            'my_hosting' => 'I miei hosting',
            'plans' => 'Piani',
            'infrastructures' => 'Infrastrutture',
            'toolkit' => 'Laravel Toolkit',
            'smtp' => 'SMTP',
            'audit' => 'Audit',
        ],
        'mobile' => [
            'customers' => 'Clienti',
            'spaces' => 'Spazi',
            'plans' => 'Piani',
            'infra' => 'Infra',
            'toolkit' => 'Toolkit',
        ],
    ],
    'locale_settings' => [
        'title' => 'Lingua',
        'description' => 'Scegli la lingua del pannello. La scelta resta salvata per questa sessione.',
        'heading' => 'Lingua del pannello',
        'help' => 'Italiano è la lingua predefinita. Il cambio resta attivo finché la sessione è aperta.',
        'save' => 'Salva lingua',
        'saved' => 'Lingua aggiornata.',
        'invalid' => 'Scegli italiano o inglese.',
    ],
    'customers' => [
        'title' => 'Clienti',
        'description' => 'Account che possiedono spazi, domini, database e SFTP.',
        'new' => 'Nuovo cliente',
        'empty_title' => 'Nessun cliente',
        'empty_body' => 'Crea un cliente, poi uno spazio e infine un dominio.',
        'name' => 'Nome',
        'email' => 'Email',
        'spaces' => 'Spazi',
        'domains' => 'Domini',
    ],
    'spaces' => [
        'title' => 'Spazi',
        'description' => 'Iscrizioni: un cliente, un piano, i domini del sito.',
        'new' => 'Nuovo spazio',
        'empty_title' => 'Nessuno spazio',
        'empty_body' => 'Crea un cliente e un piano, poi associa uno spazio.',
        'name' => 'Nome',
        'customer' => 'Cliente',
        'plan' => 'Piano',
        'domains' => 'Domini',
        'actions' => 'Azioni',
        'delete' => 'Elimina',
        'modal' => [
            'title' => 'Eliminare lo spazio :name?',
            'body' => 'Lo spazio sparisce dal pannello. Su Dokploy vengono eliminati anche i servizi e i domini collegati. Accessi, storage e account del pannello di quei domini vengono rimossi. Non si può annullare.',
            'dokploy_service' => 'Servizio Dokploy: :service',
            'no_dokploy_service' => 'Nessun servizio Dokploy',
            'no_targets' => 'Nessun dominio o servizio Dokploy collegato. Verrà eliminato solo lo spazio.',
            'confirm_lead' => 'Per confermare, digita',
            'target_domain' => 'il dominio',
            'target_space' => 'il nome dello spazio',
            'cancel' => 'Annulla',
            'submit' => 'Elimina spazio',
        ],
    ],
    'domains' => [
        'title' => 'Domini',
        'my_hosting_title' => 'I miei hosting',
        'description_admin' => 'Tutti gli hosting del pannello.',
        'description_member' => 'Hosting a cui hai accesso. Database, SFTP e toolkit restano qui nel pannello.',
        'empty_title' => 'Nessun hosting',
        'empty_member' => 'Chiedi a un amministratore di aggiungerti dalla scheda «Accessi» del dominio.',
        'domain' => 'Dominio',
        'customer' => 'Cliente',
        'stack' => 'Stack',
    ],
];
