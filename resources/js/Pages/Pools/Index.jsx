import AuthenticatedLayout, { Card, PageTitle } from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ pools }) {
    return (
        <AuthenticatedLayout header="Pools">
            <Head title="Pools" />
            <PageTitle title="Pools" subtitle="Shared capacity. Usage counts sites that still hold a slot, not a hand-maintained number." />
            <div className="grid gap-4 md:grid-cols-2">
                {pools.map((pool) => (
                    <Card key={pool.id} className="p-5">
                        <div className="flex items-start justify-between">
                            <div>
                                <div className="font-medium">{pool.name}</div>
                                <div className="text-sm text-slate-500">{pool.kind} · {pool.engine}{pool.runtime_version ? ` ${pool.runtime_version}` : ''}</div>
                            </div>
                            <div className="text-sm text-slate-500">{pool.usage}/{pool.capacity}</div>
                        </div>
                        <div className="mt-3 h-2 rounded-full bg-slate-100">
                            <div className="h-2 rounded-full bg-blue-600" style={{ width: `${pool.capacity ? Math.min(100, (pool.usage / pool.capacity) * 100) : 0}%` }} />
                        </div>
                        <div className="mt-3 text-xs text-slate-500">{pool.server} · {pool.dokploy_ref || 'no Dokploy ref'}</div>
                    </Card>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
