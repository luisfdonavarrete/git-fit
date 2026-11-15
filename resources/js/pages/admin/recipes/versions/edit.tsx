import { Head } from '@inertiajs/react';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Admin/RecipeController';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Admin/RecipeVersionController';
import Heading from '@/components/heading';
import RecipeVersionForm from '@/components/recipe-version-form';
import { rangeLabel } from '@/lib/calorie-range';
import type { CalorieRangeOption, Recipe, RecipeVersion } from '@/types';

type Props = {
    recipe: Pick<Recipe, 'id' | 'name'>;
    version: RecipeVersion;
    calorieRanges: CalorieRangeOption[];
};

export default function EditRecipeVersion({
    recipe,
    version,
    calorieRanges,
}: Props) {
    return (
        <>
            <Head
                title={`Edit ${rangeLabel(version.calorie_range)} · ${recipe.name}`}
            />

            <div className="mx-auto w-full max-w-7xl p-4 md:p-6">
                <Heading
                    title={`Edit ${rangeLabel(version.calorie_range)} kcal version`}
                    description={recipe.name}
                />
                <RecipeVersionForm
                    action={update.url({
                        recipe: recipe.id,
                        version: version.id,
                    })}
                    method="put"
                    ranges={calorieRanges}
                    version={version}
                    cancelHref={show.url(recipe.id, {
                        query: { range: version.calorie_range },
                    })}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditRecipeVersion.layout = ({ recipe, version }: Props) => ({
    breadcrumbs: [
        { title: 'Recipes', href: index() },
        { title: recipe.name, href: show(recipe.id) },
        {
            title: `${rangeLabel(version.calorie_range)} kcal`,
            href: edit({ recipe: recipe.id, version: version.id }),
        },
    ],
});
