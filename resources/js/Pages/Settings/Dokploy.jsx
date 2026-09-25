import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Dokploy({ settings, dokployOwns }) {
    const { post, processing } = useForm({});

    return (
        <AuthenticatedLayout header="Dokploy">
            <Head title="Dokploy" />
            <PageTitle
                title="Dokploy"
                subtitle="DockHost calls the API. It does not rebuild deploy logs, SSL management, cron, or docker inspect."
                action={
                    <button
                        type="button"
                        disabled={processing}
                        onClick={() => post(route('settings.dokploy.ping'))}
                        className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white"
                    >
                        Ping
                    </button>
                }
            />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card className="p-5 text-sm">
                    <div className="flex items-center justify-between">
                        <div className="font-medium">Connection</div>
                        <StatusBadge status={settings.connected ? 'online' : 'unconfigured'} />
                    </div>
                    <dl className="mt-4 space-y-2">
                        <Row label="Driver" value={settings.driver} />
                        <Row label="URL" value={settings.url || 'Not set'} />
                        <Row label="API key" value={settings.api_key_masked || 'Not set'} />
                    </dl>
                    <p className="mt-4 text-slate-500">Values come from the environment. DOKPLOY_ENVIRONMENT_ID is required before a live create.</p>
                </Card>
                <Card className="p-5">
                    <div className="text-sm font-medium">Owned by Dokploy</div>
                    <ul className="mt-3 space-y-2 text-sm text-slate-600">
                        {dokployOwns.map((item) => <li key={item}>{item}</li>)}
                    </ul>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-slate-500">{label}</dt>
            <dd className="break-all text-right">{value}</dd>
        </div>
    );
}
