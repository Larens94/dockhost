import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout, { Card, PageTitle } from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ statuses }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        company: '',
        email: '',
        billing_email: '',
        status: 'active',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('clients.store'));
    };

    return (
        <AuthenticatedLayout header="New client">
            <Head title="New client" />
            <PageTitle title="New client" subtitle="Creates the anagrafica only. Assign a plan from Billing before provisioning sites." />
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
                    <PrimaryButton disabled={processing}>Create client</PrimaryButton>
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
