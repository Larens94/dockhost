import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ services }) {
    return (
        <AuthenticatedLayout header="Services">
            <Head title="Services" />
            <PageTitle title="Service catalog" subtitle="Images DockHost knows how to place on a pool. Instantiating a service is still a Dokploy compose deploy." />
            <Card>
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Kind</th>
                            <th className="px-4 py-3 font-medium">Image</th>
                            <th className="px-4 py-3 font-medium">Mode</th>
                            <th className="px-4 py-3 font-medium">Support</th>
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
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </AuthenticatedLayout>
    );
}
