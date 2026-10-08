<?php

// panel.php — English copy for the panel chrome and the pages wired to translations.
//
// exports: none
// used_by: none
// rules:   Keys match lang/it/panel.php. Italian remains the source wording. Loaded as the panel group by HandleInertiaRequests.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | English panel strings.

return [
    'locales' => [
        'it' => 'Italiano',
        'en' => 'English',
    ],
    'layout' => [
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
        'account' => 'Account',
        'security' => 'Security (2FA)',
        'language' => 'Language',
        'logout' => 'Log out',
        'version' => 'Version',
        'panel_eyebrow' => 'Hosting panel',
        'hosting_badge' => 'Hosting',
        'nav' => [
            'customers' => 'Customers',
            'spaces' => 'Spaces',
            'domains' => 'Domains',
            'my_hosting' => 'My hosting',
            'plans' => 'Plans',
            'infrastructures' => 'Infrastructures',
            'toolkit' => 'Laravel Toolkit',
            'smtp' => 'SMTP',
            'audit' => 'Audit',
        ],
        'mobile' => [
            'customers' => 'Customers',
            'spaces' => 'Spaces',
            'plans' => 'Plans',
            'infra' => 'Infra',
            'toolkit' => 'Toolkit',
        ],
    ],
    'locale_settings' => [
        'title' => 'Language',
        'description' => 'Choose the panel language. The choice is kept for this session.',
        'heading' => 'Panel language',
        'help' => 'Italian is the default. The change stays active until the session ends.',
        'save' => 'Save language',
        'saved' => 'Language updated.',
        'invalid' => 'Choose Italian or English.',
    ],
    'customers' => [
        'title' => 'Customers',
        'description' => 'Accounts that own spaces, domains, databases, and SFTP.',
        'new' => 'New customer',
        'empty_title' => 'No customers',
        'empty_body' => 'Create a customer, then a space, and finally a domain.',
        'name' => 'Name',
        'email' => 'Email',
        'spaces' => 'Spaces',
        'domains' => 'Domains',
    ],
    'spaces' => [
        'title' => 'Spaces',
        'description' => 'Subscriptions: one customer, one plan, the site domains.',
        'new' => 'New space',
        'empty_title' => 'No spaces',
        'empty_body' => 'Create a customer and a plan, then attach a space.',
        'name' => 'Name',
        'customer' => 'Customer',
        'plan' => 'Plan',
        'domains' => 'Domains',
        'actions' => 'Actions',
        'delete' => 'Delete',
        'modal' => [
            'title' => 'Delete space :name?',
            'body' => 'The space is removed from the panel. Linked Dokploy services and domains are deleted too. Access, storage, and panel accounts for those domains are removed. This cannot be undone.',
            'dokploy_service' => 'Dokploy service: :service',
            'no_dokploy_service' => 'No Dokploy service',
            'no_targets' => 'No domain or Dokploy service is linked. Only the space will be deleted.',
            'confirm_lead' => 'To confirm, type',
            'target_domain' => 'the domain',
            'target_space' => 'the space name',
            'cancel' => 'Cancel',
            'submit' => 'Delete space',
        ],
    ],
    'domains' => [
        'title' => 'Domains',
        'my_hosting_title' => 'My hosting',
        'description_admin' => 'All hosting on the panel.',
        'description_member' => 'Hosting you can access. Databases, SFTP, and the toolkit stay here in the panel.',
        'empty_title' => 'No hosting',
        'empty_member' => 'Ask an administrator to add you from the domain’s Access tab.',
        'domain' => 'Domain',
        'customer' => 'Customer',
        'stack' => 'Stack',
    ],
];
