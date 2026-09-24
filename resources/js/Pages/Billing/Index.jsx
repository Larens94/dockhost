import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Index({ plans, subscriptions, stripeConfigured, clients }) {
    const { data, setData, post, processing, errors } = useForm({
        client_id: clients[0]?.id || '',
        plan_id: plans[0]?.id || '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('billing.assign'));
    };

    return (
        <AuthenticatedLayout header="Billing">
            <Head title="Billing" />
            <PageTitle
                title="Billing"
                subtitle={stripeConfigured
                    ? 'Real Stripe prices open Checkout. Stub price ids activate locally.'
                    : 'Stripe is not configured. Assignments activate immediately so you can test quotas.'}
            />

            <div className="grid gap-4 md:grid-cols-3">
                {plans.map((plan) => (
                    <Card key={plan.id} className="p-5">
                        <div className="text-sm text-slate-500">{plan.name}</div>
                        <div className="mt-1 text-2xl font-semibold">{plan.price}</div>
                        <div className="text-sm text-slate-500">per {plan.interval} · {plan.site_quota} sites</div>
                        <ul className="mt-4 space-y-1 text-sm text-slate-600">
                            {(plan.features || []).map((feature) => <li key={feature}>{feature}</li>)}
                        </ul>
                    </Card>
                ))}
            </div>

            <Card className="mt-6 p-5">
                <form onSubmit={submit} className="grid gap-3 md:grid-cols-[1fr,1fr,auto] md:items-end">
                    <label className="text-sm font-medium">
                        Client
                        <select value={data.client_id} onChange={(event) => setData('client_id', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            {clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}
                        </select>
                    </label>
                    <label className="text-sm font-medium">
                        Plan
                        <select value={data.plan_id} onChange={(event) => setData('plan_id', event.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            {plans.map((plan) => <option key={plan.id} value={plan.id}>{plan.name}</option>)}
                        </select>
                    </label>
                    <PrimaryButton disabled={processing}>Assign</PrimaryButton>
                </form>
                <InputError message={errors.client_id || errors.plan_id} className="mt-2" />
            </Card>

            <Card className="mt-6">
                <div className="border-b border-slate-100 px-4 py-3 text-sm font-medium">Subscriptions</div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Client</th>
                                <th className="px-4 py-3 font-medium">Plan</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Period end</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {subscriptions.map((subscription) => (
                                <tr key={subscription.id}>
                                    <td className="px-4 py-3">{subscription.client}</td>
                                    <td className="px-4 py-3">{subscription.plan}</td>
                                    <td className="px-4 py-3"><StatusBadge status={subscription.status} /></td>
                                    <td className="px-4 py-3">{subscription.period_end}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
