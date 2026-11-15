import { router, useHttp } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Search,
    Sparkles,
    UtensilsCrossed,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { store } from '@/actions/App/Http/Controllers/MealEntryController';
import RecipeSearchController from '@/actions/App/Http/Controllers/RecipeSearchController';
import ServingsStepper from '@/components/servings-stepper';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { rangeLabel, rangeTone } from '@/lib/calorie-range';
import { formatGrams, grams } from '@/lib/nutrition';
import { cn } from '@/lib/utils';
import type { DiaryMeal, LoggableRecipe } from '@/types';

type Version = LoggableRecipe['versions'][number];

/**
 * Pick a recipe, choose its calorie version and servings, and log it to a diary section.
 */
export default function AddMealDialog({
    meal,
    date,
    trigger,
}: {
    meal: DiaryMeal;
    date: string;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<LoggableRecipe | null>(null);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setSelected(null);
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="flex max-h-[min(42rem,calc(100dvh-2rem))] flex-col gap-0 overflow-hidden p-0 sm:max-w-xl">
                {selected ? (
                    <RecipeDetail
                        recipe={selected}
                        meal={meal}
                        date={date}
                        onBack={() => setSelected(null)}
                        onLogged={() => {
                            setOpen(false);
                            setSelected(null);
                        }}
                    />
                ) : (
                    <RecipePicker meal={meal} onSelect={setSelected} />
                )}
            </DialogContent>
        </Dialog>
    );
}

