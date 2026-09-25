import AuthenticatedLayout, { Card, PageTitle } from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

export default function Index({ templates }) {
    return (
        <AuthenticatedLayout header="Templates">
            <Head title="Templates" />
            <PageTitle title="Infrastructure templates" subtitle="Compose stacks deployed as a Dokploy compose service." />
            <div className="grid gap-4 md:grid-cols-2">
                {templates.map((template) => (
                    <Card key={template.id} className="p-5">
                        <div className="font-medium">{template.name}</div>
                        <p className="mt-2 text-sm text-slate-600">{template.summary}</p>
                        <div className="mt-3 text-xs text-slate-500">v{template.version} · {(template.services || []).join(', ')}</div>
                        <div className="mt-4">
                            {template.dokploy_ref ? (
                                <span className="text-xs text-slate-500">{String(template.dokploy_ref).startsWith('local_') ? 'Recorded locally' : 'On Dokploy'}</span>
                            ) : (
                                <button
                                    type="button"
                                    className="text-sm font-medium text-slate-900 underline"
                                    onClick={() => router.post(route('templates.deploy', template.id))}
                                >
                                    Deploy
                                </button>
                            )}
                        </div>
                    </Card>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
