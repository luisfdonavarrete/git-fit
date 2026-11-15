import { Head } from '@inertiajs/react';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Admin/RecipeController';
import {
    create,
    store,
} from '@/actions/App/Http/Controllers/Admin/RecipeVersionController';
import Heading from '@/components/heading';
import RecipeVersionForm from '@/components/recipe-version-form';
import type { CalorieRangeOption, CalorieRangeValue, Recipe } from '@/types';

type Props = {
    recipe: Pick<Recipe, 'id' | 'name'>;
    calorieRanges: CalorieRangeOption[];
    selectedRange: CalorieRangeValue | null;
};

export default function CreateRecipeVersion({
    recipe,
    calorieRanges,
    selectedRange,
}: Props) {
    return (
        <>
            <Head title={`New version · ${recipe.name}`} />

            <div className="mx-auto w-full max-w-7xl p-4 md:p-6">
                <Heading
                    title="Add calorie version"
                    description={`A new calorie-specific take on ${recipe.name}.`}
                />
                <RecipeVersionForm
                    action={store.url(recipe.id)}
                    method="post"
                    ranges={calorieRanges}
                    initialRange={selectedRange}
                    cancelHref={show.url(recipe.id)}
                    submitLabel="Add version"
                />
            </div>
        </>
    );
}

CreateRecipeVersion.layout = ({ recipe }: Props) => ({
    breadcrumbs: [
        { title: 'Recipes', href: index() },
        { title: recipe.name, href: show(recipe.id) },
        { title: 'New version', href: create(recipe.id) },
    ],
});
