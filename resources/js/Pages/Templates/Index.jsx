import AuthenticatedLayout, { Card, PageTitle } from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ templates }) {
    return (
        <AuthenticatedLayout header="Templates">
            <Head title="Templates" />
            <PageTitle title="Infrastructure templates" subtitle="Compose baselines for shared services. The file itself is deployed by Dokploy, not edited here." />
            <div className="grid gap-4 md:grid-cols-2">
                {templates.map((template) => (
                    <Card key={template.id} className="p-5">
                        <div className="font-medium">{template.name}</div>
                        <p className="mt-2 text-sm text-slate-600">{template.summary}</p>
                        <div className="mt-3 text-xs text-slate-500">v{template.version} · {(template.services || []).join(', ')}</div>
                    </Card>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
