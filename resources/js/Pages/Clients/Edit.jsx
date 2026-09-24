import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout, { Card, PageTitle } from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ client, statuses }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: client.name || '',
        company: client.company || '',
        email: client.email || '',
        billing_email: client.billing_email || '',
        status: client.status || 'active',
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('clients.update', client.id));
    };

    return (
        <AuthenticatedLayout header="Edit client">
            <Head title={`Edit ${client.name}`} />
            <PageTitle title={client.name} subtitle="Status can suspend new provisioning. It does not change Stripe." />
            <Card className="max-w-xl p-6">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Name" error={errors.name}>
                        <TextInput value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1 block w-full" />
                    </Field>
                    <Field label="Company" error={errors.company}>
                        <TextInput value={data.company} onChange={(event) => setData('company', event.target.value)} className="mt-1 block w-full" />
                    </Field>
                    <Field label="Email" error={errors.email}>
                        <TextInput type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className="mt-1 block w-full" />
                    </Field>
                    <Field label="Billing email" error={errors.billing_email}>
                        <TextInput type="email" value={data.billing_email} onChange={(event) => setData('billing_email', event.target.value)} className="mt-1 block w-full" />
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select value={data.status} onChange={(event) => setData('status', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                        </select>
                    </Field>
                    <div className="flex items-center justify-between">
                        <PrimaryButton disabled={processing}>Save</PrimaryButton>
                        <Link
                            href={route('clients.destroy', client.id)}
                            method="delete"
                            as="button"
                            className="text-sm text-red-700"
                            onClick={(event) => {
                                if (!confirm(`Delete ${client.name} and deprovision its sites?`)) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            Delete client
                        </Link>
                    </div>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <InputLabel value={label} />
            {children}
            <InputError message={error} className="mt-1" />
        </div>
    );
}
