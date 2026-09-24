import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ stats, recentSites, pools, audits }) {
    const cards = [
        ['Clients', stats.clients],
        ['Sites', stats.sites],
        ['Pools', stats.pools],
        ['Recipes', stats.recipes],
    ];

    return (
        <AuthenticatedLayout header="Home">
            <Head title="Home" />
            <PageTitle
                title="Home"
                subtitle="Capacity, recent sites, and what the panel just did."
                action={
                    <Link
                        href={route('wizard.create')}
                        className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        Provision site
                    </Link>
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(([label, value]) => (
                    <Card key={label} className="p-4">
                        <div className="text-sm text-slate-500">{label}</div>
                        <div className="mt-1 text-2xl font-semibold">{value}</div>
                    </Card>
                ))}
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <div className="border-b border-slate-100 px-4 py-3 text-sm font-medium">Recent sites</div>
                    <div className="divide-y divide-slate-100">
                        {recentSites.length === 0 && <p className="px-4 py-6 text-sm text-slate-500">No sites yet.</p>}
                        {recentSites.map((site) => (
                            <Link
                                key={site.id}
                                href={route('sites.toolkit', site.id)}
                                className="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50"
                            >
                                <span>
                                    <span className="font-medium">{site.domain}</span>
                                    <span className="mt-0.5 block text-slate-500">{site.client} · {site.recipe}</span>
                                </span>
                                <StatusBadge status={site.status} />
                            </Link>
                        ))}
                    </div>
                </Card>

                <Card>
                    <div className="border-b border-slate-100 px-4 py-3 text-sm font-medium">Pool capacity</div>
                    <div className="space-y-4 p-4">
                        {pools.map((pool) => {
                            const percent = pool.capacity > 0 ? Math.min(100, Math.round((pool.usage / pool.capacity) * 100)) : 0;

                            return (
                                <div key={pool.id}>
                                    <div className="flex justify-between text-sm">
                                        <span className="font-medium">{pool.name}</span>
                                        <span className="text-slate-500">{pool.usage}/{pool.capacity} · {pool.kind}</span>
                                    </div>
                                    <div className="mt-2 h-2 rounded-full bg-slate-100">
                                        <div className="h-2 rounded-full bg-blue-600" style={{ width: `${percent}%` }} />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </Card>
            </div>

            <Card className="mt-6">
                <div className="border-b border-slate-100 px-4 py-3 text-sm font-medium">Activity</div>
                <div className="divide-y divide-slate-100">
                    {(audits || []).length === 0 && <p className="px-4 py-6 text-sm text-slate-500">No activity yet.</p>}
                    {(audits || []).map((audit) => (
                        <div key={audit.id} className="flex items-center justify-between px-4 py-3 text-sm">
                            <span>
                                <span className="font-medium">{audit.action}</span>
                                <span className="ml-2 text-slate-500">{audit.meta?.domain || audit.meta?.name || audit.actor}</span>
                            </span>
                            <span className="text-slate-400">{audit.created_at}</span>
                        </div>
                    ))}
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
