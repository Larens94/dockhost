import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Toolkit({ site, toolkit, state, envPreview, artisanCommands, dokployLinks, dokployConfigured }) {
    const [tab, setTab] = useState(toolkit.tabs?.[0]?.id || 'dashboard');
    const settings = useForm({
        schedule_enabled: !!state.schedule_enabled,
        queue_enabled: !!state.queue_enabled,
        maintenance: !!state.maintenance,
        repository: site.repository || '',
    });
    const artisan = useForm({ command: artisanCommands[0] || 'about' });

    const saveSettings = (event) => {
        event.preventDefault();
        settings.transform((payload) => ({
            ...payload,
            schedule_enabled: payload.schedule_enabled ? 1 : 0,
            queue_enabled: payload.queue_enabled ? 1 : 0,
            maintenance: payload.maintenance ? 1 : 0,
        }));
        settings.patch(route('sites.toolkit.update', site.id));
    };

    const runArtisan = (event) => {
        event.preventDefault();
        artisan.post(route('sites.artisan', site.id));
    };

    return (
        <AuthenticatedLayout header={site.domain}>
            <Head title={site.domain} />
            <PageTitle
                title={site.domain}
                subtitle={`${site.client} · ${toolkit.title}`}
                action={
                    <div className="flex items-center gap-3">
                        <StatusBadge status={site.status} />
                        <Link
                            href={route('sites.destroy', site.id)}
                            method="delete"
                            as="button"
                            className="text-sm text-red-700"
                            onClick={(event) => {
                                if (!confirm(`Deprovision ${site.domain}?`)) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            Deprovision
                        </Link>
                    </div>
                }
            />

            {site.last_error && (
                <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{site.last_error}</div>
            )}

            <div className="mb-4 flex gap-2 overflow-x-auto">
                {toolkit.tabs.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => setTab(item.id)}
                        className={`rounded-full px-3 py-1 text-sm ${tab === item.id ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'}`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-[2fr,1fr]">
                <div className="space-y-6">
                    {tab === 'dashboard' && (
                        <Card className="grid gap-4 p-5 sm:grid-cols-2">
                            <Fact label="Recipe" value={site.recipe} />
                            <Fact label="Dokploy app" value={site.dokploy_app_id || 'not created'} />
                            <Fact label="Pools" value={(site.pools || []).join(', ') || 'none'} />
                            <Fact label="Repository" value={site.repository || 'not set'} />
                            {site.database && (
                                <>
                                    <Fact label="Database" value={`${site.database.schema} (${site.database.status})`} />
                                    <Fact label="DB user" value={`${site.database.username}@${site.database.host || 'pending'}:${site.database.port || ''}`} />
                                    <Fact label="DB password" value={site.database.password} />
                                </>
                            )}
                            {site.sftp && (
                                <>
                                    <Fact label="SFTP" value={`${site.sftp.username} · ${site.sftp.status}`} />
                                    <Fact label="Chroot" value={site.sftp.path} />
                                    <Fact label="SFTP password" value={site.sftp.password} />
                                </>
                            )}
                        </Card>
                    )}

                    {tab === 'artisan' && (
                        <Card className="p-5">
                            <form onSubmit={runArtisan} className="flex gap-3">
                                <select
                                    value={artisan.data.command}
                                    onChange={(event) => artisan.setData('command', event.target.value)}
                                    className="block w-full rounded-md border-slate-300 text-sm"
                                >
                                    {artisanCommands.map((command) => <option key={command}>{command}</option>)}
                                </select>
                                <PrimaryButton disabled={artisan.processing}>Record</PrimaryButton>
                            </form>
                            <InputError message={artisan.errors.command} className="mt-2" />
                            <p className="mt-3 text-xs text-slate-500">Commands are allowlisted. Execution happens in the site container, which DockHost does not shell into.</p>
                            <div className="mt-4 space-y-3">
                                {(state.artisan_history || []).map((entry, index) => (
                                    <pre key={index} className="overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">
                                        {`$ php artisan ${entry.command}\n${entry.output}`}
                                    </pre>
                                ))}
                            </div>
                        </Card>
                    )}

                    {tab !== 'dashboard' && tab !== 'artisan' && (
                        <Card className="p-5 text-sm text-slate-600">
                            {toolkit.tabs.find((item) => item.id === tab)?.owner === 'dokploy'
                                ? 'This area belongs to Dokploy. Open it there instead of rebuilding it here.'
                                : 'Desired state is saved on the site. Applying schedule, queue, and maintenance updates the generated environment and, when Dokploy is connected, pushes that environment.'}
                        </Card>
                    )}

                    <Card className="p-5">
                        <div className="text-sm font-medium">Environment preview</div>
                        <pre className="mt-3 overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{envPreview || 'No environment yet.'}</pre>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card className="p-5">
                        <form onSubmit={saveSettings} className="space-y-3 text-sm">
                            <Check label="Scheduled tasks" checked={settings.data.schedule_enabled} onChange={(value) => settings.setData('schedule_enabled', value)} />
                            <Check label="Queue" checked={settings.data.queue_enabled} onChange={(value) => settings.setData('queue_enabled', value)} />
                            <Check label="Maintenance mode" checked={settings.data.maintenance} onChange={(value) => settings.setData('maintenance', value)} />
                            <label className="block font-medium">
                                Repository
                                <TextInput value={settings.data.repository} onChange={(event) => settings.setData('repository', event.target.value)} className="mt-1 block w-full" />
                            </label>
                            <PrimaryButton disabled={settings.processing}>Save desired state</PrimaryButton>
                        </form>
                    </Card>

                    <Card className="p-5">
                        <div className="text-sm font-medium">Dokploy</div>
                        {!dokployConfigured && <p className="mt-2 text-sm text-slate-500">Set DOKPLOY_URL to deep-link deploy, logs, SSL, and the terminal.</p>}
                        {dokployLinks && (
                            <div className="mt-3 flex flex-col gap-2 text-sm">
                                {Object.entries(dokployLinks).map(([key, href]) => (
                                    <a key={key} href={href} target="_blank" rel="noreferrer" className="text-blue-700">
                                        Open {key}
                                    </a>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Fact({ label, value }) {
    return (
        <div>
            <div className="text-xs uppercase tracking-wide text-slate-400">{label}</div>
            <div className="mt-1 break-all text-sm">{value}</div>
        </div>
    );
}

function Check({ label, checked, onChange }) {
    return (
        <label className="flex items-center gap-2">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            {label}
        </label>
    );
}
