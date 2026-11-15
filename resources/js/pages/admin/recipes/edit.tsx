import { Head } from '@inertiajs/react';
import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/Admin/RecipeController';
import Heading from '@/components/heading';
import RecipeForm from '@/components/recipe-form';
import type { DietaryTagOption, MealTypeOption, Recipe } from '@/types';

type Props = {
    recipe: Recipe;
    mealTypes: MealTypeOption[];
    dietaryTags: DietaryTagOption[];
};

export default function EditRecipe({ recipe, mealTypes, dietaryTags }: Props) {
    return (
        <>
            <Head title={`Edit ${recipe.name}`} />

            <div className="mx-auto w-full max-w-5xl p-4 md:p-6">
                <Heading
                    title="Edit recipe"
                    description="These details are shared by every calorie version."
                />
                <RecipeForm
                    form={update.form(recipe.id)}
                    recipe={recipe}
                    mealTypes={mealTypes}
                    dietaryTags={dietaryTags}
                    submitLabel="Save changes"
                    cancelHref={show.url(recipe.id)}
                />
            </div>
        </>
    );
}

EditRecipe.layout = ({ recipe }: Props) => ({
    breadcrumbs: [
        { title: 'Recipes', href: index() },
        { title: recipe.name, href: show(recipe.id) },
        { title: 'Edit', href: edit(recipe.id) },
    ],
});
