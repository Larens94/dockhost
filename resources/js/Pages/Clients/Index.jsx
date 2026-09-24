import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ clients, filters, filterOptions }) {
    const submit = (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        router.get(route('clients.index'), data, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout header="Clients">
            <Head title="Clients" />
            <PageTitle
                title="Clients"
                subtitle="Anagrafica for the sites you host. Billing status is owned by the subscription, not this form."
                action={
                    <Link href={route('clients.create')} className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white">
                        New client
                    </Link>
                }
            />

            <Card className="mb-4 p-4">
                <form onSubmit={submit} className="grid gap-3 md:grid-cols-4">
                    <input name="q" defaultValue={filters.q} placeholder="Search name, company, email" className="rounded-md border-slate-300 text-sm" />
                    <select name="status" defaultValue={filters.status} className="rounded-md border-slate-300 text-sm">
                        <option value="">Any status</option>
                        {filterOptions.statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                    </select>
                    <select name="billing_status" defaultValue={filters.billing_status} className="rounded-md border-slate-300 text-sm">
                        <option value="">Any billing</option>
                        {filterOptions.billingStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
                    </select>
                    <button className="rounded-md border border-slate-300 px-3 py-2 text-sm">Filter</button>
                </form>
            </Card>

            <Card>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Billing</th>
                                <th className="px-4 py-3 font-medium">Sites</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {clients.data.map((client) => (
                                <tr key={client.id}>
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{client.name}</div>
                                        <div className="text-slate-500">{client.company}</div>
                                    </td>
                                    <td className="px-4 py-3">{client.email}</td>
                                    <td className="px-4 py-3"><StatusBadge status={client.status} /></td>
                                    <td className="px-4 py-3"><StatusBadge status={client.billing_status} /></td>
                                    <td className="px-4 py-3">{client.sites_count}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route('clients.edit', client.id)} className="text-blue-700">Edit</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pager links={clients.links} />
            </Card>
        </AuthenticatedLayout>
    );
}

export function Pager({ links }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-3">
            {links.map((link, index) => (
                <Link
                    key={`${link.label}-${index}`}
                    href={link.url || '#'}
                    className={`rounded px-2 py-1 text-xs ${link.active ? 'bg-blue-600 text-white' : 'text-slate-600'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}
