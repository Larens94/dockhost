import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout, { Card, PageTitle } from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Form({ pool, servers, kinds }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: pool?.name || '',
        kind: pool?.kind || 'database',
        engine: pool?.engine || '',
        runtime_version: pool?.runtime_version || '',
        server_id: pool?.server_id || '',
        capacity: pool?.capacity || 10,
        dokploy_ref: pool?.dokploy_ref || '',
        host: pool?.host || '',
        port: pool?.port || '',
        mode: pool?.mode || 'shared',
        admin_username: pool?.admin_username || '',
        admin_password: '',
        admin_database: pool?.admin_database || '',
        dokploy_environment_id: pool?.dokploy_environment_id || '',
        ssh_host: pool?.ssh_host || '',
        ssh_port: pool?.ssh_port || 22,
        ssh_username: pool?.ssh_username || '',
        ssh_private_key: '',
    });

    const submit = (event) => {
        event.preventDefault();

        if (pool) {
            patch(route('pools.update', pool.id));
            return;
        }

        post(route('pools.store'));
    };

    return (
        <AuthenticatedLayout header={pool ? 'Edit pool' : 'New pool'}>
            <Head title={pool ? 'Edit pool' : 'New pool'} />
            <PageTitle
                title={pool ? pool.name : 'New pool'}
                subtitle="Admin passwords and SSH keys are encrypted. Leave them blank to keep the current secret."
            />
            <Card className="max-w-2xl p-6">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Name" error={errors.name}>
                        <TextInput value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1 block w-full" />
                    </Field>
                    <Field label="Kind" error={errors.kind}>
                        <select value={data.kind} onChange={(event) => setData('kind', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            {kinds.map((kind) => <option key={kind} value={kind}>{kind}</option>)}
                        </select>
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Engine" error={errors.engine}>
                            <TextInput value={data.engine} onChange={(event) => setData('engine', event.target.value)} className="mt-1 block w-full" placeholder="mariadb" />
                        </Field>
                        <Field label="Runtime version" error={errors.runtime_version}>
                            <TextInput value={data.runtime_version} onChange={(event) => setData('runtime_version', event.target.value)} className="mt-1 block w-full" />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Server" error={errors.server_id}>
                            <select value={data.server_id} onChange={(event) => setData('server_id', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                <option value="">Unassigned</option>
                                {servers.map((server) => <option key={server.id} value={server.id}>{server.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Capacity" error={errors.capacity}>
                            <TextInput type="number" value={data.capacity} onChange={(event) => setData('capacity', event.target.value)} className="mt-1 block w-full" />
                        </Field>
                    </div>
                    <Field label="Dokploy ref" error={errors.dokploy_ref}>
                        <TextInput value={data.dokploy_ref} onChange={(event) => setData('dokploy_ref', event.target.value)} className="mt-1 block w-full" />
                    </Field>

                    {data.kind === 'database' && (
                        <div className="space-y-4 rounded-md border border-slate-200 p-4">
                            <div className="text-sm font-medium">Admin connection</div>
                            <p className="text-xs text-slate-500">Host and admin user make CREATE DATABASE run on this shared server. Dedicated mode asks Dokploy to create a database service instead.</p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Host" error={errors.host}>
                                    <TextInput value={data.host} onChange={(event) => setData('host', event.target.value)} className="mt-1 block w-full" />
                                </Field>
                                <Field label="Port" error={errors.port}>
                                    <TextInput value={data.port} onChange={(event) => setData('port', event.target.value)} className="mt-1 block w-full" />
                                </Field>
                            </div>
                            <Field label="Mode" error={errors.mode}>
                                <select value={data.mode} onChange={(event) => setData('mode', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                    <option value="shared">shared</option>
                                    <option value="dedicated">dedicated</option>
                                </select>
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Admin username" error={errors.admin_username}>
                                    <TextInput value={data.admin_username} onChange={(event) => setData('admin_username', event.target.value)} className="mt-1 block w-full" />
                                </Field>
                                <Field label="Admin database" error={errors.admin_database}>
                                    <TextInput value={data.admin_database} onChange={(event) => setData('admin_database', event.target.value)} className="mt-1 block w-full" placeholder="mysql" />
                                </Field>
                            </div>
                            <Field label={pool?.has_admin_password ? 'Admin password (saved)' : 'Admin password'} error={errors.admin_password}>
                                <TextInput type="password" value={data.admin_password} onChange={(event) => setData('admin_password', event.target.value)} className="mt-1 block w-full" autoComplete="new-password" />
                            </Field>
                            <Field label="Dokploy environment id" error={errors.dokploy_environment_id}>
                                <TextInput value={data.dokploy_environment_id} onChange={(event) => setData('dokploy_environment_id', event.target.value)} className="mt-1 block w-full" />
                            </Field>
                        </div>
                    )}

                    {data.kind === 'storage' && (
                        <div className="space-y-4 rounded-md border border-slate-200 p-4">
                            <div className="text-sm font-medium">SSH for SFTP users</div>
                            <p className="text-xs text-slate-500">Without a host and user, SFTP accounts stay reserved and the site still provisions.</p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="SSH host" error={errors.ssh_host}>
                                    <TextInput value={data.ssh_host} onChange={(event) => setData('ssh_host', event.target.value)} className="mt-1 block w-full" />
                                </Field>
                                <Field label="SSH port" error={errors.ssh_port}>
                                    <TextInput value={data.ssh_port} onChange={(event) => setData('ssh_port', event.target.value)} className="mt-1 block w-full" />
                                </Field>
                            </div>
                            <Field label="SSH username" error={errors.ssh_username}>
                                <TextInput value={data.ssh_username} onChange={(event) => setData('ssh_username', event.target.value)} className="mt-1 block w-full" />
                            </Field>
                            <Field label={pool?.has_ssh_key ? 'SSH private key (saved)' : 'SSH private key'} error={errors.ssh_private_key}>
                                <textarea
                                    value={data.ssh_private_key}
                                    onChange={(event) => setData('ssh_private_key', event.target.value)}
                                    className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                    rows="4"
                                />
                            </Field>
                        </div>
                    )}

                    <PrimaryButton disabled={processing}>{pool ? 'Save pool' : 'Create pool'}</PrimaryButton>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <InputLabel value={label} />
            {children}
            <InputError message={error} className="mt-1" />
        </div>
    );
}
