import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CreditCard,
    Database,
    Globe,
    Layers,
    LayoutDashboard,
    Server,
    Settings,
    Users,
    Wand2,
    Boxes,
} from 'lucide-react';

const navigation = [
    { name: 'Home', route: 'dashboard', icon: LayoutDashboard },
    { name: 'Clients', route: 'clients.index', icon: Users },
    { name: 'Sites', route: 'sites.index', icon: Globe },
    { name: 'Wizard', route: 'wizard.create', icon: Wand2 },
    { name: 'Pools', route: 'pools.index', icon: Database },
    { name: 'Servers', route: 'servers.index', icon: Server },
    { name: 'Services', route: 'services.index', icon: Boxes },
    { name: 'Recipes', route: 'recipes.index', icon: BookOpen },
    { name: 'Templates', route: 'templates.index', icon: Layers },
    { name: 'Billing', route: 'billing.index', icon: CreditCard },
    { name: 'Dokploy', route: 'settings.dokploy', icon: Settings },
];

function NavLinks({ onClick }) {
    return navigation.map((item) => {
        const href = route(item.route);
        const root = item.route.split('.')[0];
        const active = item.route === 'dashboard'
            ? route().current('dashboard')
            : route().current(`${root}.*`) || route().current(item.route);
        const Icon = item.icon;

        return (
            <Link
                key={item.route}
                href={href}
                onClick={onClick}
                className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium ${
                    active
                        ? 'bg-blue-50 text-blue-700'
                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                }`}
            >
                <Icon className="h-4 w-4" />
                {item.name}
            </Link>
        );
    });
}

export default function AuthenticatedLayout({ header, children }) {
    const { auth, flash, appName } = usePage().props;

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-slate-200 bg-white md:flex">
                <div className="flex h-14 items-center gap-2 border-b border-slate-200 px-4">
                    <span className="flex h-8 w-8 items-center justify-center rounded-md bg-blue-600 text-sm font-semibold text-white">
                        DH
                    </span>
                    <div>
                        <div className="text-sm font-semibold">{appName || 'DockHost'}</div>
                        <div className="text-xs text-slate-500">Control plane</div>
                    </div>
                </div>
                <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
                    <NavLinks />
                </nav>
            </aside>

            <div className="md:pl-60">
                <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
                    <div className="flex h-14 items-center justify-between px-4 md:px-6">
                        <div className="text-sm font-semibold md:hidden">{appName || 'DockHost'}</div>
                        <div className="hidden text-sm text-slate-500 md:block">
                            {typeof header === 'string' ? header : 'Hosting control plane'}
                        </div>
                        <div className="flex items-center gap-4 text-sm">
                            <Link href={route('profile.edit')} className="text-slate-600 hover:text-slate-900">
                                {auth.user?.name}
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="text-slate-500 hover:text-slate-900"
                            >
                                Log out
                            </Link>
                        </div>
                    </div>
                    <nav className="flex gap-1 overflow-x-auto border-t border-slate-100 px-2 py-2 md:hidden">
                        <NavLinks />
                    </nav>
                </header>

                {(flash?.success || flash?.error) && (
                    <div className="px-4 pt-4 md:px-6">
                        {flash.success && (
                            <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                {flash.success}
                            </div>
                        )}
                        {flash.error && (
                            <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                                {flash.error}
                            </div>
                        )}
                    </div>
                )}

                <main className="px-4 py-6 md:px-6">
                    {header && typeof header !== 'string' && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}

export function PageTitle({ title, subtitle, action }) {
    return (
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
            </div>
            {action}
        </div>
    );
}

export function Card({ children, className = '' }) {
    return <div className={`rounded-lg border border-slate-200 bg-white shadow-sm ${className}`}>{children}</div>;
}

export function StatusBadge({ status }) {
    const tone = {
        active: 'bg-emerald-50 text-emerald-700',
        provisioning: 'bg-blue-50 text-blue-700',
        pending: 'bg-amber-50 text-amber-700',
        failed: 'bg-red-50 text-red-700',
        suspended: 'bg-orange-50 text-orange-700',
        reserved: 'bg-slate-100 text-slate-700',
        provisioned: 'bg-emerald-50 text-emerald-700',
        past_due: 'bg-red-50 text-red-700',
        none: 'bg-slate-100 text-slate-600',
        online: 'bg-emerald-50 text-emerald-700',
        stable: 'bg-emerald-50 text-emerald-700',
        beta: 'bg-amber-50 text-amber-700',
        draft: 'bg-slate-100 text-slate-600',
        incomplete: 'bg-amber-50 text-amber-700',
        canceled: 'bg-slate-100 text-slate-600',
        trialing: 'bg-blue-50 text-blue-700',
        archived: 'bg-slate-100 text-slate-600',
    }[status] || 'bg-slate-100 text-slate-700';

    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${tone}`}>
            {status || 'unknown'}
        </span>
    );
}