function RecipePicker({
    meal,
    onSelect,
}: {
    meal: DiaryMeal;
    onSelect: (recipe: LoggableRecipe) => void;
}) {
    const [search, setSearch] = useState('');
    const [showAll, setShowAll] = useState(false);
    const [recipes, setRecipes] = useState<LoggableRecipe[] | null>(null);
    const latestRequest = useRef(0);
    const http = useHttp<
        Record<string, never>,
        { recipes: LoggableRecipe[] }
    >();

    useEffect(() => {
        const requestId = ++latestRequest.current;
        const timeout = setTimeout(
            async () => {
                const response = await http.get(
                    RecipeSearchController.url({
                        query: {
                            meal_type: meal.type,
                            ...(search.trim() ? { search: search.trim() } : {}),
                            ...(showAll ? { all: 1 } : {}),
                        },
                    }),
                );

                if (requestId === latestRequest.current) {
                    setRecipes(response.recipes);
                }
            },
            search ? 250 : 0,
        );

        return () => clearTimeout(timeout);
    }, [search, showAll, meal.type]);

    return (
        <>
            <DialogHeader className="space-y-3 border-b p-5 pb-4">
                <div>
                    <DialogTitle>Add to {meal.label}</DialogTitle>
                    <DialogDescription>
                        Pick a recipe from your collection.
                    </DialogDescription>
                </div>
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search recipes…"
                        aria-label="Search recipes"
                        className="h-10 rounded-xl pl-9"
                        autoFocus
                    />
                </div>
                <div
                    className="flex gap-1 rounded-xl bg-muted p-1 text-sm"
                    role="tablist"
                >
                    <Tab active={!showAll} onClick={() => setShowAll(false)}>
                        <Sparkles className="size-3.5" />
                        Suggested for {meal.label.toLowerCase()}
                    </Tab>
                    <Tab active={showAll} onClick={() => setShowAll(true)}>
                        All recipes
                    </Tab>
                </div>
            </DialogHeader>

            <div className="min-h-64 flex-1 overflow-y-auto p-2">
                {recipes === null ? (
                    <ul className="space-y-1 p-1">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <li key={i} className="flex items-center gap-3 p-2">
                                <Skeleton className="size-12 rounded-lg" />
                                <div className="flex-1 space-y-2">
                                    <Skeleton className="h-4 w-2/3" />
                                    <Skeleton className="h-3 w-1/3" />
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : recipes.length === 0 ? (
                    <div className="flex h-full min-h-60 flex-col items-center justify-center gap-3 px-6 text-center">
                        <span className="flex size-12 items-center justify-center rounded-full bg-muted">
                            <UtensilsCrossed className="size-5 text-muted-foreground" />
                        </span>
                        <p className="text-sm text-muted-foreground">
                            {search
                                ? `No recipes match “${search}”.`
                                : `No recipes are tagged for ${meal.label.toLowerCase()} yet.`}
                        </p>
                        {!showAll && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowAll(true)}
                            >
                                Browse all recipes
                            </Button>
                        )}
                    </div>
                ) : (
                    <ul
                        className={cn(
                            'space-y-0.5 transition-opacity',
                            http.processing && 'opacity-60',
                        )}
                    >
                        {recipes.map((recipe) => (
                            <li key={recipe.id}>
                                <RecipeOption
                                    recipe={recipe}
                                    onSelect={() => onSelect(recipe)}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function RecipeOption({
    recipe,
    onSelect,
}: {
    recipe: LoggableRecipe;
    onSelect: () => void;
}) {
    const calories = recipe.versions.map((v) => v.calories);
    const min = Math.min(...calories);
    const max = Math.max(...calories);

    return (
        <button
            type="button"
            onClick={onSelect}
            className="flex w-full items-center gap-3 rounded-xl p-2 text-left transition-colors outline-none hover:bg-accent focus-visible:bg-accent"
        >
            <RecipeThumb src={recipe.image_url} className="size-12" />
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium">
                    {recipe.name}
                </span>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {min === max ? `${min} kcal` : `${min}–${max} kcal`} ·{' '}
                    {recipe.versions.length}{' '}
                    {recipe.versions.length === 1 ? 'size' : 'sizes'}
                </span>
            </span>
            <span className="flex gap-0.5">
                {recipe.versions.map((v) => (
                    <span
                        key={v.id}
                        className={cn(
                            'h-4 w-1 rounded-full',
                            rangeTone(v.calorie_range).solid,
                        )}
                    />
                ))}
            </span>
        </button>
    );
}

function RecipeDetail({
    recipe,
    meal,
    date,
    onBack,
    onLogged,
}: {
    recipe: LoggableRecipe;
    meal: DiaryMeal;
    date: string;
    onBack: () => void;
    onLogged: () => void;
}) {
    const [version, setVersion] = useState<Version>(
        () =>
            recipe.versions.find((v) => v.calorie_range >= 300) ??
            recipe.versions[0],
    );
    const [servings, setServings] = useState(1);
    const [processing, setProcessing] = useState(false);

    const scaled = (value: string | number | null) =>
        Math.round(grams(value) * servings * 10) / 10;

    const log = () =>
        router.visit(store(), {
            data: {
                recipe_version_id: version.id,
                meal_type: meal.type,
                servings,
                eaten_on: date,
            },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onLogged,
        });

    return (
        <>
            <div className="relative h-40 shrink-0 overflow-hidden bg-muted">
                {recipe.image_url ? (
                    <img
                        src={recipe.image_url}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center bg-gradient-to-br from-muted to-muted-foreground/10">
                        <UtensilsCrossed className="size-8 text-muted-foreground/40" />
                    </div>
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-background via-background/30 to-transparent" />
                <Button
                    variant="secondary"
                    size="icon"
                    className="absolute top-3 left-3 size-8 rounded-full bg-background/80 backdrop-blur"
                    onClick={onBack}
                    aria-label="Back to recipes"
                >
                    <ArrowLeft />
                </Button>
            </div>

            <div className="-mt-10 flex-1 space-y-5 overflow-y-auto px-5 pb-5">
                <DialogHeader className="relative text-left">
                    <DialogTitle className="text-xl">{recipe.name}</DialogTitle>
                    {recipe.description && (
                        <DialogDescription className="line-clamp-2">
                            {recipe.description}
                        </DialogDescription>
                    )}
                </DialogHeader>

                <div className="space-y-2">
                    <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Size
                    </h3>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {recipe.versions.map((v) => {
                            const active = v.id === version.id;

                            return (
                                <button
                                    key={v.id}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => setVersion(v)}
                                    className={cn(
                                        'relative rounded-xl border p-2.5 text-left transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        active
                                            ? cn(
                                                  'ring-2',
                                                  rangeTone(v.calorie_range)
                                                      .ring,
                                              )
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {active && (
                                        <Check className="absolute top-2 right-2 size-3.5 text-foreground" />
                                    )}
                                    <span className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <span
                                            className={cn(
                                                'size-1.5 rounded-full',
                                                rangeTone(v.calorie_range)
                                                    .solid,
                                            )}
                                        />
                                        {rangeLabel(v.calorie_range)}
                                    </span>
                                    <span className="mt-1 block text-sm font-semibold tabular-nums">
                                        {v.calories} kcal
                                    </span>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {formatGrams(grams(v.protein_g))}{' '}
                                        protein
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Servings
                        </h3>
                    </div>
                    <ServingsStepper value={servings} onChange={setServings} />
                </div>

                <dl className="grid grid-cols-4 gap-2 rounded-xl bg-muted/60 p-3 text-center">
                    <Nutrient
                        label="Calories"
                        value={Math.round(
                            version.calories * servings,
                        ).toLocaleString()}
                    />
                    <Nutrient
                        label="Protein"
                        value={formatGrams(scaled(version.protein_g))}
                        className="text-sky-600 dark:text-sky-400"
                    />
                    <Nutrient
                        label="Carbs"
                        value={formatGrams(scaled(version.carbs_g))}
                    />
                    <Nutrient
                        label="Fat"
                        value={formatGrams(scaled(version.fat_g))}
                    />
                </dl>
            </div>

            <div className="border-t p-4">
                <Button
                    className="h-11 w-full rounded-xl"
                    onClick={log}
                    disabled={processing}
                >
                    Add to {meal.label}
                </Button>
            </div>
        </>
    );
}

function Nutrient({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div>
            <dt className="text-[11px] text-muted-foreground">{label}</dt>
            <dd className={cn('text-sm font-semibold tabular-nums', className)}>
                {value}
            </dd>
        </div>
    );
}

function RecipeThumb({
    src,
    className,
}: {
    src: string | null;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'shrink-0 overflow-hidden rounded-lg bg-muted',
                className,
            )}
        >
            {src ? (
                <img
                    src={src}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
            ) : (
                <span className="flex size-full items-center justify-center">
                    <UtensilsCrossed className="size-4 text-muted-foreground/50" />
                </span>
            )}
        </span>
    );
}

function Tab({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onClick}
            className={cn(
                'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition-all',
                active
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}
