import { Head } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/Admin/RecipeController';
import Heading from '@/components/heading';
import RecipeForm from '@/components/recipe-form';
import type { MealTypeOption } from '@/types';

export default function CreateRecipe({
    mealTypes,
}: {
    mealTypes: MealTypeOption[];
}) {
    return (
        <>
            <Head title="New recipe" />

            <div className="mx-auto w-full max-w-5xl p-4 md:p-6">
                <Heading
                    title="New recipe"
                    description="Start with the shared details. You'll add calorie versions next."
                />
                <RecipeForm
                    form={store.form()}
                    mealTypes={mealTypes}
                    submitLabel="Create recipe"
                    cancelHref={index.url()}
                />
            </div>
        </>
    );
}

CreateRecipe.layout = {
    breadcrumbs: [
        { title: 'Recipes', href: index() },
        { title: 'New', href: create() },
    ],
};
