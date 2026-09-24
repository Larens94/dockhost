import AuthenticatedLayout, { Card, PageTitle, StatusBadge } from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ recipes }) {
    return (
        <AuthenticatedLayout header="Recipes">
            <Head title="Recipes" />
            <PageTitle title="Recipes" subtitle="Installable applications. Laravel is the first one. The steps are data the provisioner actually runs." />
            <div className="grid gap-4 md:grid-cols-2">
                {recipes.map((recipe) => (
                    <Card key={recipe.id} className="p-5">
                        <div className="flex items-center justify-between">
                            <div className="font-medium">{recipe.name}</div>
                            <StatusBadge status={recipe.status} />
                        </div>
                        <p className="mt-2 text-sm text-slate-600">{recipe.summary}</p>
                        <div className="mt-3 text-xs uppercase tracking-wide text-slate-400">
                            {recipe.stack} · v{recipe.version} · {(recipe.requires || []).join(', ')}
                        </div>
                    </Card>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
