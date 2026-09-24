import { Pager } from '@/Pages/Clients/Index';
import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ sites, filters, filterOptions }) {
    const submit = (event) => {
        event.preventDefault();
        router.get(route('sites.index'), Object.fromEntries(new FormData(event.currentTarget)), {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout header="Sites">
            <Head title="Sites" />
            <PageTitle
                title="Sites"
                subtitle="Each site is a tenant on shared pools. Deploy, SSL, and logs stay in Dokploy."
                action={
                    <Link href={route('wizard.create')} className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white">
                        Provision site
                    </Link>
                }
            />

            <Card className="mb-4 p-4">
                <form onSubmit={submit} className="grid gap-3 md:grid-cols-5">
                    <input name="q" defaultValue={filters.q} placeholder="Domain, client, recipe" className="rounded-md border-slate-300 text-sm" />
                    <select name="status" defaultValue={filters.status} className="rounded-md border-slate-300 text-sm">
                        <option value="">Any status</option>
                        {filterOptions.statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                    </select>
                    <select name="recipe" defaultValue={filters.recipe} className="rounded-md border-slate-300 text-sm">
                        <option value="">Any recipe</option>
                        {filterOptions.recipes.map((recipe) => <option key={recipe.slug} value={recipe.slug}>{recipe.name}</option>)}
                    </select>
                    <select name="stack" defaultValue={filters.stack} className="rounded-md border-slate-300 text-sm">
                        <option value="">Any stack</option>
                        {filterOptions.stacks.map((stack) => <option key={stack} value={stack}>{stack}</option>)}
                    </select>
                    <button className="rounded-md border border-slate-300 px-3 py-2 text-sm">Filter</button>
                </form>
            </Card>

            <Card>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Domain</th>
                                <th className="px-4 py-3 font-medium">Client</th>
                                <th className="px-4 py-3 font-medium">Recipe</th>
                                <th className="px-4 py-3 font-medium">Pools</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {sites.data.map((site) => (
                                <tr key={site.id}>
                                    <td className="px-4 py-3">
                                        <Link href={route('sites.toolkit', site.id)} className="font-medium text-blue-700">{site.domain}</Link>
                                        {site.last_error && <div className="mt-1 max-w-xs text-xs text-red-700">{site.last_error}</div>}
                                    </td>
                                    <td className="px-4 py-3">{site.client}</td>
                                    <td className="px-4 py-3">{site.recipe}<div className="text-slate-500">{site.stack}</div></td>
                                    <td className="px-4 py-3 text-slate-600">{(site.pools || []).join(', ')}</td>
                                    <td className="px-4 py-3"><StatusBadge status={site.status} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pager links={sites.links} />
            </Card>
        </AuthenticatedLayout>
    );
}
