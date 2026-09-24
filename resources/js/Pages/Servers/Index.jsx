import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Index({ servers }) {
    const { post, processing } = useForm({});

    return (
        <AuthenticatedLayout header="Servers">
            <Head title="Servers" />
            <PageTitle
                title="Servers"
                subtitle="Workers that hold the pools. Sync reads Dokploy when the API key is set."
                action={
                    <button
                        type="button"
                        disabled={processing}
                        onClick={() => post(route('servers.sync'))}
                        className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                    >
                        Sync from Dokploy
                    </button>
                }
            />
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
