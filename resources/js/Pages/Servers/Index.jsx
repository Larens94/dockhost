import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Index({ servers }) {
    const sync = useForm({});
    const form = useForm({
        name: '',
        ip: '',
        role: 'worker',
        status: 'online',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('servers.store'), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AuthenticatedLayout header="Servers">
            <Head title="Servers" />
            <PageTitle
                title="Servers"
                subtitle="Workers that hold the pools. Sync reads Dokploy when the API key is set."
                action={
                    <button
                        type="button"
                        disabled={sync.processing}
                        onClick={() => sync.post(route('servers.sync'))}
                        className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                    >
                        Sync from Dokploy
                    </button>
                }
            />
            <Card className="mb-6 p-5">
                <form onSubmit={submit} className="grid gap-3 md:grid-cols-5 md:items-end">
                    <label className="text-sm font-medium">
                        Name
                        <TextInput value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="mt-1 block w-full" />
                        <InputError message={form.errors.name} />
                    </label>
                    <label className="text-sm font-medium">
                        IP
                        <TextInput value={form.data.ip} onChange={(event) => form.setData('ip', event.target.value)} className="mt-1 block w-full" />
                    </label>
                    <label className="text-sm font-medium">
                        Role
                        <select value={form.data.role} onChange={(event) => form.setData('role', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            <option value="worker">worker</option>
                            <option value="database">database</option>
                            <option value="storage">storage</option>
                            <option value="edge">edge</option>
                        </select>
                    </label>
                    <label className="text-sm font-medium">
                        Status
                        <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            <option value="online">online</option>
                            <option value="offline">offline</option>
                            <option value="maintenance">maintenance</option>
                        </select>
                    </label>
                    <PrimaryButton disabled={form.processing}>Add server</PrimaryButton>
                </form>
            </Card>
            <Card>
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">IP</th>
                            <th className="px-4 py-3 font-medium">Role</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Dokploy</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {servers.map((server) => (
                            <tr key={server.id}>
                                <td className="px-4 py-3 font-medium">{server.name}</td>
                                <td className="px-4 py-3">{server.ip}</td>
                                <td className="px-4 py-3">{server.role}</td>
                                <td className="px-4 py-3"><StatusBadge status={server.status} /></td>
                                <td className="px-4 py-3 text-slate-500">{server.dokploy_server_id}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </AuthenticatedLayout>
    );
}
