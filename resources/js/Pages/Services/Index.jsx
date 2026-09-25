import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

export default function Index({ services }) {
    const deploy = (service) => {
        router.post(route('services.deploy', service.id));
    };

    return (
        <AuthenticatedLayout header="Services">
            <Head title="Services" />
            <PageTitle title="Service catalog" subtitle="Each row can be created on the connected Dokploy environment: databases, Redis, MinIO, and SFTP." />
            <Card>
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Kind</th>
                            <th className="px-4 py-3 font-medium">Image</th>
                            <th className="px-4 py-3 font-medium">Mode</th>
                            <th className="px-4 py-3 font-medium">Support</th>
                            <th className="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {services.map((service) => (
                            <tr key={service.id}>
                                <td className="px-4 py-3 font-medium">{service.name}</td>
                                <td className="px-4 py-3">{service.kind}</td>
                                <td className="px-4 py-3 font-mono text-xs">{service.image}</td>
                                <td className="px-4 py-3">{service.mode}</td>
                                <td className="px-4 py-3"><StatusBadge status={service.support} /></td>
                                <td className="px-4 py-3 text-right">
                                    {service.dokploy_ref ? (
                                        <span className="text-xs text-slate-500">{String(service.dokploy_ref).startsWith('local_') ? 'Recorded locally' : 'On Dokploy'}</span>
                                    ) : (
                                        <button type="button" className="text-sm font-medium text-slate-900 underline" onClick={() => deploy(service)}>
                                            Deploy
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </AuthenticatedLayout>
    );
}
