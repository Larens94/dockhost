import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout, { Card, PageTitle } from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Provision({ clients, recipes, pools }) {
    const { data, setData, post, processing, errors, transform } = useForm({
        client_id: clients[0]?.id || '',
        domain: '',
        repository: '',
        recipe_id: recipes[0]?.id || '',
        wants_database: true,
        database_pool_id: pools.database[0]?.id || '',
        wants_storage: true,
        storage_pool_id: pools.storage[0]?.id || '',
        wants_sftp: false,
        wants_cache: false,
        cache_pool_id: pools.cache[0]?.id || '',
        database_mode: 'shared',
        git_branch: 'main',
    });

    const client = clients.find((item) => String(item.id) === String(data.client_id));
    const recipe = recipes.find((item) => String(item.id) === String(data.recipe_id));
    const requires = recipe?.requires || [];

    const chooseRecipe = (next) => {
        setData((current) => ({
            ...current,
            recipe_id: next.id,
            wants_database: next.requires?.includes('database') ? true : current.wants_database,
            wants_storage: next.requires?.includes('storage') ? true : current.wants_storage,
            wants_cache: next.requires?.includes('cache') ? true : current.wants_cache,
        }));
    };

    const submit = (event) => {
        event.preventDefault();
        transform((payload) => ({
            ...payload,
            wants_database: payload.wants_database ? 1 : 0,
            wants_storage: payload.wants_storage ? 1 : 0,
            wants_sftp: payload.wants_sftp ? 1 : 0,
            wants_cache: payload.wants_cache ? 1 : 0,
            database_mode: payload.wants_database && payload.database_mode === 'dedicated' ? 'dedicated' : 'shared',
            database_pool_id: payload.wants_database ? payload.database_pool_id : null,
            storage_pool_id: payload.wants_storage || payload.wants_sftp ? payload.storage_pool_id : null,
            cache_pool_id: payload.wants_cache ? payload.cache_pool_id : null,
        }));
        post(route('wizard.store'));
    };

    return (
        <AuthenticatedLayout header="Wizard">
            <Head title="Provision site" />
            <PageTitle
                title="Provision a site"
                subtitle="Choose the client, the recipe, and the shared pools. DockHost creates the tenant; Dokploy deploys it."
            />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[2fr,1fr]">
                <div className="space-y-6">
                    <Card className="space-y-4 p-5">
                        <label className="block text-sm font-medium">
                            Client
                            <select
                                value={data.client_id}
                                onChange={(event) => setData('client_id', event.target.value)}
                                className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                            >
                                {clients.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} · {item.plan || 'no plan'} · {item.used}/{item.quota}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <InputError message={errors.client_id} />
                        {client && !client.can_provision && (
                            <p className="text-sm text-red-700">{client.reason}</p>
                        )}
                        {client?.can_provision && (
                            <p className="text-sm text-slate-500">
                                {client.plan} allows SFTP {client.entitlements.sftp ? 'yes' : 'no'}, cache {client.entitlements.cache ? 'yes' : 'no'}, dedicated database {client.entitlements.dedicated_database ? 'yes' : 'no'}.
                            </p>
                        )}

                        <label className="block text-sm font-medium">
                            Domain
                            <TextInput value={data.domain} onChange={(event) => setData('domain', event.target.value)} className="mt-1 block w-full" placeholder="shop.example.com" />
                        </label>
                        <InputError message={errors.domain} />

                        <label className="block text-sm font-medium">
                            Git repository
                            <TextInput value={data.repository} onChange={(event) => setData('repository', event.target.value)} className="mt-1 block w-full" placeholder="git@github.com:org/app.git" />
                        </label>
                        <label className="block text-sm font-medium">
                            Branch
                            <TextInput value={data.git_branch} onChange={(event) => setData('git_branch', event.target.value)} className="mt-1 block w-full" placeholder="main" />
                        </label>
                        <InputError message={errors.git_branch} />
                        <p className="text-xs text-slate-500">When Dokploy is connected, DockHost saves this repository before the first deploy.</p>
                    </Card>

                    <Card className="p-5">
                        <div className="text-sm font-medium">Recipe</div>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {recipes.map((item) => (
                                <button
                                    type="button"
                                    key={item.id}
                                    onClick={() => chooseRecipe(item)}
                                    className={`rounded-md border p-3 text-left text-sm ${String(data.recipe_id) === String(item.id) ? 'border-blue-600 bg-blue-50' : 'border-slate-200'}`}
                                >
                                    <div className="font-medium">{item.name}</div>
                                    <div className="mt-1 text-slate-500">{item.summary}</div>
                                    <div className="mt-2 text-xs uppercase tracking-wide text-slate-400">{item.stack} · {(item.requires || []).join(', ')}</div>
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.recipe_id} className="mt-2" />
                    </Card>

                    <Card className="space-y-4 p-5">
                        <Toggle
                            label="Database"
                            checked={data.wants_database}
                            locked={requires.includes('database')}
                            onChange={(value) => setData('wants_database', value)}
                            error={errors.wants_database}
                        >
                            <PoolSelect pools={pools.database} value={data.database_pool_id} onChange={(value) => setData('database_pool_id', value)} />
                            <InputError message={errors.database_pool_id} />
                            {client?.entitlements?.dedicated_database && (
                                <label className="mt-3 flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.database_mode === 'dedicated'}
                                        onChange={(event) => setData('database_mode', event.target.checked ? 'dedicated' : 'shared')}
                                    />
                                    Dedicated database service
                                </label>
                            )}
                            <InputError message={errors.database_mode} />
                        </Toggle>
                        <Toggle
                            label="Storage"
                            checked={data.wants_storage || data.wants_sftp}
                            locked={requires.includes('storage')}
                            onChange={(value) => setData('wants_storage', value)}
                            error={errors.wants_storage}
                        >
                            <PoolSelect pools={pools.storage} value={data.storage_pool_id} onChange={(value) => setData('storage_pool_id', value)} />
                            <InputError message={errors.storage_pool_id} />
                        </Toggle>
                        <Toggle
                            label="SFTP"
                            checked={data.wants_sftp}
                            disabled={client && !client.entitlements.sftp}
                            onChange={(value) => setData({ ...data, wants_sftp: value, wants_storage: value ? true : data.wants_storage })}
                            error={errors.wants_sftp}
                        />
                        <Toggle
                            label="Cache"
                            checked={data.wants_cache}
                            locked={requires.includes('cache')}
                            disabled={client && !client.entitlements.cache && !requires.includes('cache')}
                            onChange={(value) => setData('wants_cache', value)}
                            error={errors.wants_cache}
                        >
                            <PoolSelect pools={pools.cache} value={data.cache_pool_id} onChange={(value) => setData('cache_pool_id', value)} />
                            <InputError message={errors.cache_pool_id} />
                        </Toggle>
                    </Card>
                </div>

                <Card className="h-fit p-5">
                    <div className="text-sm font-medium">What DockHost will do</div>
                    <ul className="mt-3 space-y-2 text-sm text-slate-600">
                        <li>Check the plan quota and entitlements.</li>
                        <li>Hold one slot on each selected pool.</li>
                        <li>Reserve a database user and, when the pool has an admin DSN, create the schema.</li>
                        <li>Reserve an SFTP account when requested.</li>
                        <li>Write the site .env, connect the Git repository, and ask Dokploy to deploy. The site stays provisioning until Dokploy reports the app is up.</li>
                    </ul>
                    <PrimaryButton className="mt-5" disabled={processing || (client && !client.can_provision)}>
                        Provision
                    </PrimaryButton>
                </Card>
            </form>
        </AuthenticatedLayout>
    );
}

function Toggle({ label, checked, onChange, locked = false, disabled = false, error, children }) {
    return (
        <div className="rounded-md border border-slate-200 p-3">
            <label className="flex items-center gap-2 text-sm font-medium">
                <input
                    type="checkbox"
                    checked={checked}
                    disabled={locked || disabled}
                    onChange={(event) => onChange(event.target.checked)}
                />
                {label}
                {locked && <span className="text-xs font-normal text-slate-500">required by the recipe</span>}
                {disabled && <span className="text-xs font-normal text-slate-500">not in this plan</span>}
            </label>
            {checked && children && <div className="mt-3">{children}</div>}
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function PoolSelect({ pools, value, onChange }) {
    return (
        <select value={value} onChange={(event) => onChange(event.target.value)} className="block w-full rounded-md border-slate-300 text-sm">
            {pools.map((pool) => (
                <option key={pool.id} value={pool.id} disabled={!pool.available}>
                    {pool.name} · {pool.engine} · {pool.usage}/{pool.capacity} {pool.server ? `· ${pool.server}` : ''}
                </option>
            ))}
        </select>
    );
}
